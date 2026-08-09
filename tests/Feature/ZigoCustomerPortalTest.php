<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerProfile, TenantOperation};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Tests\TestCase;

final class ZigoCustomerPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') $this->markTestSkipped('Requires SQLite :memory:.');
        $this->schema();
    }

    public function test_tenant_root_is_branded_and_unknown_or_platform_hosts_are_not_hijacked(): void
    {
        $tenant = $this->tenant('pilot');
        $tenant->branding()->create(['brand_name' => 'RapidGo Local', 'primary_color' => '#2457D6']);
        $this->get($this->url($tenant, '/'))->assertOk()->assertSee('Envía fácil con RapidGo Local')->assertSee('Powered by ZIGO Platform');
        $this->get('http://unknown.zigo.local/')->assertNotFound();
        $this->get('http://'.config('zigo_driver.host').'/')->assertNotFound();
    }

    public function test_registration_reuses_global_user_and_creates_no_membership(): void
    {
        $tenant = $this->tenant('register');
        $user = User::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => Hash::make('Password!123'), 'empresa_id' => 1]);
        $payload = ['name' => 'Ana Cliente', 'email' => 'ana@example.test', 'password' => 'Password!123', 'password_confirmation' => 'Password!123', 'terms' => '1'];
        $this->post($this->url($tenant, '/registro'), $payload)->assertRedirect('/app');
        $this->assertSame(1, User::where('email', 'ana@example.test')->count());
        $this->assertDatabaseHas('tenant_customer_profiles', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseMissing('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    }

    public function test_login_requires_profile_for_current_tenant_and_cross_tenant_is_blocked(): void
    {
        $a = $this->tenant('customer-a'); $b = $this->tenant('customer-b');
        $user = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => Hash::make('secret-pass'), 'empresa_id' => 1]);
        TenantCustomerProfile::create(['tenant_id' => $a->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->post($this->url($b, '/login'), ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post($this->url($a, '/login'), ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect('/app');
        $this->get($this->url($b, '/app'))->assertForbidden();
        $this->get($this->url($a, '/admin'))->assertRedirect();
        $this->get($this->url($a, '/driver'))->assertRedirect('/driver/login');
        $this->get($this->url($a, '/network'))->assertRedirect('/network/login');
    }

    public function test_customer_dashboard_and_shipments_only_show_owned_records(): void
    {
        $tenant = $this->tenant('ownership');
        $owner = $this->customer($tenant, 'owner@example.test'); $other = $this->customer($tenant, 'other@example.test');
        $own = $this->shipment($tenant, $owner, 'ZLCUSTOMEROWN001', 'OUT_FOR_DELIVERY');
        $foreign = $this->shipment($tenant, $other, 'ZLCUSTOMEROTHER1', 'DELIVERED');
        $this->actingAs($owner->user)->get($this->url($tenant, '/app'))->assertOk()->assertSee($own->tracking_number)->assertDontSee($foreign->tracking_number)->assertSee('En reparto')->assertDontSee('OUT_FOR_DELIVERY');
        $this->get($this->url($tenant, '/app/envios'))->assertOk()->assertSee($own->tracking_number)->assertDontSee($foreign->tracking_number);
        $this->get($this->url($tenant, '/app/envios/'.$foreign->uuid))->assertNotFound();
    }

    public function test_public_tracking_is_private_and_spanish_route_matches_legacy(): void
    {
        $tenant = $this->tenant('tracking'); $profile = $this->customer($tenant, 'track@example.test');
        $shipment = $this->shipment($tenant, $profile, 'ZLPRIVACYTRACK01', 'OUT_FOR_DELIVERY');
        $shipment->events()->create(['status' => 'OUT_FOR_DELIVERY', 'event_code' => 'PRIVATE', 'description' => 'Private internal note', 'occurred_at' => now()]);
        foreach (['/tracking/', '/rastreo/'] as $prefix) {
            $response = $this->get($this->url($tenant, $prefix.$shipment->tracking_number))->assertOk()->assertSee('En reparto');
            foreach (['Secret address', '8112345678', 'Private internal note', 'provider_cost'] as $private) $response->assertDontSee($private);
        }
    }

    public function test_service_selection_is_session_bound_and_inactive_proof_is_hidden(): void
    {
        $tenant = $this->tenant('selection'); $profile = $this->customer($tenant, 'select@example.test');
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'quoted', 'metadata' => ['final_price' => 100]]);
        $session = ['tenant_customer.quote_result' => ['tenant_id' => $tenant->id, 'operation_uuid' => $operation->uuid, 'options' => [['provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL', 'service' => 'Mismo día', 'price' => 120]]]];
        $this->actingAs($profile->user)->withSession($session)->post($this->url($tenant, '/app/cotizar/seleccionar'), ['operation_uuid' => $operation->uuid, 'option' => 0])->assertRedirect('/app');
        $this->assertSame($profile->id, $operation->fresh()->customer_profile_id);
        $this->assertSame('LOCAL', $operation->fresh()->service_code);
        $this->assertDatabaseCount('network_usage_events', 0);
        DB::table('tenant_delivery_proof_options')->insert([
            ['uuid' => '10000000-0000-4000-8000-000000000001', 'tenant_id' => $tenant->id, 'code' => 'ACTIVE', 'name' => 'Firma al recibir', 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 3, 'currency' => 'MXN', 'is_active' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => '10000000-0000-4000-8000-000000000002', 'tenant_id' => $tenant->id, 'code' => 'INACTIVE', 'name' => 'No ofrecer', 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 3, 'currency' => 'MXN', 'is_active' => 0, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->get($this->url($tenant, '/app/evidencia'))->assertOk()->assertSee('Firma al recibir')->assertDontSee('No ofrecer');
    }

    public function test_customer_logout_invalidates_session(): void
    {
        $tenant = $this->tenant('logout'); $profile = $this->customer($tenant, 'logout@example.test');
        $this->actingAs($profile->user)->post($this->url($tenant, '/logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    private function customer(Tenant $tenant, string $email): TenantCustomerProfile
    {
        $user = User::create(['name' => 'Customer', 'email' => $email, 'password' => Hash::make('secret-pass'), 'empresa_id' => 1]);
        return TenantCustomerProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active', 'display_name' => 'Customer']);
    }

    private function shipment(Tenant $tenant, TenantCustomerProfile $profile, string $tracking, string $status): LocalShipment
    {
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'customer_profile_id' => $profile->id, 'channel' => 'b2c', 'status' => 'confirmed', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL', 'metadata' => []]);
        $shipment = LocalShipment::create(['tenant_id' => $tenant->id, 'tenant_operation_id' => $operation->id, 'tracking_number' => $tracking, 'service_code' => 'LOCAL', 'status' => $status, 'sender_snapshot' => ['name' => 'Sender', 'address' => 'Secret address', 'postal_code' => '64000', 'phone' => '8112345678'], 'recipient_snapshot' => ['name' => 'Recipient', 'address' => 'Secret address', 'postal_code' => '64000'], 'package_snapshot' => ['type' => 'caja', 'weight' => 1], 'pricing_snapshot' => ['final_price' => 120, 'provider_cost' => 80], 'guide_snapshot' => []]);
        $shipment->events()->create(['status' => $status, 'event_code' => 'STATUS', 'occurred_at' => now()]);
        return $shipment;
    }

    private function tenant(string $slug): Tenant
    {
        $plan = Plan::create(['code' => strtoupper($slug), 'name' => $slug, 'status' => 'active', 'currency' => 'MXN']);
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $tenant->domains()->create(['domain' => $slug.'.zigo.local', 'type' => 'subdomain', 'environment' => 'local', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now(), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        foreach (['B2C','SHIPPING','TRACKING'] as $code) { $module = Module::create(['code' => $code.$tenant->id, 'name' => $code, 'type' => 'addon', 'is_active' => true, 'sort_order' => 1]); Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']); }
        return $tenant;
    }

    private function url(Tenant $tenant, string $path): string { return 'http://'.$tenant->primaryDomain()->value('domain').$path; }

    private function schema(): void
    {
        Schema::create('users', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('name'),$t->string('email')->unique(),$t->string('password'),$t->unsignedBigInteger('empresa_id'),$t->rememberToken(),$t->timestamps()]));
        Schema::create('network_plans', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('code'),$t->string('name'),$t->string('status'),$t->string('currency'),$t->unsignedInteger('included_operations')->nullable(),$t->timestamps()]));
        Schema::create('network_modules', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('type'),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]));
        Schema::create('network_tenants', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->string('name'),$t->string('slug')->unique(),$t->string('status'),$t->unsignedBigInteger('current_plan_id')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_domains', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('domain')->unique(),$t->string('type'),$t->string('environment'),$t->boolean('is_primary'),$t->string('status'),$t->timestamp('verified_at')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_brandings', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id')->unique(),$t->string('brand_name')->nullable(),$t->string('logo_path')->nullable(),$t->string('primary_color')->nullable(),$t->string('secondary_color')->nullable(),$t->string('accent_color')->nullable(),$t->string('favicon_path')->nullable(),$t->string('support_email')->nullable(),$t->string('support_phone')->nullable(),$t->timestamps()]));
        Schema::create('network_tenant_memberships', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('role'),$t->string('status'),$t->timestamps()]));
        Schema::create('network_subscriptions', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('plan_id'),$t->string('status'),$t->unsignedInteger('operations_limit')->nullable(),$t->timestamp('started_at'),$t->timestamp('current_period_start'),$t->timestamp('current_period_end'),$t->timestamp('trial_ends_at')->nullable(),$t->timestamp('grace_ends_at')->nullable(),$t->timestamp('canceled_at')->nullable(),$t->timestamp('ended_at')->nullable(),$t->timestamps()]));
        Schema::create('network_entitlements', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('module_id'),$t->string('code'),$t->boolean('is_enabled'),$t->unsignedInteger('limit_value')->nullable(),$t->string('source'),$t->timestamps()]));
        Schema::create('network_usage_events', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->nullable(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('metric'),$t->unsignedInteger('quantity'),$t->string('idempotency_key')->nullable(),$t->timestamp('occurred_at'),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]));
        Schema::create('tenant_customer_profiles', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('status'),$t->string('display_name')->nullable(),$t->string('phone')->nullable(),$t->timestamps(),$t->unique(['tenant_id','user_id'])]));
        Schema::create('network_tenant_operations', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('channel'),$t->string('status'),$t->string('source_type')->nullable(),$t->unsignedBigInteger('source_id')->nullable(),$t->string('provider')->nullable(),$t->string('service_code')->nullable(),$t->string('external_reference')->nullable(),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->unsignedBigInteger('customer_profile_id')->nullable(),$t->json('metadata')->nullable(),$t->timestamps()]));
        Schema::create('local_shipments', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('tenant_operation_id'),$t->string('tracking_number')->unique(),$t->string('service_code'),$t->string('status'),$t->json('sender_snapshot'),$t->json('recipient_snapshot'),$t->json('package_snapshot'),$t->json('pricing_snapshot'),$t->json('guide_snapshot'),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->timestamps()]));
        Schema::create('local_tracking_events', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->unsignedBigInteger('local_shipment_id'),$t->string('status'),$t->string('event_code'),$t->text('description')->nullable(),$t->timestamp('occurred_at'),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]));
        Schema::create('local_shipment_delivery_requirements', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid')->nullable(),$t->unsignedBigInteger('local_shipment_id'),$t->string('option_name')->nullable(),$t->boolean('require_signature')->default(false),$t->boolean('require_photo')->default(false),$t->boolean('require_gps')->default(false),$t->timestamps()]));
        Schema::create('tenant_delivery_proof_options', fn(Blueprint $t) => tap($t, fn($t) => [$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id'),$t->string('code'),$t->string('name'),$t->text('description')->nullable(),$t->boolean('require_receiver_name')->default(false),$t->boolean('require_receiver_type')->default(false),$t->boolean('require_signature')->default(false),$t->boolean('require_photo')->default(false),$t->boolean('require_gps')->default(false),$t->string('receiver_policy'),$t->unsignedSmallInteger('max_delivery_attempts'),$t->decimal('surcharge_amount',12,2)->default(0),$t->string('currency'),$t->boolean('is_default')->default(false),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]));
    }
}
