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
use App\Domain\Shipping\Local\Models\{LocalShippingPackageRule, LocalShippingPricingRule, LocalShippingQuoteSnapshot, LocalShippingService, LocalShippingZone, LocalShippingZonePostalCode};
use App\Domain\Shipping\Local\Routing\RouteDistanceProvider;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Services\ZigoProviderRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $this->postalFixtures();
    }

    public function test_verified_host_is_branded_and_unknown_host_is_404(): void
    {
        $tenant = $this->tenant('alpha', $this->storefrontEntitlements());
        $tenant->branding()->create(['brand_name' => 'Rapid Alpha', 'primary_color' => '#123456']);
        $this->get($this->url($tenant, '/cotizar'))->assertOk()->assertSee('Rapid Alpha')->assertSee('CP origen');
        $this->get('http://unknown.zigo.local/cotizar')->assertNotFound();
    }

    public function test_tenant_storefront_routes_are_public_branded_and_isolated_from_corporate_hosts(): void
    {
        $tenant = $this->tenant('storefront', $this->storefrontEntitlements());
        $tenant->branding()->create([
            'brand_name' => 'Storefront Express',
            'primary_color' => '#123456',
            'secondary_color' => '#234567',
            'accent_color' => '#345678',
            'support_email' => 'ayuda@storefront.test',
            'support_phone' => '81 1234 5678',
        ]);

        $home = $this->get($this->url($tenant, '/'))->assertOk()
            ->assertSee('Envía fácil con Storefront Express')
            ->assertSee('Cotizar envío')
            ->assertSee('Regístrate o inicia sesión')
            ->assertSee('ayuda@storefront.test')
            ->assertSee('#123456', false)
            ->assertDontSee('Tenant')
            ->assertDontSee('White Label')
            ->assertDontSee('Sandbox');
        $this->assertSame(1, substr_count($home->getContent(), 'Cotiza</div>'));

        $this->get($this->url($tenant, '/cotizar'))->assertOk()->assertSee('Storefront Express');
        $this->get($this->url($tenant, '/rastrear'))->assertOk()->assertSee('Rastrea tu envío');
        $this->get($this->url($tenant, '/registro'))->assertOk()->assertSee('Crea tu cuenta');
        $this->get($this->url($tenant, '/login'))->assertOk()->assertSee('Iniciar sesión');
        $this->get($this->url($tenant, '/white-label'))->assertStatus(301)->assertRedirect('/');
        $this->get($this->url($tenant, '/admin'))->assertRedirect(route('tenant.admin.login', [], false));
        $this->get('http://unknown.zigo.local/')->assertNotFound();
        $this->get('http://stage.zigo-envios.com/')->assertDontSee('Storefront Express');
        $this->get('http://zigo-envios.com/')->assertDontSee('Storefront Express');
    }

    public function test_landing_uses_safe_tenant_hero_and_contextual_navigation(): void
    {
        Storage::fake('public');
        $tenant = $this->tenant('premium-landing', $this->storefrontEntitlements());
        $hero = 'tenant-branding/'.$tenant->uuid.'/hero.webp';
        Storage::disk('public')->put($hero, 'image');
        $tenant->branding()->create(['brand_name' => 'Premium Express', 'hero_image_path' => $hero]);

        $guest = $this->get($this->url($tenant, '/'))->assertOk()
            ->assertSee(Storage::disk('public')->url($hero), false)
            ->assertSee('Iniciar sesión')->assertSee('Crear cuenta')
            ->assertSee('¿Ya tienes una guía?')->assertSee('data-landing-tracking', false)
            ->assertDontSee('href="/#cotizar"', false);
        $guest->assertSee('action="/cotizar"', false);

        Storage::disk('public')->delete($hero);
        $this->get($this->url($tenant, '/'))->assertOk()
            ->assertSee(asset('images/tenant-hero-fallback.svg'), false)
            ->assertDontSee(Storage::disk('public')->url($hero), false);

        $customer = $this->user('premium-customer@test.local');
        $this->actingAs($customer)->get($this->url($tenant, '/'))->assertOk()
            ->assertSee('Envíos')->assertSee('Mi cuenta')->assertDontSee('Crear cuenta')
            ->assertDontSee(route('tenant.customer.app.quote', [], false), false);
    }

    public function test_subscription_and_entitlements_guard_channel_quote_and_tracking(): void
    {
        $suspended = $this->tenant('suspended', $this->storefrontEntitlements(), 'suspended');
        $this->get($this->url($suspended, '/cotizar'))->assertStatus(503);
        $noQuotes = $this->tenant('no-quotes', ['CUSTOMERS', 'SHIPPING', 'TRACKING', 'WHITE_LABEL']);
        $this->get($this->url($noQuotes, '/cotizar'))->assertForbidden();
        $noShipping = $this->tenant('no-shipping', ['CUSTOMERS', 'QUOTES', 'TRACKING', 'WHITE_LABEL']);
        $this->get($this->url($noShipping, '/cotizar'))->assertOk();
        $this->post($this->url($noShipping, '/cotizar'), $this->quote())->assertForbidden();
        $noTracking = $this->tenant('no-tracking', ['CUSTOMERS', 'QUOTES', 'SHIPPING', 'WHITE_LABEL']);
        $this->get($this->url($noTracking, '/rastrear'))->assertForbidden();
    }

    public function test_storefront_capabilities_are_independent_and_do_not_require_b2c(): void
    {
        $landing = $this->tenant('landing-only', ['WHITE_LABEL']);
        $this->get($this->url($landing, '/'))->assertOk();
        $this->get($this->url($landing, '/login'))->assertForbidden();

        $noLanding = $this->tenant('no-landing', ['CUSTOMERS', 'QUOTES', 'SHIPPING', 'TRACKING']);
        $this->get($this->url($noLanding, '/'))->assertForbidden();
        $this->get($this->url($noLanding, '/login'))->assertOk();
        $this->get($this->url($noLanding, '/registro'))->assertOk();
        $this->get($this->url($noLanding, '/cotizar'))->assertOk();
        $this->get($this->url($noLanding, '/rastrear'))->assertOk();
        $this->get($this->url($noLanding, '/admin/login'))->assertOk();
        $this->assertDatabaseMissing('network_entitlements', ['code' => 'B2C']);
    }

    public function test_tenant_quote_returns_only_its_published_service_and_creates_owned_snapshot_without_usage(): void
    {
        $tenant = $this->tenant('quote', $this->storefrontEntitlements());
        $foreign = $this->tenant('foreign-quote', $this->storefrontEntitlements());
        $this->tenantService($tenant, published: true, amount: '149.50');
        $this->tenantService($foreign, published: true, amount: '999.00');
        $this->mock(ZigoProviderRateService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('getOptionsForCotizacion'));

        $this->post($this->url($tenant, '/cotizar'), $this->quote())->assertOk()
            ->assertSee('Entrega tenant')->assertSee('149.50')
            ->assertDontSee('999.00')->assertDontSee('FLAT')->assertDontSee('base_cost')->assertDontSee('matched_tariff');
        $operation = TenantOperation::firstOrFail();
        $snapshot = LocalShippingQuoteSnapshot::firstOrFail();
        $this->assertSame($tenant->id, $operation->tenant_id);
        $this->assertSame($tenant->id, $snapshot->tenant_id);
        $this->assertSame('149.50', (string) $snapshot->amount);
        $this->assertSame('quoted', $operation->status);
        $this->assertDatabaseCount('network_usage_events', 0);
        $this->assertSame('64000', $snapshot->origin['postal_code']);
        $this->assertSame('57300', $snapshot->destination['postal_code']);
    }

    public function test_tenant_quote_without_coverage_preserves_input_rolls_back_and_does_not_request_distance(): void
    {
        $tenant = $this->tenant('no-coverage', $this->storefrontEntitlements());
        $this->tenantService($tenant, published: true, amount: '149.50', coveredDestination: false, strategy: 'DISTANCE_TIERS_OVERAGE');
        $this->mock(RouteDistanceProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('distance'));
        $payload = $this->quote(['tipo_envio' => 'caja', 'peso' => 2.5, 'length' => 20, 'width' => 15, 'height' => 10]);

        $this->from($this->url($tenant, '/cotizar'))->post($this->url($tenant, '/cotizar'), $payload)
            ->assertRedirect($this->url($tenant, '/cotizar'))
            ->assertSessionHasErrors('quote')
            ->assertSessionHasInput('cp_origen', '64000')
            ->assertSessionHasInput('origin_settlement', 'Centro')
            ->assertSessionHasInput('origin_address', 'Avenida Juárez 123')
            ->assertSessionHasInput('cp_destino', '57300')
            ->assertSessionHasInput('destination_settlement', 'Benito Juárez')
            ->assertSessionHasInput('destination_address', 'Avenida Pantitlán 456')
            ->assertSessionHasInput('tipo_envio', 'caja')
            ->assertSessionHasInput('peso', 2.5)
            ->assertSessionHasInput('length', 20)
            ->assertSessionHasInput('width', 15)
            ->assertSessionHasInput('height', 10);

        $this->assertDatabaseCount('local_shipping_quote_snapshots', 0);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
    }

    public function test_unpublished_tenant_service_returns_controlled_error_without_national_fallback_or_artifacts(): void
    {
        $tenant = $this->tenant('unpublished', $this->storefrontEntitlements());
        $this->tenantService($tenant, published: false, amount: '149.50');
        $this->mock(ZigoProviderRateService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('getOptionsForCotizacion'));

        $this->from($this->url($tenant, '/cotizar'))->post($this->url($tenant, '/cotizar'), $this->quote())
            ->assertRedirect($this->url($tenant, '/cotizar'))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('local_shipping_quote_snapshots', 0);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
    }

    public function test_confirm_is_tenant_scoped_and_usage_is_idempotent(): void
    {
        $a = $this->tenant('tenant-a', $this->storefrontEntitlements());
        $b = $this->tenant('tenant-b', $this->storefrontEntitlements());
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
        $a = $this->tenant('admin-a', $this->storefrontEntitlements());
        $b = $this->tenant('admin-b', $this->storefrontEntitlements());
        TenantOperation::create(['tenant_id' => $a->id, 'subscription_id' => $a->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted']);
        $foreign = TenantOperation::create(['tenant_id' => $b->id, 'subscription_id' => $b->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted']);
        $user = $this->user();
        $a->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->get($this->url($a, '/admin/operations'))->assertOk()->assertDontSee($foreign->uuid);
        $this->get($this->url($a, '/admin/operations/'.$foreign->uuid))->assertNotFound();
        $this->post($this->url($a, '/admin/operations/'.$foreign->uuid.'/confirm'))->assertNotFound();
        $this->get($this->url($a, '/postal-code/lookup/64000'))->assertOk()->assertJsonPath('codigo_postal', '64000');
        $this->get($this->url($a, '/b2c/cp/colonias?cp=64000'))->assertOk()->assertJsonPath('data.0.d_codigo', '64000');
        $this->assertNotNull(app('router')->getRoutes()->getByName('home'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('b2c.cotizar'));
    }

    public function test_operation_shipment_guide_and_tracking_are_tenant_isolated_and_public_tracking_is_private(): void
    {
        $a = $this->tenant('private-a', $this->storefrontEntitlements());
        $b = $this->tenant('private-b', $this->storefrontEntitlements());
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

    private function quote(array $overrides = []): array
    {
        return array_replace([
            'cp_origen' => '64000',
            'origin_settlement' => 'Centro',
            'origin_address' => 'Avenida Juárez 123',
            'cp_destino' => '57300',
            'destination_settlement' => 'Benito Juárez',
            'destination_address' => 'Avenida Pantitlán 456',
            'tipo_envio' => 'sobre',
            'peso' => 1,
        ], $overrides);
    }

    private function postalFixtures(): void
    {
        DB::table('zigo_postal_codes')->insert([
            ['codigo_postal' => '64000', 'asentamiento' => 'Centro', 'tipo_asentamiento' => 'Colonia', 'municipio' => 'Monterrey', 'estado' => 'Nuevo León', 'ciudad' => 'Monterrey', 'activo' => true],
            ['codigo_postal' => '57300', 'asentamiento' => 'Benito Juárez', 'tipo_asentamiento' => 'Colonia', 'municipio' => 'Nezahualcóyotl', 'estado' => 'México', 'ciudad' => 'Nezahualcóyotl', 'activo' => true],
        ]);
    }

    private function tenantService(Tenant $tenant, bool $published, string $amount, bool $coveredDestination = true, string $strategy = 'FLAT'): LocalShippingService
    {
        $zone = LocalShippingZone::create(['tenant_id' => $tenant->id, 'code' => 'pool', 'name' => 'Cobertura', 'coverage_mode' => 'POSTAL_POOL', 'status' => 'active']);
        foreach (array_filter(['64000', $coveredDestination ? '57300' : null]) as $postalCode) {
            LocalShippingZonePostalCode::create(['tenant_id' => $tenant->id, 'zone_id' => $zone->id, 'postal_code' => $postalCode, 'active' => true]);
        }
        $service = LocalShippingService::create(['tenant_id' => $tenant->id, 'code' => 'tenant-delivery', 'name' => 'Entrega tenant', 'origin_zone_id' => $zone->id, 'destination_zone_id' => $zone->id, 'service_level' => 'same_day', 'base_cost' => '80.00', 'base_price' => '100.00', 'currency' => 'MXN', 'status' => 'active', 'published' => $published, 'pricing_strategy' => $strategy, 'sort_order' => 1, 'sla_text' => 'Mismo día']);
        LocalShippingPricingRule::create(['tenant_id' => $tenant->id, 'service_id' => $service->id, 'from_km' => $strategy === 'DISTANCE_TIERS_OVERAGE' ? '0' : null, 'to_km' => $strategy === 'DISTANCE_TIERS_OVERAGE' ? '25' : null, 'amount' => $amount, 'active' => true]);
        foreach ([['sobre', '1', null, null, null], ['caja', '40', '60', '50', '40']] as $package) {
            LocalShippingPackageRule::create(['tenant_id' => $tenant->id, 'service_id' => $service->id, 'package_type' => $package[0], 'max_weight_kg' => $package[1], 'max_dimension_1_cm' => $package[2], 'max_dimension_2_cm' => $package[3], 'max_dimension_3_cm' => $package[4], 'active' => true]);
        }
        return $service;
    }

    private function storefrontEntitlements(): array
    {
        return ['CUSTOMERS', 'QUOTES', 'SHIPPING', 'TRACKING', 'WHITE_LABEL'];
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
        Schema::create('network_tenant_brandings', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id')->unique(), $t->string('brand_name')->nullable(), $t->string('logo_path')->nullable(), $t->string('hero_image_path')->nullable(), $t->string('primary_color')->nullable(), $t->string('secondary_color')->nullable(), $t->string('accent_color')->nullable(), $t->string('favicon_path')->nullable(), $t->string('support_email')->nullable(), $t->string('support_phone')->nullable(), $t->timestamps()]));
        Schema::create('network_tenant_memberships', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('user_id'), $t->string('role'), $t->string('status'), $t->timestamps()]));
        Schema::create('network_subscriptions', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->unsignedInteger('operations_limit')->nullable(), $t->timestamp('started_at'), $t->timestamp('current_period_start'), $t->timestamp('current_period_end'), $t->timestamp('trial_ends_at')->nullable(), $t->timestamp('grace_ends_at')->nullable(), $t->timestamp('canceled_at')->nullable(), $t->timestamp('ended_at')->nullable(), $t->timestamps()]));
        Schema::create('network_entitlements', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('subscription_id'), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('module_id'), $t->string('code'), $t->boolean('is_enabled'), $t->unsignedInteger('limit_value')->nullable(), $t->string('source'), $t->timestamps()]));
        Schema::create('network_usage_events', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->string('metric'), $t->unsignedInteger('quantity'), $t->string('idempotency_key')->nullable(), $t->timestamp('occurred_at'), $t->json('metadata')->nullable(), $t->timestamp('created_at')->nullable(), $t->unique(['tenant_id', 'metric', 'idempotency_key'])]));
        Schema::create('network_tenant_operations', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->string('channel'), $t->string('status'), $t->string('source_type')->nullable(), $t->unsignedBigInteger('source_id')->nullable(), $t->string('provider')->nullable(), $t->string('service_code')->nullable(), $t->string('external_reference')->nullable(), $t->unsignedBigInteger('created_by_user_id')->nullable(), $t->json('metadata')->nullable(), $t->timestamps()]));
        Schema::create('b2c_cotizaciones', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('user_id')->nullable(), $t->string('cp_origen'), $t->string('cp_destino'), $t->string('tipo_envio'), $t->decimal('peso'), $t->decimal('peso_real')->nullable(), $t->decimal('peso_facturable')->nullable(), $t->string('medidas')->nullable(), $t->string('estatus'), $t->string('referencia')->nullable(), $t->timestamps()]));
        Schema::create('zigo_postal_codes', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('codigo_postal'), $t->string('asentamiento'), $t->string('tipo_asentamiento')->nullable(), $t->string('municipio'), $t->string('estado'), $t->string('ciudad')->nullable(), $t->boolean('activo')]));
        $migration = require database_path('migrations/2026_08_09_100000_create_local_shipping_foundation_tables.php');
        $migration->up();
        $logistics = require database_path('migrations/2026_08_20_100000_extend_local_shipping_for_tenant_logistics.php');
        $logistics->up();
    }
}
