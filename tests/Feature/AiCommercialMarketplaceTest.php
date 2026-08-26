<?php

namespace Tests\Feature;

use App\Domain\AI\Commerce\AiCommercialActivationService;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\{NetworkCommercialProduct, PlatformPaymentAttempt, PlatformPaymentEvent, TenantSaasOrder};
use App\Domain\Network\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Tests\TestCase;

final class AiCommercialMarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['ai.enabled' => true, 'zigo_payments.platform.account_id' => 'stage-account']);
        $this->schema();
    }

    public function test_ai_base_preserves_logistics_subscription_and_applies_capacity_once(): void
    {
        [$tenant, $subscription, $entitlement] = $this->tenantWithLogistics();
        $aiModule = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 2]);
        $product = NetworkCommercialProduct::create(['code' => 'AI-BASE', 'name' => 'Agentes IA', 'description' => 'Stage', 'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '299.00', 'currency' => 'MXN', 'module_id' => $aiModule->id, 'is_active' => true, 'metadata' => ['ai_product' => true, 'ai_kind' => 'base', 'ai_capacities' => [AiCapacityService::MAX_AGENTS => 1, AiCapacityService::MAX_WEBCHAT_CHANNELS => 1, AiCapacityService::MAX_WHATSAPP_CHANNELS => 0, AiCapacityService::MONTHLY_RUNTIME_UNITS => 1000, AiCapacityService::MONTHLY_ACTION_RUNS => 100, AiCapacityService::MONTHLY_CONVERSATIONS => 250]]]);
        $order = $this->order($tenant, $product, ['ai_product' => true, 'ai_kind' => 'base', 'ai_capacities' => $product->metadata['ai_capacities']]);
        app(AiCommercialActivationService::class)->apply($order, $subscription);
        app(AiCommercialActivationService::class)->apply($order, $subscription);
        $this->assertSame($subscription->id, $tenant->fresh()->subscriptions()->first()->id);
        $this->assertSame(1, Entitlement::where('tenant_id', $tenant->id)->where('code', 'AI_CORE')->count());
        $capacity = app(AiCapacityService::class);
        $this->assertSame(1, $capacity->limit($tenant->fresh(), AiCapacityService::MAX_AGENTS));
        $this->assertSame(1, $capacity->limit($tenant->fresh(), AiCapacityService::MAX_WEBCHAT_CHANNELS));
    }

    public function test_ai_addons_accumulate_and_replay_same_order_does_not_duplicate(): void
    {
        [$tenant, $subscription, $entitlement] = $this->tenantWithAi();
        $product = NetworkCommercialProduct::create(['code' => 'AI-AGENT-ADDON', 'name' => 'Agente adicional', 'description' => 'Stage', 'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '99.00', 'currency' => 'MXN', 'module_id' => $entitlement->module_id, 'is_active' => true, 'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]]]);
        $activation = app(AiCommercialActivationService::class);
        $first = $this->order($tenant, $product, ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]]);
        $activation->apply($first, $subscription);
        $activation->apply($first, $subscription);
        $second = $this->order($tenant, $product, ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]], 'second');
        $activation->apply($second, $subscription);
        $this->assertSame(3, app(AiCapacityService::class)->limit($tenant->fresh(), AiCapacityService::MAX_AGENTS));
        $this->assertSame(2, DB::table('network_entitlement_capacities')->where('source', 'addon')->count());
    }

    public function test_manual_ai_entitlement_remains_distinguishable_without_invented_purchase(): void
    {
        [$tenant] = $this->tenantWithAi('manual');
        $this->assertSame('manual', Entitlement::where('tenant_id', $tenant->id)->where('code', 'AI_CORE')->value('source'));
        $this->assertSame(0, TenantSaasOrder::where('tenant_id', $tenant->id)->count());
    }

    public function test_addon_requires_ai_core_and_subscription_tenant_matches_order(): void
    {
        [$tenant, $subscription] = $this->tenantWithLogistics();
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 2]);
        $product = NetworkCommercialProduct::create(['code' => 'AI-ADDON', 'name' => 'Agente adicional', 'description' => 'Stage', 'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '99.00', 'currency' => 'MXN', 'module_id' => $module->id, 'is_active' => true, 'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]]]);
        $order = $this->order($tenant, $product, $product->metadata);
        $this->expectException(\RuntimeException::class);
        app(AiCommercialActivationService::class)->apply($order, $subscription);
    }

    public function test_capacity_application_rejects_a_subscription_from_another_tenant(): void
    {
        [$tenantA, $subscriptionA] = $this->tenantWithAi();
        [$tenantB, $subscriptionB] = $this->tenantWithAi('manual');
        $module = Module::where('code', 'AI_CORE')->firstOrFail();
        $product = NetworkCommercialProduct::create(['code' => 'AI-CROSS', 'name' => 'Agente adicional', 'description' => 'Stage', 'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '99.00', 'currency' => 'MXN', 'module_id' => $module->id, 'is_active' => true, 'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]]]);
        $order = $this->order($tenantA, $product, $product->metadata, 'cross');
        $this->expectException(\RuntimeException::class);
        app(AiCommercialActivationService::class)->apply($order, $subscriptionB);
    }

    public function test_stage_platform_payment_approves_ai_order_once_through_command(): void
    {
        [$tenant, $subscription, $entitlement] = $this->tenantWithAi();
        $product = NetworkCommercialProduct::create([
            'code' => 'AI-AGENT-STAGE', 'name' => 'Agente adicional', 'description' => 'Stage',
            'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '99.00', 'currency' => 'MXN',
            'module_id' => $entitlement->module_id, 'is_active' => true,
            'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]],
        ]);
        $order = $this->order($tenant, $product, $product->metadata);
        $order->update(['status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING']);
        $attempt = PlatformPaymentAttempt::create([
            'tenant_id' => $tenant->id, 'saas_order_id' => $order->id, 'provider' => 'MERCADO_PAGO',
            'status' => 'PENDING', 'provider_preference_id' => 'pref-stage',
            'external_reference' => 'stage-'.$order->uuid, 'amount' => $order->total_amount, 'currency' => 'MXN',
        ]);

        $this->artisan('zigo:stage-platform-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])
            ->assertExitCode(0);

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('ACTIVATED', $order->fresh()->status);
        $this->assertSame(2, app(AiCapacityService::class)->limit($tenant->fresh(), AiCapacityService::MAX_AGENTS));
        $this->assertSame(1, Entitlement::where('tenant_id', $tenant->id)->where('code', 'AI_CORE')->count());
        $this->assertSame(1, PlatformPaymentEvent::count());

        $this->artisan('zigo:stage-platform-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])
            ->assertExitCode(0);
        $this->assertSame(2, app(AiCapacityService::class)->limit($tenant->fresh(), AiCapacityService::MAX_AGENTS));
        $this->assertSame(1, PlatformPaymentEvent::count());
    }

    public function test_stage_platform_payment_is_blocked_in_production_without_changes(): void
    {
        [$tenant, $subscription, $entitlement] = $this->tenantWithAi();
        $product = NetworkCommercialProduct::create([
            'code' => 'AI-PROD-GUARD', 'name' => 'Agente adicional', 'description' => 'Stage',
            'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '99.00', 'currency' => 'MXN',
            'module_id' => $entitlement->module_id, 'is_active' => true,
            'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]],
        ]);
        $order = $this->order($tenant, $product, $product->metadata);
        $order->update(['status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING']);
        $attempt = PlatformPaymentAttempt::create([
            'tenant_id' => $tenant->id, 'saas_order_id' => $order->id, 'provider' => 'MERCADO_PAGO',
            'status' => 'PENDING', 'provider_preference_id' => 'pref-production',
            'external_reference' => 'production-'.$order->uuid, 'amount' => $order->total_amount, 'currency' => 'MXN',
        ]);
        $previousEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->artisan('zigo:stage-platform-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])
                ->assertExitCode(1);
        } finally {
            app()->detectEnvironment(fn () => $previousEnvironment);
        }
        $this->assertSame('PENDING', $attempt->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', $order->fresh()->status);
        $this->assertSame(0, PlatformPaymentEvent::count());
    }

    public function test_public_ai_routes_render_the_catalog_offer_without_internal_branding(): void
    {
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 2]);
        $product = NetworkCommercialProduct::create([
            'code' => 'PUBLIC-AI-BASE', 'name' => 'Agentes IA', 'description' => 'Atención automatizada',
            'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '299.00', 'currency' => 'MXN',
            'module_id' => $module->id, 'is_active' => true, 'metadata' => [
                'ai_product' => true, 'ai_kind' => 'base', 'ai_capacities' => [AiCapacityService::MAX_AGENTS => 1],
            ],
        ]);
        config(['zigo_surfaces.corporate.host' => 'zigo.local']);
        $server = ['HTTP_HOST' => 'zigo.local'];

        $this->call('GET', '/agentes-ia', [], [], [], $server)->assertOk()->assertSee('Agentes IA')->assertDontSee('INNOTECH');
        $this->call('GET', '/agentes-ia/precios', [], [], [], $server)->assertOk()->assertSee($product->name)->assertSee('299.00');
        $this->call('GET', '/agentes-ia/comenzar', [], [], [], $server)->assertOk()->assertSee('Comienza con Agentes IA');
    }

    private function tenantWithLogistics(): array
    {
        $tenant = Tenant::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Tenant', 'slug' => 'tenant', 'status' => 'active']);
        $plan = Plan::create(['code' => 'LOGISTICS', 'name' => 'ZIGO Esencial', 'status' => 'active', 'currency' => 'MXN']);
        $module = Module::create(['code' => 'SHIPPING', 'name' => 'Shipping', 'type' => 'core', 'is_active' => true, 'sort_order' => 1]);
        $subscription = Subscription::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => 'SHIPPING', 'is_enabled' => true, 'source' => 'plan']);
        return [$tenant, $subscription, Entitlement::where('subscription_id', $subscription->id)->first()];
    }

    private function tenantWithAi(string $source = 'commercial'): array
    {
        [$tenant, $subscription, $shipping] = $this->tenantWithLogistics();
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 2]);
        $entitlement = Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => 'AI_CORE', 'is_enabled' => true, 'source' => $source]);
        DB::table('network_entitlement_capacities')->insert(['tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'entitlement_id' => $entitlement->id, 'capability_code' => AiCapacityService::MAX_AGENTS, 'quantity' => 1, 'source' => 'override', 'source_key' => 'override', 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        return [$tenant, $subscription, $entitlement];
    }

    private function order(Tenant $tenant, NetworkCommercialProduct $product, array $metadata, string $suffix = 'first'): TenantSaasOrder
    {
        return TenantSaasOrder::create(['tenant_id' => $tenant->id, 'commercial_product_id' => $product->id, 'created_by_user_id' => 1, 'purchase_key' => 'purchase-'.$suffix, 'status' => 'PAID', 'payment_status' => 'APPROVED', 'quantity' => 1, 'unit_amount' => $product->price, 'subtotal' => $product->price, 'tax_amount' => 0, 'total_amount' => $product->price, 'currency' => 'MXN', 'purchase_snapshot' => ['type' => $product->type, 'billing_type' => $product->billing_type, 'metadata' => $metadata], 'expires_at' => now()->addHour()]);
    }

    private function schema(): void
    {
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('email')->nullable(), $t->string('password')->nullable(), $t->unsignedBigInteger('empresa_id')->nullable(), $t->timestamps()]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('network_tenants', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid'), $t->string('name'), $t->string('slug'), $t->string('status'), $t->unsignedBigInteger('current_plan_id')->nullable(), $t->timestamps()]);
        Schema::create('network_plans', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name'), $t->string('status'), $t->char('currency', 3), $t->timestamps()]);
        Schema::create('network_modules', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('name'), $t->string('type'), $t->boolean('is_active'), $t->unsignedInteger('sort_order'), $t->timestamps()]);
        Schema::create('network_subscriptions', fn (Blueprint $t) => [$t->id(), $t->uuid('uuid'), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->timestamp('started_at'), $t->timestamp('current_period_start'), $t->timestamp('current_period_end'), $t->timestamps()]);
        Schema::create('network_entitlements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('subscription_id'), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('module_id'), $t->string('code'), $t->boolean('is_enabled'), $t->string('source'), $t->timestamps()]);
        Schema::create('network_entitlement_capacities', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('entitlement_id');$t->string('capability_code');$t->unsignedInteger('quantity');$t->string('source');$t->string('source_key');$t->boolean('is_enabled')->default(true);$t->timestamps();$t->unique(['entitlement_id','capability_code','source','source_key']);});
        Schema::create('network_commercial_products', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->string('billing_type');$t->decimal('price',12,2);$t->char('currency',3);$t->unsignedBigInteger('module_id')->nullable();$t->unsignedBigInteger('plan_id')->nullable();$t->unsignedInteger('included_operations')->nullable();$t->boolean('is_active');$t->unsignedInteger('sort_order')->default(0);$t->json('metadata')->nullable();$t->timestamps();});
        Schema::create('tenant_saas_orders', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('commercial_product_id');$t->unsignedBigInteger('created_by_user_id');$t->string('purchase_key');$t->string('status');$t->string('payment_status');$t->unsignedInteger('quantity');$t->decimal('unit_amount',12,2);$t->decimal('subtotal',12,2);$t->decimal('tax_amount',12,2);$t->decimal('total_amount',12,2);$t->char('currency',3);$t->json('purchase_snapshot');$t->string('payment_provider')->nullable();$t->string('payment_reference')->nullable();$t->timestamp('paid_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('activated_at')->nullable();$t->timestamps();$t->unique(['tenant_id','purchase_key']);});
        Schema::create('platform_payment_attempts', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id')->nullable();$t->unsignedBigInteger('saas_order_id')->nullable();$t->unsignedBigInteger('onboarding_application_id')->nullable();$t->string('provider');$t->string('status');$t->string('provider_preference_id')->nullable();$t->string('provider_payment_id')->nullable();$t->string('external_reference')->unique();$t->decimal('amount',12,2);$t->char('currency',3);$t->text('init_point')->nullable();$t->timestamp('approved_at')->nullable();$t->timestamp('rejected_at')->nullable();$t->timestamps();$t->unique(['provider','provider_payment_id']);});
        Schema::create('platform_payment_events', function (Blueprint $t): void {$t->id();$t->string('provider');$t->string('event_key');$t->unsignedBigInteger('platform_payment_attempt_id')->nullable();$t->string('provider_payment_id')->nullable();$t->string('status');$t->string('error_code')->nullable();$t->timestamp('received_at');$t->timestamp('processed_at')->nullable();$t->timestamps();$t->unique(['provider','event_key']);});
    }
}
