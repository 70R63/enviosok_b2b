<?php

namespace Tests\Feature;

use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Billing\MercadoPagoRecurringSubscriptionProvider;
use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Billing\RecurringSubscriptionService;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AiRecurringSubscriptionTest extends TestCase
{
    private Tenant $tenant;
    private Subscription $subscription;
    private NetworkCommercialProduct $growth;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'ai.enabled' => true,
            'zigo_payments.platform.access_token' => 'test-token',
            'zigo_payments.providers.mercado_pago.api_url' => 'https://api.mercadopago.com',
        ]);

        $this->createSchema();

        $module = Module::create([
            'code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'ai',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $trial = Plan::create([
            'code' => 'AI_TRIAL', 'name' => 'Trial', 'status' => 'active',
            'monthly_price' => 0, 'currency' => 'MXN',
        ]);
        $growthPlan = Plan::create([
            'code' => 'AI_CRECIMIENTO', 'name' => 'Crecimiento', 'status' => 'active',
            'monthly_price' => 1299, 'annual_price' => 12990, 'currency' => 'MXN',
        ]);
        $growthPlan->modules()->attach($module->id, ['is_included' => true]);

        foreach ([
            ['MAX_AGENTS', 2], ['MAX_WEBCHAT_CHANNELS', 2],
            ['MAX_WHATSAPP_CHANNELS', 1], ['MONTHLY_CONVERSATIONS', 500],
        ] as [$code, $quantity]) {
            DB::table('network_plan_module_capacities')->insert([
                'plan_id' => $growthPlan->id, 'module_id' => $module->id,
                'capability_code' => $code, 'quantity' => $quantity,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->growth = NetworkCommercialProduct::create([
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'code' => 'AI_CRECIMIENTO_MONTHLY', 'name' => 'Crecimiento',
            'type' => 'PLAN', 'billing_type' => 'MONTHLY', 'price' => 1299,
            'currency' => 'MXN', 'plan_id' => $growthPlan->id,
            'is_active' => true, 'is_public' => true,
            'metadata' => [
                'ai_product' => true, 'ai_kind' => 'base', 'trial' => false,
                'provider_plan_id_monthly' => 'mp-plan-growth',
            ],
        ]);

        $this->tenant = Tenant::create([
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Tenant', 'slug' => 'tenant', 'status' => 'active',
        ]);
        $this->subscription = Subscription::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $trial->id,
            'status' => 'trialing', 'started_at' => now(),
            'current_period_start' => now(), 'current_period_end' => now()->addDays(7),
            'trial_ends_at' => now()->addDays(7), 'billing_frequency' => 'TRIAL',
        ]);
        $entitlement = Entitlement::create([
            'subscription_id' => $this->subscription->id, 'tenant_id' => $this->tenant->id,
            'module_id' => $module->id, 'code' => 'AI_CORE', 'is_enabled' => true,
            'source' => 'override',
        ]);
        foreach ([
            ['MAX_AGENTS', 1], ['MAX_WEBCHAT_CHANNELS', 1],
            ['MAX_WHATSAPP_CHANNELS', 0], ['MONTHLY_CONVERSATIONS', 50],
        ] as [$code, $quantity]) {
            DB::table('network_entitlement_capacities')->insert([
                'tenant_id' => $this->tenant->id, 'subscription_id' => $this->subscription->id,
                'entitlement_id' => $entitlement->id, 'capability_code' => $code,
                'quantity' => $quantity, 'source' => 'override', 'source_key' => 'override',
                'is_enabled' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_monthly_plan_payload_uses_one_month(): void
    {
        Http::fake(['https://api.mercadopago.com/preapproval_plan' => Http::response(['id' => 'plan-monthly'], 201)]);
        $response = app(MercadoPagoRecurringSubscriptionProvider::class)->createPlan([
            'reason' => 'Inicial',
            'auto_recurring' => ['frequency' => 1, 'frequency_type' => 'months', 'transaction_amount' => '599.00', 'currency_id' => 'MXN'],
            'back_url' => 'https://zigo.local',
        ]);
        $this->assertSame('plan-monthly', $response['id']);
    }

    public function test_annual_plan_payload_uses_twelve_months(): void
    {
        Http::fake(['https://api.mercadopago.com/preapproval_plan' => Http::response(['id' => 'plan-annual'], 201)]);
        $response = app(MercadoPagoRecurringSubscriptionProvider::class)->createPlan([
            'reason' => 'Pro',
            'auto_recurring' => ['frequency' => 12, 'frequency_type' => 'months', 'transaction_amount' => '24990.00', 'currency_id' => 'MXN'],
            'back_url' => 'https://zigo.local',
        ]);
        $this->assertSame('plan-annual', $response['id']);
    }

    public function test_trial_subscription_becomes_growth_only_after_verified_authorization(): void
    {
        Http::fake([
            'https://api.mercadopago.com/preapproval/mp-sub-growth' => Http::response([
                'id' => 'mp-sub-growth', 'status' => 'authorized',
                'external_reference' => 'ai-sub:'.$this->subscription->uuid,
                'preapproval_plan_id' => 'mp-plan-growth',
                'auto_recurring' => ['transaction_amount' => '1299.00', 'currency_id' => 'MXN'],
                'next_payment_date' => now()->addMonth()->toISOString(),
            ]),
        ]);

        $this->subscription->update([
            'provider_subscription_id' => 'mp-sub-growth',
            'provider_plan_id' => 'mp-plan-growth',
            'billing_frequency' => 'MONTHLY',
        ]);
        $capacity = app(AiCapacityService::class);
        $this->assertSame(1, $capacity->limit($this->tenant, 'MAX_AGENTS', $this->subscription));
        $this->assertSame(1, $capacity->limit($this->tenant, 'MAX_WEBCHAT_CHANNELS', $this->subscription));
        $this->assertSame(0, $capacity->limit($this->tenant, 'MAX_WHATSAPP_CHANNELS', $this->subscription));
        $this->assertSame(50, $capacity->limit($this->tenant, 'MONTHLY_CONVERSATIONS', $this->subscription));

        $reconciled = app(RecurringSubscriptionService::class)->reconcile($this->subscription);

        $this->assertSame($this->subscription->id, $reconciled->id);
        $this->assertSame($this->tenant->id, $reconciled->tenant_id);
        $this->assertSame('active', $reconciled->status);
        $this->assertSame('authorized', $reconciled->provider_status);
        $this->assertSame($this->growth->plan_id, $reconciled->plan_id);
        $this->assertSame('MONTHLY', $reconciled->billing_frequency);
        $this->assertSame(2, $capacity->limit($this->tenant, 'MAX_AGENTS', $reconciled));
        $this->assertSame(2, $capacity->limit($this->tenant, 'MAX_WEBCHAT_CHANNELS', $reconciled));
        $this->assertSame(1, $capacity->limit($this->tenant, 'MAX_WHATSAPP_CHANNELS', $reconciled));
        $this->assertSame(500, $capacity->limit($this->tenant, 'MONTHLY_CONVERSATIONS', $reconciled));
    }

    private function createSchema(): void
    {
        foreach (['network_plan_module_capacities', 'network_entitlement_capacities', 'network_entitlements', 'network_subscriptions', 'network_plan_modules', 'network_commercial_products', 'network_tenants', 'network_plans', 'network_modules'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('network_modules', function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('name'); $table->string('type'); $table->boolean('is_active'); $table->unsignedInteger('sort_order'); $table->timestamps();
        });
        Schema::create('network_plans', function (Blueprint $table): void {
            $table->id(); $table->string('code'); $table->string('name'); $table->string('status'); $table->decimal('monthly_price', 12, 2)->nullable(); $table->decimal('annual_price', 12, 2)->nullable(); $table->char('currency', 3); $table->timestamps();
        });
        Schema::create('network_tenants', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->string('name'); $table->string('slug'); $table->string('status'); $table->timestamps();
        });
        Schema::create('network_plan_modules', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_id'); $table->unsignedBigInteger('module_id'); $table->boolean('is_included'); $table->timestamps(); $table->unique(['plan_id', 'module_id']);
        });
        Schema::create('network_plan_module_capacities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_id'); $table->unsignedBigInteger('module_id'); $table->string('capability_code'); $table->unsignedInteger('quantity'); $table->timestamps();
        });
        Schema::create('network_commercial_products', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->string('code'); $table->string('name'); $table->string('type'); $table->string('billing_type'); $table->decimal('price', 12, 2); $table->char('currency', 3); $table->unsignedBigInteger('plan_id')->nullable(); $table->boolean('is_active'); $table->boolean('is_public'); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('network_subscriptions', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('plan_id'); $table->string('status'); $table->timestamp('started_at')->nullable(); $table->timestamp('current_period_start')->nullable(); $table->timestamp('current_period_end')->nullable(); $table->timestamp('trial_ends_at')->nullable(); $table->string('billing_frequency')->nullable(); $table->string('provider_subscription_id')->nullable(); $table->string('provider_plan_id')->nullable(); $table->string('provider_status')->nullable(); $table->timestamp('next_payment_date')->nullable(); $table->boolean('cancel_at_period_end')->default(false); $table->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('module_id'); $table->string('code'); $table->boolean('is_enabled'); $table->string('source'); $table->timestamps(); $table->unique(['subscription_id', 'module_id']);
        });
        Schema::create('network_entitlement_capacities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('entitlement_id'); $table->string('capability_code'); $table->unsignedInteger('quantity'); $table->string('source'); $table->string('source_key'); $table->boolean('is_enabled')->default(true); $table->timestamps(); $table->unique(['entitlement_id', 'capability_code', 'source', 'source_key']);
        });
    }
}
