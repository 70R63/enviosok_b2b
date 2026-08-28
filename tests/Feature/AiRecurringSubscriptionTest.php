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
use Illuminate\Support\Str;
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
            'zigo_payments.platform.webhook_secret' => 'webhook-secret',
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
                'ai_product' => true, 'ai_kind' => 'base', 'trial' => false, 'ai_tier_rank' => 20,
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

    public function test_valid_subscription_webhook_reconciles_server_side(): void
    {
        Http::fake(['https://api.mercadopago.com/preapproval/mp-sub-growth' => Http::response([
            'id' => 'mp-sub-growth', 'status' => 'authorized',
            'external_reference' => 'ai-sub:'.$this->subscription->uuid,
            'preapproval_plan_id' => 'mp-plan-growth',
            'auto_recurring' => ['transaction_amount' => '1299.00', 'currency_id' => 'MXN'],
        ])]);
        $this->subscription->update(['provider_subscription_id' => 'mp-sub-growth', 'provider_plan_id' => 'mp-plan-growth']);
        $requestId = 'req-sub-1';
        $response = $this->withHeaders($this->signatureHeaders('mp-sub-growth', $requestId))->postJson('/api/payments/mercado-pago/subscriptions/webhook', [
            'type' => 'subscription_preapproval', 'data' => ['id' => 'mp-sub-growth'],
        ]);
        $response->assertOk();
        $this->assertSame('active', $this->subscription->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_invalid_subscription_webhook_signature_is_rejected(): void
    {
        $response = $this->withHeaders(['x-request-id' => 'req-invalid', 'x-signature' => 'ts=1,v1=invalid'])
            ->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'subscription_preapproval', 'data' => ['id' => 'missing']]);
        $response->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_monthly_payment_webhook_renews_once(): void
    {
        $subscription = $this->subscription->update(['status' => 'active', 'billing_frequency' => 'MONTHLY', 'provider_subscription_id' => 'mp-sub-monthly', 'current_period_start' => '2026-08-01 00:00:00', 'current_period_end' => '2026-09-01 00:00:00', 'next_payment_date' => '2026-09-01 00:00:00']);
        $paymentId = 'pay-monthly-001'; $requestId = 'req-payment-1';
        Http::fake(['https://api.mercadopago.com/v1/payments/'.$paymentId => Http::response(['id' => $paymentId, 'status' => 'approved', 'preapproval_id' => 'mp-sub-monthly'])]);
        $payload = ['type' => 'payment', 'data' => ['id' => $paymentId]];
        $this->withHeaders($this->signatureHeaders($paymentId, $requestId))->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $fresh = $this->subscription->fresh();
        $this->assertSame('2026-09-01 00:00:00', $fresh->current_period_start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 00:00:00', $fresh->current_period_end->format('Y-m-d H:i:s'));
        $this->withHeaders($this->signatureHeaders($paymentId, $requestId))->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $this->assertSame('2026-10-01 00:00:00', $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_annual_payment_webhook_adds_twelve_months_once(): void
    {
        $this->subscription->update(['status' => 'active', 'billing_frequency' => 'ANNUAL', 'provider_subscription_id' => 'mp-sub-annual', 'current_period_start' => '2026-01-01 00:00:00', 'current_period_end' => '2027-01-01 00:00:00', 'next_payment_date' => '2027-01-01 00:00:00']);
        $paymentId = 'pay-annual-001'; $requestId = 'req-payment-annual';
        Http::fake(['https://api.mercadopago.com/v1/payments/'.$paymentId => Http::response(['id' => $paymentId, 'status' => 'approved', 'preapproval_id' => 'mp-sub-annual'])]);
        $payload = ['type' => 'payment', 'data' => ['id' => $paymentId]];
        $this->withHeaders($this->signatureHeaders($paymentId, $requestId))->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $this->assertSame('2028-01-01 00:00:00', $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
        $this->withHeaders($this->signatureHeaders($paymentId, $requestId))->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $this->assertSame('2028-01-01 00:00:00', $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_pending_payment_does_not_renew(): void
    {
        $this->subscription->update(['status' => 'active', 'billing_frequency' => 'MONTHLY', 'provider_subscription_id' => 'mp-sub-pending', 'current_period_end' => '2026-09-01 00:00:00']);
        Http::fake(['https://api.mercadopago.com/v1/payments/pay-pending' => Http::response(['id' => 'pay-pending', 'status' => 'pending', 'preapproval_id' => 'mp-sub-pending'])]);
        $this->withHeaders($this->signatureHeaders('pay-pending', 'req-pending'))->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-pending']])->assertOk();
        $this->assertSame('2026-09-01 00:00:00', $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_duplicate_subscription_webhook_is_idempotent(): void
    {
        $this->subscription->update(['provider_subscription_id' => 'mp-sub-growth', 'provider_plan_id' => 'mp-plan-growth']);
        Http::fake(['https://api.mercadopago.com/preapproval/mp-sub-growth' => Http::response([
            'id' => 'mp-sub-growth', 'status' => 'authorized',
            'external_reference' => 'ai-sub:'.$this->subscription->uuid,
            'preapproval_plan_id' => 'mp-plan-growth',
            'auto_recurring' => ['transaction_amount' => '1299.00', 'currency_id' => 'MXN'],
        ])]);
        $payload = ['type' => 'subscription_preapproval', 'data' => ['id' => 'mp-sub-growth']];
        $headers = $this->signatureHeaders('mp-sub-growth', 'req-sub-duplicate');
        $this->withHeaders($headers)->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $this->withHeaders($headers)->postJson('/api/payments/mercado-pago/subscriptions/webhook', $payload)->assertOk();
        $this->assertSame(1, DB::table('platform_payment_events')->count());
        $this->assertSame('active', $this->subscription->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_rejected_payment_does_not_renew(): void
    {
        $this->subscription->update(['status' => 'active', 'billing_frequency' => 'MONTHLY', 'provider_subscription_id' => 'mp-sub-rejected', 'current_period_end' => '2026-09-01 00:00:00']);
        Http::fake(['https://api.mercadopago.com/v1/payments/pay-rejected' => Http::response(['id' => 'pay-rejected', 'status' => 'rejected', 'preapproval_id' => 'mp-sub-rejected'])]);
        $this->withHeaders($this->signatureHeaders('pay-rejected', 'req-rejected'))->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-rejected']])->assertOk();
        $this->assertSame('2026-09-01 00:00:00', $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_payment_for_unknown_subscription_does_not_renew(): void
    {
        $end = $this->subscription->current_period_end->format('Y-m-d H:i:s');
        Http::fake(['https://api.mercadopago.com/v1/payments/pay-unknown' => Http::response(['id' => 'pay-unknown', 'status' => 'approved', 'preapproval_id' => 'mp-sub-unknown'])]);
        $this->withHeaders($this->signatureHeaders('pay-unknown', 'req-unknown'))->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-unknown']])->assertOk();
        $this->assertSame($end, $this->subscription->fresh()->current_period_end->format('Y-m-d H:i:s'));
        Http::assertSentCount(1);
    }

    public function test_rejected_recurring_payment_enters_grace_once(): void
    {
        config(['ai.billing.grace_days' => 3]);
        $this->subscription->update(['status' => 'active', 'provider_subscription_id' => 'mp-sub-failed', 'current_period_end' => now()->addMonth()]);
        Http::fake(['https://api.mercadopago.com/v1/payments/pay-failed' => Http::response(['id' => 'pay-failed', 'status' => 'rejected', 'preapproval_id' => 'mp-sub-failed'])]);
        $this->withHeaders($this->signatureHeaders('pay-failed', 'req-failed'))->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-failed']])->assertOk();
        $grace = $this->subscription->fresh()->grace_ends_at;
        $this->assertSame('grace', $this->subscription->fresh()->status);
        $this->assertNotNull($grace);
        $this->withHeaders($this->signatureHeaders('pay-failed', 'req-failed'))->postJson('/api/payments/mercado-pago/subscriptions/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-failed']])->assertOk();
        $this->assertTrue($grace->equalTo($this->subscription->fresh()->grace_ends_at));
        $this->assertSame(1, DB::table('network_subscription_events')->where('event', 'payment_failed')->count());
    }

    public function test_grace_lifecycle_suspends_after_deadline_without_deleting_data(): void
    {
        $this->subscription->update(['status' => 'grace', 'grace_ends_at' => now()->subSecond()]);
        $this->artisan('ai:process-billing-lifecycle')->assertSuccessful();
        $this->assertSame('suspended', $this->subscription->fresh()->status);
        $this->assertDatabaseHas('network_tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('network_entitlements', ['subscription_id' => $this->subscription->id]);
    }

    public function test_cancel_at_period_end_keeps_active_then_cancels_provider(): void
    {
        $this->subscription->update(['status' => 'active', 'provider_subscription_id' => 'mp-sub-cancel', 'current_period_end' => now()->addDay()]);
        $cancelled = app(RecurringSubscriptionService::class)->cancelAtPeriodEnd($this->subscription);
        $this->assertTrue($cancelled->cancel_at_period_end);
        $this->assertSame('active', $cancelled->status);
        Http::fake(['https://api.mercadopago.com/preapproval/mp-sub-cancel' => Http::response(['id' => 'mp-sub-cancel', 'status' => 'canceled'])]);
        $this->subscription->update(['current_period_end' => now()->subSecond()]);
        $this->artisan('ai:process-billing-lifecycle')->assertSuccessful();
        $this->assertSame('canceled', $this->subscription->fresh()->status);
        $this->assertSame('canceled', $this->subscription->fresh()->provider_status);
        Http::assertSentCount(1);
    }

    public function test_duplicate_cancel_request_is_idempotent_and_keeps_period(): void
    {
        $this->subscription->update(['status' => 'active', 'current_period_end' => now()->addDay()]);
        app(RecurringSubscriptionService::class)->cancelAtPeriodEnd($this->subscription);
        app(RecurringSubscriptionService::class)->cancelAtPeriodEnd($this->subscription);
        $this->assertSame(1, DB::table('network_subscription_events')->where('event', 'cancellation_scheduled')->count());
        $this->assertSame('active', $this->subscription->fresh()->status);
    }

    public function test_provider_cancel_failure_keeps_cancellation_scheduled(): void
    {
        $this->subscription->update(['status' => 'active', 'provider_subscription_id' => 'mp-sub-failure', 'cancel_at_period_end' => true, 'current_period_end' => now()->subSecond()]);
        Http::fake(['https://api.mercadopago.com/preapproval/mp-sub-failure' => Http::response([], 500)]);
        $this->artisan('ai:process-billing-lifecycle')->assertSuccessful();
        $fresh = $this->subscription->fresh();
        $this->assertTrue($fresh->cancel_at_period_end);
        $this->assertSame('active', $fresh->status);
    }

    public function test_canceled_subscription_is_not_renewed_by_late_payment(): void
    {
        $this->subscription->update(['status' => 'canceled', 'billing_frequency' => 'MONTHLY', 'current_period_end' => '2026-09-01 00:00:00']);
        $result = app(RecurringSubscriptionService::class)->renew($this->subscription, 'late-payment');
        $this->assertSame('canceled', $result->status);
        $this->assertSame('2026-09-01 00:00:00', $result->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_upgrade_keeps_old_capacities_until_provider_authorized(): void
    {
        NetworkCommercialProduct::create(['uuid' => '55555555-5555-4555-8555-555555555555', 'code' => 'AI_TRIAL_LEGACY', 'name' => 'Inicial', 'type' => 'PLAN', 'billing_type' => 'MONTHLY', 'price' => 599, 'currency' => 'MXN', 'plan_id' => $this->subscription->plan_id, 'is_active' => true, 'is_public' => true, 'metadata' => ['ai_product' => true, 'ai_tier_rank' => 10]]);
        $this->subscription->update(['status' => 'active', 'provider_subscription_id' => null, 'plan_id' => $this->subscription->plan_id]);
        Http::fake([
            'https://api.mercadopago.com/preapproval/mp-upgrade' => Http::response([
                'id' => 'mp-upgrade', 'status' => 'authorized', 'external_reference' => 'ai-sub:'.$this->subscription->uuid,
                'preapproval_plan_id' => 'mp-plan-growth', 'auto_recurring' => ['transaction_amount' => '1299.00', 'currency_id' => 'MXN'],
            ]),
            'https://api.mercadopago.com/preapproval' => Http::response(['id' => 'mp-upgrade', 'status' => 'pending']),
        ]);
        app(RecurringSubscriptionService::class)->requestUpgrade($this->subscription, $this->growth, 'MONTHLY', 'owner@example.test');
        $this->assertSame(1, app(AiCapacityService::class)->limit($this->tenant, 'MAX_AGENTS', $this->subscription));
        app(RecurringSubscriptionService::class)->reconcile($this->subscription->fresh());
        $this->assertSame(2, app(AiCapacityService::class)->limit($this->tenant, 'MAX_AGENTS', $this->subscription->fresh()));
    }

    public function test_downgrade_is_applied_at_period_end_and_preserves_schedule_on_provider_failure(): void
    {
        $initial = Plan::create(['code' => 'AI_INICIAL', 'name' => 'Inicial', 'status' => 'active', 'monthly_price' => 599, 'currency' => 'MXN']);
        $module = Module::where('code', 'AI_CORE')->first();
        $initial->modules()->attach($module->id, ['is_included' => true]);
        foreach ([['MAX_AGENTS', 1], ['MAX_WEBCHAT_CHANNELS', 1], ['MAX_WHATSAPP_CHANNELS', 0], ['MONTHLY_CONVERSATIONS', 150]] as [$code, $quantity]) DB::table('network_plan_module_capacities')->insert(['plan_id' => $initial->id, 'module_id' => $module->id, 'capability_code' => $code, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
        $initialProduct = NetworkCommercialProduct::create(['uuid' => '44444444-4444-4444-8444-444444444444', 'code' => 'AI_INICIAL_MONTHLY', 'name' => 'Inicial', 'type' => 'PLAN', 'billing_type' => 'MONTHLY', 'price' => 599, 'currency' => 'MXN', 'plan_id' => $initial->id, 'is_active' => true, 'is_public' => true, 'metadata' => ['ai_product' => true, 'ai_tier_rank' => 10, 'provider_plan_id_monthly' => 'mp-plan-initial']]);
        $this->subscription->update(['status' => 'active', 'provider_subscription_id' => 'mp-sub-downgrade', 'current_period_end' => now()->subSecond()]);
        app(RecurringSubscriptionService::class)->scheduleDowngrade($this->subscription, $initialProduct->plan_id);
        Http::fake(['https://api.mercadopago.com/preapproval/mp-sub-downgrade' => Http::response(['id' => 'mp-sub-downgrade', 'status' => 'authorized'])]);
        $this->artisan('ai:process-billing-lifecycle')->assertSuccessful();
        $fresh = $this->subscription->fresh();
        $this->assertSame($initial->id, $fresh->plan_id);
        $this->assertNull($fresh->pending_plan_id);
        $fresh->update(['current_period_end' => now()->addDay()]);
        $this->assertSame(1, app(AiCapacityService::class)->limit($this->tenant, 'MAX_AGENTS', $fresh));
    }

    public function test_pending_plan_must_be_active_different_and_have_effective_date(): void
    {
        $service = app(RecurringSubscriptionService::class);
        try {
            $service->scheduleDowngrade($this->subscription, $this->subscription->plan_id);
            $this->fail('The current plan cannot be scheduled as pending.');
        } catch (\RuntimeException $e) {
            $this->assertSame('PENDING_PLAN_INVALID', $e->getMessage());
        }

        $inactive = Plan::create(['code' => 'AI_INACTIVE', 'name' => 'Inactive', 'status' => 'inactive', 'monthly_price' => 1, 'currency' => 'MXN']);
        $this->expectExceptionMessage('PENDING_PLAN_INVALID');
        $service->scheduleDowngrade($this->subscription, $inactive->id);
    }

    private function signatureHeaders(string $id, string $requestId): array
    {
        $ts = '1720000000';
        $manifest = 'id:'.strtolower($id).';request-id:'.$requestId.';ts:'.$ts.';';
        return ['x-request-id' => $requestId, 'x-signature' => 'ts='.$ts.',v1='.hash_hmac('sha256', $manifest, 'webhook-secret')];
    }

    private function createSchema(): void
    {
        foreach (['platform_payment_events', 'network_subscription_events', 'network_plan_module_capacities', 'network_entitlement_capacities', 'network_entitlements', 'network_subscriptions', 'network_plan_modules', 'network_commercial_products', 'network_tenants', 'network_plans', 'network_modules'] as $table) {
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
            $table->id(); $table->uuid('uuid'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('plan_id'); $table->string('status'); $table->timestamp('started_at')->nullable(); $table->timestamp('current_period_start')->nullable(); $table->timestamp('current_period_end')->nullable(); $table->timestamp('trial_ends_at')->nullable(); $table->timestamp('grace_ends_at')->nullable(); $table->timestamp('canceled_at')->nullable(); $table->timestamp('ended_at')->nullable(); $table->string('billing_frequency')->nullable(); $table->string('provider_subscription_id')->nullable(); $table->string('provider_plan_id')->nullable(); $table->string('provider_status')->nullable(); $table->timestamp('next_payment_date')->nullable(); $table->boolean('cancel_at_period_end')->default(false); $table->unsignedBigInteger('pending_plan_id')->nullable(); $table->timestamp('pending_effective_at')->nullable(); $table->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('module_id'); $table->string('code'); $table->boolean('is_enabled'); $table->string('source'); $table->timestamps(); $table->unique(['subscription_id', 'module_id']);
        });
        Schema::create('network_entitlement_capacities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('entitlement_id'); $table->string('capability_code'); $table->unsignedInteger('quantity'); $table->string('source'); $table->string('source_key'); $table->boolean('is_enabled')->default(true); $table->timestamps(); $table->unique(['entitlement_id', 'capability_code', 'source', 'source_key']);
        });
        Schema::create('network_subscription_events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('subscription_id'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('actor_user_id')->nullable(); $table->string('event'); $table->string('from_status')->nullable(); $table->string('to_status')->nullable(); $table->json('metadata')->nullable(); $table->timestamp('created_at')->nullable();
        });
        Schema::create('platform_payment_events', function (Blueprint $table): void {
            $table->id(); $table->string('provider'); $table->string('event_key'); $table->unsignedBigInteger('platform_payment_attempt_id')->nullable(); $table->string('provider_payment_id')->nullable(); $table->string('status'); $table->string('error_code')->nullable(); $table->timestamp('received_at'); $table->timestamp('processed_at')->nullable(); $table->timestamps(); $table->unique(['provider', 'event_key']);
        });
    }
}
