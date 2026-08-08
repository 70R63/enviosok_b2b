<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Billing\Models\UsageEvent;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\TenantOperationService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Services\ZigoCommercialQuoteService;
use App\Services\ZigoProviderRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class NetworkTenantB2cTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_verified_host_is_branded_and_unknown_host_is_404(): void
    {
        $tenant = $this->tenant('alpha', ['B2C', 'SHIPPING']);
        $tenant->branding()->create(['brand_name' => 'Rapid Alpha', 'primary_color' => '#123456']);
        $this->get($this->url($tenant, '/cotizar'))->assertOk()->assertSee('Rapid Alpha')->assertSee('CP origen');
        $this->get('http://unknown.zigo.local/cotizar')->assertNotFound();
    }

    public function test_subscription_and_entitlements_guard_channel_quote_and_tracking(): void
    {
        $suspended = $this->tenant('suspended', ['B2C', 'SHIPPING'], 'suspended');
        $this->get($this->url($suspended, '/cotizar'))->assertStatus(503);
        $noB2c = $this->tenant('no-b2c', ['SHIPPING']);
        $this->get($this->url($noB2c, '/cotizar'))->assertForbidden();
        $noShipping = $this->tenant('no-shipping', ['B2C']);
        $this->get($this->url($noShipping, '/cotizar'))->assertForbidden();
        $this->get($this->url($noShipping, '/tracking'))->assertForbidden();
    }

    public function test_quote_uses_legacy_adapters_is_tenant_owned_and_does_not_consume_operations(): void
    {
        $tenant = $this->tenant('quote', ['B2C', 'SHIPPING']);
        $this->mock(ZigoProviderRateService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getOptionsForCotizacion')->once()->andReturn([['logistico' => 'Estafeta', 'servicio' => 'Terrestre', 'service_code' => 'terrestre', 'provider_source' => 'xperta', 'base_price' => 100]]);
        });
        $this->mock(ZigoCommercialQuoteService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('calculate')->once()->andReturn(['carrier' => 'ESTAFETA', 'service' => 'Terrestre', 'final_price' => 149.50]);
        });
        $this->post($this->url($tenant, '/cotizar'), $this->quote())->assertOk()->assertSee('149.50')->assertDontSee('base_price')->assertDontSee('100.00');
        $operation = TenantOperation::firstOrFail();
        $this->assertSame($tenant->id, $operation->tenant_id);
        $this->assertSame('quoted', $operation->status);
        $this->assertDatabaseCount('network_usage_events', 0);
        $this->assertSame('64000', $operation->metadata['origin_postal_code']);
    }

    public function test_fallback_only_quote_returns_controlled_error_preserves_input_and_rolls_back(): void
    {
        $tenant = $this->tenant('fallback', ['B2C', 'SHIPPING']);
        $this->mock(ZigoProviderRateService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getOptionsForCotizacion')->once()->andReturn([[
                'logistico' => 'Estafeta', 'servicio' => 'Terrestre', 'base_price' => 395,
                'provider_source' => 'estafeta_solo_cotizacion', 'is_fallback_rate' => true,
            ]]);
        });
        $payload = ['cp_origen' => '64000', 'cp_destino' => '57300', 'tipo_envio' => 'caja', 'peso' => 2.5, 'length' => 20, 'width' => 15, 'height' => 10];

        $this->from($this->url($tenant, '/cotizar'))->post($this->url($tenant, '/cotizar'), $payload)
            ->assertRedirect($this->url($tenant, '/cotizar'))
            ->assertSessionHasErrors('quote')
            ->assertSessionHasInput('cp_origen', '64000')
            ->assertSessionHasInput('cp_destino', '57300')
            ->assertSessionHasInput('tipo_envio', 'caja')
            ->assertSessionHasInput('peso', 2.5)
            ->assertSessionHasInput('length', 20);

        $this->assertDatabaseCount('b2c_cotizaciones', 0);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
    }

    public function test_provider_without_options_returns_controlled_error_and_rolls_back(): void
    {
        $tenant = $this->tenant('no-options', ['B2C', 'SHIPPING']);
        $this->mock(ZigoProviderRateService::class, fn (MockInterface $mock) => $mock->shouldReceive('getOptionsForCotizacion')->once()->andReturn([]));

        $this->from($this->url($tenant, '/cotizar'))->post($this->url($tenant, '/cotizar'), $this->quote())
            ->assertRedirect($this->url($tenant, '/cotizar'))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('b2c_cotizaciones', 0);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
    }

    public function test_confirm_is_tenant_scoped_and_usage_is_idempotent(): void
    {
        $a = $this->tenant('tenant-a', ['B2C', 'SHIPPING']);
        $b = $this->tenant('tenant-b', ['B2C', 'SHIPPING']);
        $operation = TenantOperation::create(['tenant_id' => $a->id, 'subscription_id' => $a->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted']);
        $service = app(TenantOperationService::class);
        $service->confirm($a, $operation);
        $service->confirm($a, $operation->fresh());
        $this->assertSame(1, UsageEvent::where('tenant_id', $a->id)->sum('quantity'));
        $this->expectException(NotFoundHttpException::class);
        $service->confirm($b, $operation);
    }

    public function test_admin_operations_are_isolated_and_postal_64000_routes_remain_available(): void
    {
        $a = $this->tenant('admin-a', ['B2C', 'SHIPPING']);
        $b = $this->tenant('admin-b', ['B2C', 'SHIPPING']);
        TenantOperation::create(['tenant_id' => $a->id, 'subscription_id' => $a->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted']);
        $foreign = TenantOperation::create(['tenant_id' => $b->id, 'subscription_id' => $b->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted']);
        $user = $this->user();
        $a->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->get($this->url($a, '/admin/operations'))->assertOk()->assertDontSee($foreign->uuid);
        DB::table('zigo_postal_codes')->insert(['codigo_postal' => '64000', 'asentamiento' => 'Centro', 'tipo_asentamiento' => 'Colonia', 'municipio' => 'Monterrey', 'estado' => 'Nuevo León', 'ciudad' => 'Monterrey', 'activo' => true]);
        $this->get($this->url($a, '/postal-code/lookup/64000'))->assertOk()->assertJsonPath('codigo_postal', '64000');
        $this->get($this->url($a, '/b2c/cp/colonias?cp=64000'))->assertOk()->assertJsonPath('data.0.d_codigo', '64000');
        $this->assertNotNull(app('router')->getRoutes()->getByName('home'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('b2c.cotizar'));
    }

    public function test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private(): void
    {
        $a = $this->tenant('private-a', ['B2C', 'SHIPPING', 'TRACKING']);
        $b = $this->tenant('private-b', ['B2C', 'SHIPPING', 'TRACKING']);
        $operation = TenantOperation::create(['tenant_id' => $b->id, 'subscription_id' => $b->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'confirmed', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL64000SD', 'metadata' => ['final_price' => 120]]);
        $shipment = LocalShipment::create([
            'tenant_id' => $b->id, 'tenant_operation_id' => $operation->id, 'tracking_number' => 'ZL260808PRIVATE001', 'service_code' => 'LOCAL64000SD', 'status' => 'CREATED',
            'sender_snapshot' => ['name' => 'Secret Sender', 'address' => 'Private Street 1', 'phone' => '8112345678'],
            'recipient_snapshot' => ['name' => 'Secret Recipient', 'address' => 'Private Street 2', 'phone' => '8187654321'],
            'package_snapshot' => ['type' => 'sobre', 'weight' => 1],
            'pricing_snapshot' => ['final_price' => 120, 'base_cost' => 80, 'margin' => 'internal-margin-secret', 'credential' => 'never-public'],
            'guide_snapshot' => ['tracking_number' => 'ZL260808PRIVATE001', 'branding' => ['brand_name' => 'Private B'], 'sender' => [], 'recipient' => [], 'package' => []],
        ]);
        $shipment->events()->create(['status' => 'CREATED', 'event_code' => 'SHIPMENT_CREATED', 'description' => 'private event note', 'occurred_at' => now()]);
        $user = $this->user();
        $a->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)->get($this->url($a, '/admin/operations/'.$operation->uuid))->assertNotFound();
        $this->post($this->url($a, '/admin/operations/'.$operation->uuid.'/local-shipment'), [])->assertNotFound();
        $this->get($this->url($a, '/admin/operations/'.$operation->uuid.'/guide.pdf'))->assertNotFound();
        $this->get($this->url($a, '/tracking/'.$shipment->tracking_number))->assertNotFound();

        $response = $this->get($this->url($b, '/tracking/'.$shipment->tracking_number))->assertOk()->assertSee($shipment->tracking_number)->assertSee('CREATED');
        foreach (['Secret Sender', 'Private Street', '8112345678', '8187654321', '$80.00', 'internal-margin-secret', 'never-public', 'private event note'] as $private) {
            $response->assertDontSee($private);
        }
    }

    private function quote(): array
    {
        return ['cp_origen' => '64000', 'cp_destino' => '64000', 'tipo_envio' => 'sobre', 'peso' => 1];
    }

    private function url(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->primaryDomain()->first()->domain.$path;
    }

    private function tenant(string $slug, array $codes, string $status = 'active'): Tenant
    {
        $plan = Plan::create(['code' => strtoupper($slug), 'name' => $slug, 'status' => 'active', 'currency' => 'MXN', 'included_operations' => 300]);
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $tenant->domains()->create(['domain' => $slug.'.zigo.local', 'type' => 'subdomain', 'environment' => 'production', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status, 'operations_limit' => 300, 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        foreach ($codes as $code) {
            $module = Module::firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'addon', 'is_active' => true, 'sort_order' => 1]);
            Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']);
        }

        return $tenant;
    }

    private function user(): User
    {
        $user = User::forceCreate(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => Hash::make('secret'), 'empresa_id' => 1]);
        $role = Roles::create(['name' => 'usuario', 'slug' => 'usuario']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function schema(): void
    {
        Schema::create('users', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('name'), $t->string('email'), $t->string('password'), $t->unsignedBigInteger('empresa_id'), $t->rememberToken(), $t->timestamps()]));
        Schema::create('roles', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('name'), $t->string('slug'), $t->timestamps()]));
        Schema::create('users_roles', fn (Blueprint $t) => tap($t, fn ($t) => [$t->unsignedBigInteger('user_id'), $t->unsignedBigInteger('roles_id')]));
        Schema::create('network_plans', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('code'), $t->string('name'), $t->string('status'), $t->string('currency'), $t->unsignedInteger('included_operations')->nullable(), $t->timestamps()]));
        Schema::create('network_modules', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('code')->unique(), $t->string('name'), $t->string('type'), $t->boolean('is_active'), $t->unsignedSmallInteger('sort_order'), $t->timestamps()]));
        Schema::create('network_tenants', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->string('name'), $t->string('slug')->unique(), $t->string('status'), $t->unsignedBigInteger('current_plan_id')->nullable(), $t->timestamps()]));
        Schema::create('network_tenant_domains', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->string('domain')->unique(), $t->string('type'), $t->string('environment'), $t->boolean('is_primary'), $t->string('status'), $t->timestamp('verified_at')->nullable(), $t->timestamps()]));
        Schema::create('network_tenant_brandings', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id')->unique(), $t->string('brand_name')->nullable(), $t->string('logo_path')->nullable(), $t->string('primary_color')->nullable(), $t->string('secondary_color')->nullable(), $t->string('accent_color')->nullable(), $t->string('favicon_path')->nullable(), $t->string('support_email')->nullable(), $t->string('support_phone')->nullable(), $t->timestamps()]));
        Schema::create('network_tenant_memberships', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('user_id'), $t->string('role'), $t->string('status'), $t->timestamps()]));
        Schema::create('network_subscriptions', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->unsignedInteger('operations_limit')->nullable(), $t->timestamp('started_at'), $t->timestamp('current_period_start'), $t->timestamp('current_period_end'), $t->timestamp('trial_ends_at')->nullable(), $t->timestamp('grace_ends_at')->nullable(), $t->timestamp('canceled_at')->nullable(), $t->timestamp('ended_at')->nullable(), $t->timestamps()]));
        Schema::create('network_entitlements', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('subscription_id'), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('module_id'), $t->string('code'), $t->boolean('is_enabled'), $t->unsignedInteger('limit_value')->nullable(), $t->string('source'), $t->timestamps()]));
        Schema::create('network_usage_events', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->string('metric'), $t->unsignedInteger('quantity'), $t->string('idempotency_key')->nullable(), $t->timestamp('occurred_at'), $t->json('metadata')->nullable(), $t->timestamp('created_at')->nullable(), $t->unique(['tenant_id', 'metric', 'idempotency_key'])]));
        Schema::create('network_tenant_operations', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->string('channel'), $t->string('status'), $t->string('source_type')->nullable(), $t->unsignedBigInteger('source_id')->nullable(), $t->string('provider')->nullable(), $t->string('service_code')->nullable(), $t->string('external_reference')->nullable(), $t->unsignedBigInteger('created_by_user_id')->nullable(), $t->json('metadata')->nullable(), $t->timestamps()]));
        Schema::create('b2c_cotizaciones', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('user_id')->nullable(), $t->string('cp_origen'), $t->string('cp_destino'), $t->string('tipo_envio'), $t->decimal('peso'), $t->decimal('peso_real')->nullable(), $t->decimal('peso_facturable')->nullable(), $t->string('medidas')->nullable(), $t->string('estatus'), $t->string('referencia')->nullable(), $t->timestamps()]));
        Schema::create('zigo_postal_codes', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('codigo_postal'), $t->string('asentamiento'), $t->string('tipo_asentamiento')->nullable(), $t->string('municipio'), $t->string('estado'), $t->string('ciudad')->nullable(), $t->boolean('activo')]));
        $migration = require database_path('migrations/2026_08_09_100000_create_local_shipping_foundation_tables.php');
        $migration->up();
    }
}
