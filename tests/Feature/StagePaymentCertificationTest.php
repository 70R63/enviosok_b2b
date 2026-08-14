<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerCheckout, TenantCustomerProfile, TenantOperation};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Payments\Models\{TenantPaymentAttempt, TenantPaymentConnection, TenantPaymentEvent};
use App\Domain\Payments\TenantPaymentService;
use App\Domain\Payments\VerifiedTenantPaymentService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, DB, Http, Log, Schema};
use Tests\TestCase;

final class StagePaymentCertificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        config([
            'zigo_payments.providers.mercado_pago.environment' => 'sandbox',
            'zigo_payments.providers.mercado_pago.api_url' => 'https://api.mercadopago.com',
            'zigo_payments.providers.mercado_pago.webhook_secret' => 'seller-secret',
            'zigo_payments.providers.mercado_pago.webhook_tolerance_seconds' => 300,
            'zigo_usage.metrics' => ['operations'],
        ]);
        $this->schema();
    }

    protected function tearDown(): void
    {
        foreach (['tenant_payment_events', 'tenant_payment_attempts', 'tenant_payment_connections', 'local_tracking_events', 'local_shipments', 'tenant_customer_checkouts', 'tenant_customer_profiles', 'network_usage_events', 'network_tenant_operations', 'network_subscriptions', 'network_tenant_brandings', 'network_tenants', 'network_plans', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_production_and_missing_confirmation_are_blocked_without_changes(): void
    {
        $attempt = $this->fixture();
        $this->app->instance('env', 'production');
        $this->artisan('zigo:stage-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])
            ->expectsOutput('Stage payment simulation is disabled in this environment.')->assertExitCode(1);
        $this->assertSame('PENDING', $attempt->fresh()->status);

        $this->app->instance('env', 'testing');
        $this->artisan('zigo:stage-payment-approve', ['attempt' => $attempt->uuid])
            ->expectsOutput('The --confirm-stage option is required.')->assertExitCode(1);
        $this->assertSame('PENDING_PAYMENT', $attempt->checkout->fresh()->status);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_invalid_attempt_and_tenant_isolation_do_not_change_state(): void
    {
        $attempt = $this->fixture();
        $this->artisan('zigo:stage-payment-approve', ['attempt' => 'does-not-exist', '--confirm-stage' => true])
            ->assertExitCode(1);
        $this->assertDatabaseCount('local_shipments', 0);

        $otherTenant = $this->tenant('other');
        $attempt->checkout->update(['tenant_id' => $otherTenant->id]);
        $this->artisan('zigo:stage-payment-approve', ['attempt' => $attempt->external_reference, '--confirm-stage' => true])
            ->expectsOutput('Checkout tenant mismatch.')->assertExitCode(1);
        $this->assertSame('PENDING', $attempt->fresh()->status);
        $this->assertDatabaseCount('tenant_payment_events', 0);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_wrong_amount_currency_and_seller_are_rejected_by_shared_verified_pipeline(): void
    {
        foreach (['amount', 'currency', 'seller'] as $mismatch) {
            $attempt = $this->fixture($mismatch);
            $payment = $this->payment($attempt);
            if ($mismatch === 'amount') $payment['transaction_amount'] = '999.00';
            if ($mismatch === 'currency') $payment['currency_id'] = 'USD';
            if ($mismatch === 'seller') $payment['collector_id'] = 'other-seller';

            try {
                app(VerifiedTenantPaymentService::class)->process($attempt, $payment);
                $this->fail("{$mismatch} mismatch should fail.");
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString(strtoupper($mismatch), $exception->getMessage());
            }
            $this->assertSame('PENDING', $attempt->fresh()->status);
            $this->assertSame('PENDING_PAYMENT', $attempt->checkout->fresh()->status);
        }
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_stage_command_uses_real_fulfillment_and_is_idempotent(): void
    {
        $attempt = $this->fixture();
        Log::spy();
        $parameters = ['attempt' => (string) $attempt->id, '--confirm-stage' => true];
        $exitCode = Artisan::call('zigo:stage-payment-approve', $parameters);
        $output = Artisan::output();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Stage payment fulfilled through the real pipeline.', $output);

        $attempt->refresh();
        $checkout = $attempt->checkout->fresh();
        $shipment = LocalShipment::sole();
        $this->assertSame('APPROVED', $attempt->status);
        $this->assertSame('SIM-STAGE-'.$attempt->uuid, $attempt->provider_payment_id);
        $this->assertSame('PAID', $checkout->status);
        $this->assertSame('APPROVED', $checkout->payment_status);
        $this->assertSame('confirmed', $checkout->operation->fresh()->status);
        $this->assertSame($shipment->tracking_number, $shipment->guide_snapshot['tracking_number']);
        $this->assertStringStartsWith('ZL', $shipment->tracking_number);
        $this->assertDatabaseHas('local_tracking_events', ['local_shipment_id' => $shipment->id, 'event_code' => 'SHIPMENT_CREATED']);
        $this->assertDatabaseHas('tenant_payment_events', ['payment_attempt_id' => $attempt->id, 'event_type' => 'STAGE_SIMULATION', 'status' => 'PROCESSED']);
        Log::shouldHaveReceived('notice')->once()->with('Stage payment simulation fulfilled', \Mockery::on(fn (array $context): bool =>
            $context['source'] === 'STAGE_SIMULATION' && $context['actor'] === 'CLI' &&
            $context['provider'] === 'MERCADO_PAGO' && $context['real_payment'] === false
        ));
        $this->assertDatabaseCount('network_usage_events', 1);

        $this->artisan('zigo:stage-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])
            ->expectsOutput('Attempt already fulfilled.')->assertExitCode(0);
        $this->artisan('zigo:stage-payment-approve', ['attempt' => $attempt->external_reference, '--confirm-stage' => true])
            ->expectsOutput('Attempt already fulfilled.')->assertExitCode(0);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('local_tracking_events', 1);
        $this->assertDatabaseCount('network_usage_events', 1);
        $this->assertDatabaseCount('tenant_payment_events', 1);
    }

    public function test_productive_webhook_continues_through_shared_verified_pipeline(): void
    {
        $attempt = $this->fixture();
        $payment = $this->payment($attempt, '123456789');
        Http::fake(['api.mercadopago.com/v1/payments/123456789' => Http::response($payment, 200)]);
        $timestamp = (string) time();
        $requestId = 'request-shared-pipeline';
        $manifest = "id:123456789;request-id:{$requestId};ts:{$timestamp};";
        $signature = hash_hmac('sha256', $manifest, 'seller-secret');
        $request = Request::create('/api/payments/mercado-pago/webhook?attempt='.$attempt->uuid.'&data[id]=123456789', 'POST', ['type' => 'payment'], [], [], [
            'HTTP_X_REQUEST_ID' => $requestId,
            'HTTP_X_SIGNATURE' => "ts={$timestamp},v1={$signature}",
        ]);

        app(TenantPaymentService::class)->webhook($request);

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('PAID', $attempt->checkout->fresh()->status);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseHas('tenant_payment_events', ['event_type' => 'payment', 'status' => 'PROCESSED']);
    }

    private function fixture(string $suffix = 'base'): TenantPaymentAttempt
    {
        $tenant = $this->tenant('tenant-'.$suffix);
        $profile = TenantCustomerProfile::create(['tenant_id' => $tenant->id, 'user_id' => DB::table('users')->insertGetId(['name' => 'Buyer', 'email' => "{$suffix}@example.test", 'password' => 'x', 'empresa_id' => 1, 'created_at' => now(), 'updated_at' => now()]), 'status' => 'active']);
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'customer_profile_id' => $profile->id, 'channel' => 'b2c', 'status' => 'quoted', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL', 'metadata' => []]);
        $checkout = TenantCustomerCheckout::create([
            'tenant_id' => $tenant->id, 'customer_profile_id' => $profile->id, 'tenant_operation_id' => $operation->id,
            'status' => 'PENDING_PAYMENT', 'payment_status' => 'PENDING', 'currency' => 'MXN',
            'shipping_amount' => '120.00', 'evidence_amount' => '0.00', 'total_amount' => '120.00',
            'quote_snapshot' => [], 'shipping_data_snapshot' => [
                'sender' => ['name' => 'Sender', 'address' => 'Origin', 'postal_code' => '64000'],
                'recipient' => ['name' => 'Recipient', 'address' => 'Destination', 'postal_code' => '64000'],
                'package' => ['type' => 'box', 'weight' => 1],
            ], 'proof_option_snapshot' => [], 'expires_at' => now()->addHour(),
        ]);
        $connection = TenantPaymentConnection::create(['tenant_id' => $tenant->id, 'provider' => 'MERCADO_PAGO', 'status' => 'CONNECTED', 'provider_account_id' => 'seller-'.$suffix, 'access_token' => 'sandbox-token', 'metadata' => ['environment' => 'sandbox']]);
        return TenantPaymentAttempt::create([
            'tenant_id' => $tenant->id, 'customer_profile_id' => $profile->id, 'checkout_id' => $checkout->id,
            'payment_connection_id' => $connection->id, 'provider' => 'MERCADO_PAGO', 'status' => 'PENDING',
            'provider_preference_id' => 'pref-'.$suffix, 'external_reference' => 'zg_'.$suffix,
            'amount' => '120.00', 'currency' => 'MXN', 'marketplace_fee_amount' => '0.00',
        ]);
    }

    private function payment(TenantPaymentAttempt $attempt, ?string $id = null): array
    {
        return ['id' => $id ?? 'payment-'.$attempt->uuid, 'status' => 'approved', 'external_reference' => $attempt->external_reference, 'transaction_amount' => $attempt->amount, 'currency_id' => $attempt->currency, 'collector_id' => $attempt->connection->provider_account_id, 'live_mode' => false];
    }

    private function tenant(string $slug): Tenant
    {
        $planId = DB::table('network_plans')->insertGetId(['code' => strtoupper($slug), 'name' => $slug, 'status' => 'active', 'currency' => 'MXN', 'created_at' => now(), 'updated_at' => now()]);
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $planId, 'status' => 'active', 'started_at' => now(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        return $tenant;
    }

    private function schema(): void
    {
        Schema::create('users', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('name'), $t->string('email')->unique(), $t->string('password'), $t->unsignedBigInteger('empresa_id'), $t->timestamps()]));
        Schema::create('network_plans', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('code'), $t->string('name'), $t->string('status'), $t->string('currency'), $t->timestamps()]));
        Schema::create('network_tenants', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->string('name'), $t->string('slug')->unique(), $t->string('status'), $t->timestamps()]));
        Schema::create('network_tenant_brandings', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->string('brand_name')->nullable(), $t->string('logo_path')->nullable(), $t->string('primary_color')->nullable(), $t->timestamps()]));
        Schema::create('network_subscriptions', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->unsignedInteger('operations_limit')->nullable(), $t->timestamp('started_at'), $t->timestamp('current_period_start'), $t->timestamp('current_period_end'), $t->timestamp('trial_ends_at')->nullable(), $t->timestamp('grace_ends_at')->nullable(), $t->timestamp('canceled_at')->nullable(), $t->timestamp('ended_at')->nullable(), $t->timestamps()]));
        Schema::create('network_usage_events', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->nullable(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->string('metric'), $t->unsignedInteger('quantity'), $t->string('idempotency_key')->nullable(), $t->timestamp('occurred_at'), $t->json('metadata')->nullable(), $t->timestamp('created_at')->nullable(), $t->unique(['tenant_id', 'metric', 'idempotency_key'])]));
        Schema::create('tenant_customer_profiles', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('user_id'), $t->string('status'), $t->string('display_name')->nullable(), $t->timestamps()]));
        Schema::create('network_tenant_operations', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('subscription_id')->nullable(), $t->unsignedBigInteger('customer_profile_id')->nullable(), $t->string('channel'), $t->string('status'), $t->string('provider')->nullable(), $t->string('service_code')->nullable(), $t->json('metadata')->nullable(), $t->timestamps()]));
        Schema::create('tenant_customer_checkouts', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('customer_profile_id'), $t->unsignedBigInteger('tenant_operation_id')->unique(), $t->string('status'), $t->string('payment_status'), $t->char('currency', 3), $t->decimal('shipping_amount', 12, 2), $t->decimal('evidence_amount', 12, 2), $t->decimal('total_amount', 12, 2), $t->json('quote_snapshot'), $t->json('shipping_data_snapshot'), $t->json('proof_option_snapshot'), $t->string('payment_provider')->nullable(), $t->string('payment_reference')->nullable(), $t->timestamp('expires_at')->nullable(), $t->timestamp('paid_at')->nullable(), $t->timestamps()]));
        Schema::create('local_shipments', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('tenant_operation_id')->unique(), $t->string('tracking_number')->unique(), $t->string('service_code'), $t->string('status'), $t->json('sender_snapshot'), $t->json('recipient_snapshot'), $t->json('package_snapshot'), $t->json('pricing_snapshot'), $t->json('guide_snapshot'), $t->unsignedBigInteger('created_by_user_id')->nullable(), $t->timestamps()]));
        Schema::create('local_tracking_events', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->unsignedBigInteger('local_shipment_id'), $t->string('status'), $t->string('event_code'), $t->text('description')->nullable(), $t->timestamp('occurred_at'), $t->unsignedBigInteger('created_by_user_id')->nullable(), $t->json('metadata')->nullable(), $t->timestamp('created_at')->nullable()]));
        Schema::create('tenant_payment_connections', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->string('provider'), $t->string('status'), $t->string('provider_account_id')->nullable(), $t->text('access_token')->nullable(), $t->text('refresh_token')->nullable(), $t->timestamp('token_expires_at')->nullable(), $t->json('scopes')->nullable(), $t->timestamp('connected_at')->nullable(), $t->timestamp('disconnected_at')->nullable(), $t->json('metadata')->nullable(), $t->timestamps()]));
        Schema::create('tenant_payment_attempts', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->uuid('uuid')->unique(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('customer_profile_id'), $t->unsignedBigInteger('checkout_id'), $t->unsignedBigInteger('payment_connection_id'), $t->string('provider'), $t->string('status'), $t->string('provider_preference_id')->nullable(), $t->string('provider_payment_id')->nullable(), $t->string('external_reference')->unique(), $t->decimal('amount', 12, 2), $t->char('currency', 3), $t->decimal('marketplace_fee_amount', 12, 2), $t->text('init_point')->nullable(), $t->timestamp('approved_at')->nullable(), $t->timestamp('rejected_at')->nullable(), $t->timestamps(), $t->unique(['provider', 'provider_payment_id'])]));
        Schema::create('tenant_payment_events', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->string('provider'), $t->string('event_key'), $t->unsignedBigInteger('payment_attempt_id')->nullable(), $t->string('event_type')->nullable(), $t->string('provider_payment_id')->nullable(), $t->string('status'), $t->string('error_code')->nullable(), $t->timestamp('received_at'), $t->timestamp('processed_at')->nullable(), $t->timestamps(), $t->unique(['provider', 'event_key'])]));
    }
}
