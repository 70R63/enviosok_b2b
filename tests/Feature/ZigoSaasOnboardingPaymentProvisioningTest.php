<?php

namespace Tests\Feature;

use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\{
    NetworkCommercialProduct, PlatformPaymentAttempt, PlatformPaymentEvent,
    TenantOperationAllowance, TenantSaasOrder
};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\{
    OnboardingApplicationService, OnboardingStateService, OnboardingSubdomainService,
    SaasTenantProvisioningService
};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Jobs\ProvisionSaasOnboardingJob;
use App\Models\{Empresa, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Http, Log, Queue, Schema};
use Tests\TestCase;

class ZigoSaasOnboardingPaymentProvisioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        $this->schema();
        config([
            'zigo_surfaces.payments.host' => 'payments.zigo.local',
            'zigo_onboarding.subdomain_base' => 'zigo-envios.com',
            'zigo_onboarding.tenant_subdomain_suffix' => '',
            'zigo_onboarding.tenant_domain_environment' => 'production',
            'zigo_onboarding.managed_subdomains_are_verified' => true,
            'zigo_payments.platform.account_id' => '123456',
            'zigo_payments.platform.access_token' => 'platform-token',
            'zigo_payments.platform.webhook_secret' => 'webhook-secret',
            'zigo_payments.providers.mercado_pago.api_url' => 'https://api.mercadopago.com',
            'zigo_payments.providers.mercado_pago.webhook_tolerance_seconds' => 300,
        ]);
    }

    public function test_invalid_signature_is_unauthorized_and_pending_or_rejected_never_pay_or_provision(): void
    {
        [$pending, $pendingAttempt] = $this->pending('pending-case');
        $this->postJson($this->webhookUrl($pendingAttempt, 'p-1'), ['data' => ['id' => 'p-1']])
            ->assertUnauthorized();
        $this->assertSame('PENDING_PAYMENT', $pending->fresh()->status);

        Queue::fake();
        [$rejected, $rejectedAttempt] = $this->pending('rejected-case');
        Http::fakeSequence()
            ->push($this->payment($pendingAttempt, 'p-2', 'pending'))
            ->push($this->payment($rejectedAttempt, 'p-3', 'rejected'));
        $this->signedPost($pendingAttempt, 'p-2', 'request-p-2')->assertOk();
        $this->assertSame('PENDING_PAYMENT', $pending->fresh()->status);
        $this->assertSame('PENDING', $pendingAttempt->fresh()->status);

        $this->signedPost($rejectedAttempt, 'p-3', 'request-p-3')->assertOk();
        $this->assertSame('PENDING_PAYMENT', $rejected->fresh()->status);
        $this->assertSame('REJECTED', $rejectedAttempt->fresh()->status);
        Queue::assertNothingPushed();
        $this->assertOperationalCounts(0);
    }

    public function test_all_provider_mismatches_leave_onboarding_unpaid(): void
    {
        foreach (['amount', 'currency', 'collector', 'reference'] as $case) {
            [$application, $attempt] = $this->pending('mismatch-'.$case);
            $payment = $this->payment($attempt, 'mismatch-'.$case, 'approved');
            if ($case === 'amount') $payment['transaction_amount'] = '999.00';
            if ($case === 'currency') $payment['currency_id'] = 'USD';
            if ($case === 'collector') $payment['collector_id'] = 'other-account';
            if ($case === 'reference') $payment['external_reference'] = 'wrong-reference';
            $this->sendWebhookResponse($attempt, $payment, 'request-'.$case)->assertOk();
            $this->assertSame('PENDING_PAYMENT', $application->fresh()->status);
            $this->assertNull($application->paid_at);
        }
        $this->assertSame(4, PlatformPaymentEvent::where('status', 'INCONSISTENT')->count());
        $this->assertOperationalCounts(0);
    }

    public function test_approved_commits_paid_dispatches_afterward_and_duplicate_is_noop(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('approved-case');
        $this->sendWebhook($attempt, 'approved-1', 'approved', 'same-request')->assertOk();

        $this->assertSame('PAID', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->paid_at);
        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('approved-1', $attempt->fresh()->provider_payment_id);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);

        $this->sendWebhook($attempt, 'approved-1', 'approved', 'same-request')->assertOk();
        $this->assertSame(1, PlatformPaymentEvent::count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
        $this->assertOperationalCounts(0);
    }

    public function test_dedicated_platform_webhook_resolves_attempt_without_query_hint(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('external-reference-fallback');
        $paymentId = 'approved-without-hint';
        Http::fake([
            'https://api.mercadopago.com/v1/payments/*' => Http::response(
                $this->payment($attempt, $paymentId, 'approved'),
            ),
        ]);

        $this->signedPostToUrl(
            $this->platformWebhookUrl($paymentId),
            $paymentId,
            'request-without-hint',
        )->assertOk();

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('PAID', $application->fresh()->status);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('platformPaymentIdSourceProvider')]
    public function test_dedicated_platform_webhook_accepts_compatible_payment_id_sources(string $query, bool $sendJsonBody): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('payment-id-source-'.md5($query.(int) $sendJsonBody));
        $paymentId = 'source-payment';
        Http::fake([
            'https://api.mercadopago.com/v1/payments/*' => Http::response(
                $this->payment($attempt, $paymentId, 'approved'),
            ),
        ]);

        $body = $sendJsonBody ? ['type' => 'payment', 'data' => ['id' => $paymentId]] : [];
        $this->signedPostToUrl(
            'http://payments.zigo.local/api/payments/mercado-pago/platform/webhook'.$query,
            $paymentId,
            'request-payment-id-source',
            $body,
        )->assertOk();

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('PAID', $application->fresh()->status);
    }

    public static function platformPaymentIdSourceProvider(): array
    {
        return [
            'json body data.id' => ['', true],
            'query data brackets' => ['?data[id]=source-payment', false],
            'php normalized data_id' => ['?data_id=source-payment', false],
        ];
    }

    public function test_missing_payment_id_is_rejected_before_event_persistence(): void
    {
        Log::spy();
        $timestamp = (string) time();

        $this->withHeaders([
            'x-request-id' => 'request-without-payment-id',
            'x-signature' => 'ts='.$timestamp.',v1=not-used',
        ])->postJson(
            'http://payments.zigo.local/api/payments/mercado-pago/platform/webhook',
            ['type' => 'payment'],
        )->assertUnauthorized();

        $this->assertSame(0, PlatformPaymentEvent::count());
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('ZIGO_PLATFORM_WEBHOOK_REJECT reason=PAYMENT_ID_MISSING');
    }

    public function test_hmac_mismatch_logs_only_safe_reason_and_creates_no_event(): void
    {
        Log::spy();
        $timestamp = (string) time();

        $this->withHeaders([
            'x-request-id' => 'request-not-logged',
            'x-signature' => 'ts='.$timestamp.',v1=value-not-logged',
        ])->postJson(
            'http://payments.zigo.local/api/payments/mercado-pago/platform/webhook',
            ['type' => 'payment', 'data' => ['id' => 'payment-not-logged']],
        )->assertUnauthorized();

        $this->assertSame(0, PlatformPaymentEvent::count());
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('ZIGO_PLATFORM_WEBHOOK_REJECT reason=HMAC_MISMATCH');
    }

    public function test_approved_return_is_verified_server_side_and_dispatches_once(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-approved');
        $payment = $this->payment($attempt, 'return-approved-payment', 'approved');
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);

        $event = app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, 'return-approved-payment');

        $this->assertSame('PROCESSED', $event?->status);
        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('return-approved-payment', $attempt->fresh()->provider_payment_id);
        $this->assertSame('PAID', $application->fresh()->status);
        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
        Http::assertSentCount(1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonApprovedReturnProvider')]
    public function test_browser_success_never_overrides_provider_status(string $providerStatus, string $attemptStatus): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-'.$providerStatus);
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response(
            $this->payment($attempt, 'return-'.$providerStatus.'-payment', $providerStatus),
        )]);

        app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, 'return-'.$providerStatus.'-payment');

        $this->assertSame($attemptStatus, $attempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PENDING_PAYMENT, $application->fresh()->status);
        Queue::assertNothingPushed();
    }

    public static function nonApprovedReturnProvider(): array
    {
        return [
            'pending' => ['pending', 'PENDING'],
            'rejected' => ['rejected', 'REJECTED'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidVerifiedReturnProvider')]
    public function test_invalid_verified_return_is_inconsistent(
        string $error,
        array $overrides,
    ): void {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('retinv-'.substr(md5($error), 0, 12));
        $payment = array_replace(
            $this->payment($attempt, 'return-invalid-payment-'.$error, 'approved'),
            $overrides,
        );
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);

        $event = app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, (string) $payment['id']);

        $this->assertSame('INCONSISTENT', $event?->status);
        $this->assertSame($error, $event?->error_code);
        $this->assertSame('PENDING', $attempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PENDING_PAYMENT, $application->fresh()->status);
        Queue::assertNothingPushed();
    }

    public static function invalidVerifiedReturnProvider(): array
    {
        return [
            'reference' => ['REFERENCE_MISMATCH', ['external_reference' => 'wrong-reference']],
            'amount' => ['AMOUNT_MISMATCH', ['transaction_amount' => '999.99']],
            'currency' => ['CURRENCY_MISMATCH', ['currency_id' => 'USD']],
            'collector' => ['PLATFORM_ACCOUNT_MISMATCH', ['collector_id' => 'wrong-account']],
        ];
    }

    public function test_return_without_payment_hint_makes_no_provider_request_or_state_change(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-without-hint');
        config(['zigo_surfaces.corporate.host' => 'zigo.local']);

        $this->get('http://zigo.local/zigo-platform/solicitud/'.$application->public_token.'/retorno/success?status=approved')
            ->assertOk();

        Http::assertNothingSent();
        $this->assertSame('PENDING', $attempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PENDING_PAYMENT, $application->fresh()->status);
        $this->assertSame(0, PlatformPaymentEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_duplicate_return_is_idempotent(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-duplicate');
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response(
            $this->payment($attempt, 'return-duplicate-payment', 'approved'),
        )]);
        $service = app(\App\Domain\Network\Commerce\PlatformPaymentService::class);

        $service->reconcileOnboardingReturn($application, 'return-duplicate-payment');
        $service->reconcileOnboardingReturn($application, 'return-duplicate-payment');

        $this->assertSame(1, PlatformPaymentEvent::count());
        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public function test_webhook_then_return_is_idempotent(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('webhook-then-return');
        $payment = $this->payment($attempt, 'webhook-then-return-payment', 'approved');
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);

        $this->signedPost($attempt, (string) $payment['id'], 'webhook-first')->assertOk();
        app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, (string) $payment['id']);

        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public function test_return_then_webhook_is_idempotent(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-then-webhook');
        $payment = $this->payment($attempt, 'return-then-webhook-payment', 'approved');
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);

        app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, (string) $payment['id']);
        $this->signedPost($attempt, (string) $payment['id'], 'webhook-second')->assertOk();

        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public function test_stale_pending_webhook_cannot_downgrade_an_approved_return(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('return-before-stale-pending');
        $approved = $this->payment($attempt, 'stable-approved-payment', 'approved');
        $pending = $this->payment($attempt, 'stable-approved-payment', 'pending');
        Http::fakeSequence()->push($approved)->push($pending);

        app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, 'stable-approved-payment');
        $this->signedPost($attempt, 'stable-approved-payment', 'stale-pending')->assertOk();

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PAID, $application->fresh()->status);
        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public function test_approved_attempt_rejects_a_different_payment_id(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('approved-different-payment');
        Http::fakeSequence()
            ->push($this->payment($attempt, 'original-payment', 'approved'))
            ->push($this->payment($attempt, 'different-payment', 'approved'));
        $service = app(\App\Domain\Network\Commerce\PlatformPaymentService::class);

        $service->reconcileOnboardingReturn($application, 'original-payment');
        $event = $service->reconcileOnboardingReturn($application, 'different-payment');

        $this->assertSame('INCONSISTENT', $event?->status);
        $this->assertSame('PAYMENT_ID_CONFLICT', $event?->error_code);
        $this->assertSame('original-payment', $attempt->fresh()->provider_payment_id);
        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame(1, $application->events()->where('event', 'PAYMENT_VERIFIED')->count());
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public function test_payment_id_owned_by_another_attempt_is_rejected_without_corruption(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$firstApplication, $firstAttempt] = $this->pending('payment-owner-first');
        [$secondApplication, $secondAttempt] = $this->pending('payment-owner-second');
        $service = app(\App\Domain\Network\Commerce\PlatformPaymentService::class);
        Http::fakeSequence()
            ->push($this->payment($firstAttempt, 'shared-provider-payment', 'approved'))
            ->push($this->payment($secondAttempt, 'shared-provider-payment', 'approved'));

        $service->reconcileOnboardingReturn($firstApplication, 'shared-provider-payment');
        $event = $service->reconcileOnboardingReturn($secondApplication, 'shared-provider-payment');

        $this->assertSame('INCONSISTENT', $event?->status);
        $this->assertSame('PAYMENT_ID_CONFLICT', $event?->error_code);
        $this->assertSame('PENDING', $secondAttempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PENDING_PAYMENT, $secondApplication->fresh()->status);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicReturnSurfaceProvider')]
    public function test_public_returns_share_server_side_reconciliation(string $surface): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$application, $attempt] = $this->pending('http-return-'.$surface);
        $paymentId = 'http-return-payment-'.$surface;
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response(
            $this->payment($attempt, $paymentId, 'approved'),
        )]);
        config(['zigo_surfaces.corporate.host' => 'zigo.local']);

        $this->get('http://zigo.local/'.$surface.'/solicitud/'.$application->public_token
            .'/retorno/failure?payment_id='.$paymentId.'&status=rejected&collection_status=rejected')
            ->assertOk();

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame(SaasOnboardingApplication::PAID, $application->fresh()->status);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
    }

    public static function publicReturnSurfaceProvider(): array
    {
        return [
            'ZIGO Platform' => ['zigo-platform'],
            'Agentes IA' => ['agentes-ia'],
        ];
    }

    public function test_reconciliation_log_contains_only_sanitized_error_code(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Log::spy();
        [$application, $attempt] = $this->pending('return-safe-log');
        $payment = $this->payment($attempt, 'provider-id-not-logged', 'approved');
        $payment['external_reference'] = 'provider-payload-not-logged';
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);

        app(\App\Domain\Network\Commerce\PlatformPaymentService::class)
            ->reconcileOnboardingReturn($application, 'provider-id-not-logged');

        Log::shouldHaveReceived('warning')->once()
            ->with('ZIGO_PLATFORM_RETURN_RECONCILIATION_REJECT reason=REFERENCE_MISMATCH');
    }

    public function test_inconsistent_event_can_be_reprocessed_and_late_expired_payment_becomes_paid(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('retry-event');
        [$expired, $expiredAttempt] = $this->pending('late-payment');
        Http::fakeSequence()
            ->pushStatus(500)
            ->push($this->payment($attempt, 'retry-payment', 'approved'))
            ->push($this->payment($expiredAttempt, 'late-approved', 'approved'));
        $this->signedPost($attempt, 'retry-payment', 'retry-request')->assertOk();
        $this->assertSame('INCONSISTENT', PlatformPaymentEvent::first()->status);
        $this->signedPost($attempt, 'retry-payment', 'retry-request')->assertOk();
        $this->assertSame('PROCESSED', PlatformPaymentEvent::first()->fresh()->status);
        $this->assertSame('PAID', $application->fresh()->status);

        $expired = app(OnboardingStateService::class)->transition($expired, 'EXPIRED', 'CHECKOUT_EXPIRED');
        $this->signedPost($expiredAttempt, 'late-approved', 'request-late-approved')->assertOk();
        $this->assertSame('PAID', $expired->fresh()->status);
        $this->assertNotNull($expired->fresh()->paid_at);
    }

    public function test_failed_provisioning_preserves_payment_and_retry_completes_every_invariant(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('provision-all', true);
        $this->sendWebhook($attempt, 'paid-provision', 'approved')->assertOk();
        config(['zigo_onboarding.managed_subdomains_are_verified' => false]);
        $failed = app(SaasTenantProvisioningService::class)->provision($application->fresh());

        $this->assertSame('FAILED', $failed->status);
        $this->assertNotNull($failed->paid_at);
        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $legacyCompanyId = $failed->legacy_empresa_id;
        $this->assertNotNull($legacyCompanyId);
        $this->assertSame(1, Empresa::withoutGlobalScopes()->count());
        $this->assertOperationalCounts(0);

        config(['zigo_onboarding.managed_subdomains_are_verified' => true]);
        $active = app(SaasTenantProvisioningService::class)->provision($failed);
        $this->assertSame('ACTIVE', $active->status);
        $this->assertSame($legacyCompanyId, $active->legacy_empresa_id);
        $this->assertSame(1, Empresa::withoutGlobalScopes()->count());
        $this->assertNotNull($active->tenant_id);
        $this->assertNotNull($active->owner_user_id);
        $this->assertNotNull($active->legacy_empresa_id);
        $this->assertSame(1, DB::table('empresas')->count());
        $this->assertSame($active->legacy_empresa_id, User::findOrFail($active->owner_user_id)->empresa_id);
        $this->assertTrue($active->owner_is_new);
        $this->assertDatabaseHas('network_tenant_domains', [
            'tenant_id' => $active->tenant_id,
            'domain' => 'provision-all.zigo-envios.com',
            'environment' => 'production',
        ]);
        $this->assertSame('provision-all', $active->tenant->slug);
        $this->assertOperationalCounts(1);
        $this->assertSame(2, DB::table('network_entitlements')->count());
        $this->assertSame(1, TenantOperationAllowance::count());
        $this->assertSame(1, DB::table('tenant_api_quota_policies')->count());
        $this->assertSame(1, TenantSaasOrder::where('onboarding_application_id', $active->id)->count());
        $this->assertSame('active', DB::table('network_tenants')->value('status'));

        $ids = [$active->tenant_id, $active->owner_user_id, $active->legacy_empresa_id];
        $again = app(SaasTenantProvisioningService::class)->provision($active);
        $this->assertSame($ids, [$again->tenant_id, $again->owner_user_id, $again->legacy_empresa_id]);
        $this->assertOperationalCounts(1);
        $this->assertDatabaseHas('saas_onboarding_events', ['event' => 'PROVISIONING_RETRIED']);
        $this->assertDatabaseHas('saas_onboarding_events', ['event' => 'PROVISIONING_COMPLETED']);
    }

    public function test_existing_owner_can_own_a_new_tenant_without_changing_legacy_identity(): void
    {
        Queue::fake();
        $company = Empresa::withoutGlobalScopes()->create([
            'estatus' => 1, 'contacto' => 'Existing', 'nombre' => 'Historical Co',
            'email' => 'compatible@example.test', 'telefono' => '5555555555',
        ]);
        $password = Hash::make('existing-secret');
        $owner = User::create([
            'name' => 'Existing', 'email' => 'compatible@example.test',
            'empresa_id' => $company->id, 'password' => $password,
        ]);
        $previousTenant = Tenant::create([
            'name' => 'Historical Tenant', 'slug' => 'historical-tenant', 'status' => 'active',
        ]);
        $previousMembership = $previousTenant->memberships()->create([
            'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active',
        ]);
        [$compatible, $attempt] = $this->pending('compatible', false, 'compatible@example.test');
        Http::fakeSequence()->push($this->payment($attempt, 'compatible-paid', 'approved'));
        $this->signedPost($attempt, 'compatible-paid', 'request-compatible-paid')->assertOk();
        $result = app(SaasTenantProvisioningService::class)->provision($compatible->fresh());

        $this->assertSame('ACTIVE', $result->status);
        $this->assertSame($owner->id, $result->owner_user_id);
        $this->assertNotSame($company->id, $result->legacy_empresa_id);
        $this->assertSame('Company compatible SA de CV', Empresa::withoutGlobalScopes()->findOrFail($result->legacy_empresa_id)->nombre);
        $this->assertSame($company->id, $owner->fresh()->empresa_id);
        $this->assertSame('compatible@example.test', $owner->fresh()->email);
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertFalse($result->owner_is_new);
        $this->assertDatabaseHas('network_tenant_memberships', [
            'id' => $previousMembership->id, 'tenant_id' => $previousTenant->id, 'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('network_tenant_memberships', [
            'tenant_id' => $result->tenant_id, 'user_id' => $owner->id,
            'role' => 'owner', 'status' => 'active',
        ]);
        $this->assertSame(1, User::where('email', 'compatible@example.test')->count());
        $this->assertDatabaseHas('saas_onboarding_events', ['event' => 'OWNER_REUSED']);

        $ids = [$result->legacy_empresa_id, $result->owner_user_id, $result->tenant_id];
        $again = app(SaasTenantProvisioningService::class)->provision($result->fresh());
        $this->assertSame($ids, [$again->legacy_empresa_id, $again->owner_user_id, $again->tenant_id]);
        $this->assertSame(2, DB::table('network_tenant_memberships')->count());
    }

    public function test_real_legacy_company_identity_collision_still_fails_safely(): void
    {
        Queue::fake();
        $historical = Empresa::withoutGlobalScopes()->create([
            'estatus' => 1, 'contacto' => 'Existing', 'nombre' => 'Historical Co',
            'email' => 'conflict@example.test', 'telefono' => '5555555555',
        ]);
        $owner = User::create([
            'name' => 'Conflict', 'email' => 'conflict@example.test',
            'empresa_id' => $historical->id, 'password' => Hash::make('existing-secret'),
        ]);
        Empresa::withoutGlobalScopes()->create([
            'estatus' => 1, 'contacto' => 'Unrelated', 'nombre' => 'Company conflict',
            'email' => 'unrelated@example.test', 'telefono' => '5555555555',
        ]);
        [$conflict, $conflictAttempt] = $this->pending('conflict', false, 'conflict@example.test');
        Http::fakeSequence()->push($this->payment($conflictAttempt, 'conflict-paid', 'approved'));
        $this->signedPost($conflictAttempt, 'conflict-paid', 'request-conflict-paid')->assertOk();
        $failed = app(SaasTenantProvisioningService::class)->provision($conflict->fresh());

        $this->assertSame('FAILED', $failed->status);
        $this->assertSame('LEGACY_COMPANY_CONFLICT', $failed->failure_code);
        $this->assertSame($historical->id, $owner->fresh()->empresa_id);
        $this->assertNull($failed->legacy_empresa_id);
    }

    public function test_stage_domain_uses_suffix_and_sandbox_without_changing_tenant_slug(): void
    {
        Queue::fake();
        config([
            'zigo_onboarding.tenant_subdomain_suffix' => '-stage',
            'zigo_onboarding.tenant_domain_environment' => 'sandbox',
        ]);
        [$application, $attempt] = $this->pending('bruniverse');
        $this->sendWebhook($attempt, 'stage-domain-paid', 'approved')->assertOk();

        $active = app(SaasTenantProvisioningService::class)->provision($application->fresh());
        $this->assertSame('ACTIVE', $active->status);
        $this->assertSame('bruniverse', $active->tenant->slug);
        $this->assertDatabaseHas('network_tenant_domains', [
            'tenant_id' => $active->tenant_id,
            'domain' => 'bruniverse-stage.zigo-envios.com',
            'environment' => 'sandbox',
            'is_primary' => true,
            'status' => 'verified',
        ]);

        $again = app(SaasTenantProvisioningService::class)->provision($active->fresh());
        $this->assertSame($active->tenant_id, $again->tenant_id);
        $this->assertSame(1, DB::table('network_tenant_domains')->count());
    }

    public function test_reconciler_processes_paid_and_active_is_safe_noop(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('command-case');
        $this->sendWebhook($attempt, 'command-paid', 'approved')->assertOk();
        $this->artisan('zigo:onboarding:reconcile', ['--uuid' => $application->uuid])
            ->assertExitCode(0);
        $this->assertSame('ACTIVE', $application->fresh()->status);
        $this->artisan('zigo:onboarding:reconcile', ['--uuid' => $application->uuid])
            ->assertExitCode(0);
        $this->assertOperationalCounts(1);
    }

    public function test_stage_platform_payment_approves_onboarding_and_dispatches_provisioning_once(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('stage-command');
        $application->update(['total' => '100.00', 'currency' => 'MXN']);
        $attempt->update(['provider_preference_id' => 'pref-stage', 'amount' => '100.00']);

        $this->artisan('zigo:stage-platform-payment-approve', [
            'attempt' => $attempt->uuid, '--confirm-stage' => true,
        ])->assertExitCode(0);

        $this->assertSame('APPROVED', $attempt->fresh()->status);
        $this->assertSame('PAID', $application->fresh()->status);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);

        $this->artisan('zigo:stage-platform-payment-approve', [
            'attempt' => $attempt->uuid, '--confirm-stage' => true,
        ])->assertExitCode(0);
        Queue::assertPushed(ProvisionSaasOnboardingJob::class, 1);
        $this->assertSame(1, PlatformPaymentEvent::count());
    }

    public function test_audit_metadata_never_contains_passwords_or_tokens(): void
    {
        Queue::fake();
        [$application, $attempt] = $this->pending('audit-safe');
        $this->sendWebhook($attempt, 'audit-paid', 'approved')->assertOk();
        app(SaasTenantProvisioningService::class)->provision($application->fresh());
        $events = DB::table('saas_onboarding_events')->pluck('metadata_json')->implode(' ');
        $this->assertStringNotContainsString('platform-token', $events);
        $this->assertStringNotContainsString('webhook-secret', $events);
        $this->assertStringNotContainsString('password', strtolower($events));
        $this->assertStringNotContainsString('rapidgo', strtolower($events));
    }

    public function test_ai_public_onboarding_uses_existing_application_and_provisions_ai_only(): void
    {
        Queue::fake();
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 5]);
        $plan = Plan::create(['code' => 'AI_AGENTS', 'name' => 'Agentes IA', 'status' => 'active', 'monthly_price' => '299.00', 'currency' => 'MXN']);
        $plan->modules()->attach($module, ['is_included' => true, 'limit_value' => null]);
        $product = NetworkCommercialProduct::create([
            'code' => 'PUBLIC-AI-ONBOARDING', 'name' => 'Agentes IA', 'description' => 'AI only',
            'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '299.00', 'currency' => 'MXN',
            'module_id' => $module->id, 'plan_id' => $plan->id, 'is_active' => true,
            'metadata' => ['ai_product' => true, 'ai_kind' => 'base', 'ai_capacities' => [
                AiCapacityService::MAX_AGENTS => 1, AiCapacityService::MAX_WEBCHAT_CHANNELS => 1,
                AiCapacityService::MAX_WHATSAPP_CHANNELS => 0, AiCapacityService::MONTHLY_RUNTIME_UNITS => 1000,
                AiCapacityService::MONTHLY_ACTION_RUNS => 100, AiCapacityService::MONTHLY_CONVERSATIONS => 250,
            ]],
        ]);

        $application = app(OnboardingApplicationService::class)->createOrRecover([
            'contact_name' => 'AI Owner', 'contact_last_name' => 'Test', 'contact_email' => 'ai-only@example.test',
            'contact_phone' => '5551234567', 'company_name' => 'AI Company', 'company_legal_name' => 'AI Company SA',
            'selected_plan_id' => $plan->id, 'billing_period' => 'monthly',
        ], 'ai-public-purchase');
        $application = app(OnboardingApplicationService::class)->updateDraft($application, [
            'selected_plan_id' => $plan->id, 'billing_period' => 'monthly',
            'selected_modules_json' => ['plan_offer_uuid' => $product->uuid, 'module_ids' => []],
        ], 'AI_PRODUCT_SELECTED');
        $application = app(OnboardingSubdomainService::class)->reserve($application, 'ai-only');
        $application = app(OnboardingApplicationService::class)->freezeCommercialSnapshot($application, [], null, '0.00', $product->uuid);
        $application = app(OnboardingStateService::class)->transition($application, 'PENDING_PAYMENT', 'AI_READY_FOR_CHECKOUT', 'public_session');
        $attempt = PlatformPaymentAttempt::create([
            'onboarding_application_id' => $application->id, 'provider' => 'MERCADO_PAGO', 'status' => 'PENDING',
            'provider_preference_id' => 'pref-ai', 'external_reference' => 'ai-public-ref', 'amount' => '299.00', 'currency' => 'MXN',
        ]);
        $this->artisan('zigo:stage-platform-payment-approve', ['attempt' => $attempt->uuid, '--confirm-stage' => true])->assertExitCode(0);
        $active = app(SaasTenantProvisioningService::class)->provision($application->fresh());

        $this->assertSame('ACTIVE', $active->status);
        $this->assertSame(1, DB::table('network_tenants')->count());
        $this->assertSame(1, DB::table('network_subscriptions')->count());
        $this->assertSame(1, DB::table('network_entitlements')->where('code', 'AI_CORE')->count());
        $this->assertSame(1, DB::table('network_entitlement_capacities')->where('capability_code', AiCapacityService::MAX_AGENTS)->value('quantity'));
        $this->assertSame(1, DB::table('network_tenants')->where('id', $active->tenant_id)->count());
        $this->assertSame('ACTIVE', app(SaasTenantProvisioningService::class)->provision($active->fresh())->status);
        $this->assertSame(1, DB::table('network_tenants')->count());
    }

    public function test_public_ai_start_route_creates_pending_application_without_logistics_fields(): void
    {
        $module = Module::create(['code' => 'AI_CORE', 'name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 5]);
        $product = NetworkCommercialProduct::create([
            'code' => 'PUBLIC-AI-START', 'name' => 'Agentes IA', 'description' => 'AI only',
            'type' => 'ADDON', 'billing_type' => 'MONTHLY', 'price' => '299.00', 'currency' => 'MXN',
            'module_id' => $module->id, 'is_active' => true,
            'metadata' => ['ai_product' => true, 'ai_kind' => 'base', 'ai_capacities' => [AiCapacityService::MAX_AGENTS => 1]],
        ]);
        config(['zigo_surfaces.corporate.host' => 'zigo.local']);
        $response = $this->withServerVariables(['HTTP_HOST' => 'zigo.local'])->post('/agentes-ia/comenzar', [
            'contact_name' => 'Public', 'contact_last_name' => 'Owner', 'contact_email' => 'public-start@example.test',
            'company_name' => 'AI Only Company', 'requested_subdomain' => 'ai-start', 'offer' => $product->uuid,
        ]);
        $response->assertRedirect();
        $application = SaasOnboardingApplication::where('contact_email', 'public-start@example.test')->firstOrFail();
        $this->assertSame('PENDING_PAYMENT', $application->status);
        $this->assertSame($product->uuid, data_get($application->commercial_snapshot_json, 'plan.commercial_product_uuid'));
        $this->assertSame(0, DB::table('network_modules')->where('code', 'SHIPPING')->count());
    }

    protected function pending(string $key, bool $withExtras = false, ?string $email = null, bool $withB2c = false): array
    {
        [$plan, $planOffer, $apiOffer, $opsOffer] = $this->catalog($key, $withB2c);
        $application = app(OnboardingApplicationService::class)->createOrRecover([
            'contact_name' => 'Owner', 'contact_last_name' => 'Test',
            'contact_email' => $email ?? $key.'@example.test', 'contact_phone' => '5551234567',
            'company_name' => 'Company '.$key, 'company_legal_name' => 'Company '.$key.' SA de CV',
            'selected_plan_id' => $plan->id, 'billing_period' => 'monthly',
        ], 'purchase-'.$key);
        $moduleIds = $withExtras ? [$apiOffer->module_id] : [];
        $application = app(OnboardingApplicationService::class)->updateDraft($application, [
            'selected_plan_id' => $plan->id, 'billing_period' => 'monthly',
            'selected_modules_json' => [
                'plan_offer_uuid' => $planOffer->uuid, 'module_ids' => $moduleIds,
                'operations_offer_uuid' => $withExtras ? $opsOffer->uuid : null,
            ],
            'requested_operations' => $withExtras ? 500 : null,
        ], 'SOLUTION_SELECTED');
        $application = app(OnboardingSubdomainService::class)->reserve($application, $key);
        $selection = $application->selected_modules_json;
        $application = app(OnboardingApplicationService::class)->freezeCommercialSnapshot(
            $application, $moduleIds, $application->requested_operations, '0.00',
            $selection['plan_offer_uuid'], $selection['operations_offer_uuid'],
        );
        $application = app(OnboardingStateService::class)->transition(
            $application, 'PENDING_PAYMENT', 'READY_FOR_CHECKOUT', 'public_session'
        );
        $attempt = PlatformPaymentAttempt::create([
            'onboarding_application_id' => $application->id, 'provider' => 'MERCADO_PAGO',
            'status' => 'PENDING', 'external_reference' => 'ref-'.$key,
            'amount' => $application->total, 'currency' => $application->currency,
        ]);
        return [$application, $attempt];
    }

    protected function catalog(string $suffix, bool $withB2c = false): array
    {
        $shipping = Module::firstOrCreate(['code' => 'SHIPPING'], ['name'=>'Shipping','type'=>'core','is_active'=>true,'sort_order'=>1]);
        $api = Module::firstOrCreate(['code' => 'API'], ['name'=>'API','type'=>'addon','is_active'=>true,'sort_order'=>2]);
        $b2c = Module::firstOrCreate(['code' => 'B2C'], ['name'=>'B2C','type'=>'channel','is_active'=>true,'sort_order'=>3]);
        $plan = Plan::create(['code'=>'PLAN-'.$suffix,'name'=>'Plan '.$suffix,'status'=>'active','monthly_price'=>'999.00','currency'=>'MXN','included_operations'=>100]);
        $plan->modules()->attach($shipping, ['is_included'=>true,'limit_value'=>null]);
        if ($withB2c) $plan->modules()->attach($b2c, ['is_included'=>true,'limit_value'=>null]);
        $planOffer = NetworkCommercialProduct::create(['code'=>'OFFER-'.$suffix,'name'=>'Offer','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>'100.00','currency'=>'MXN','plan_id'=>$plan->id,'is_active'=>true]);
        $apiOffer = NetworkCommercialProduct::create(['code'=>'API-'.$suffix,'name'=>'API','type'=>'MODULE','billing_type'=>'MONTHLY','price'=>'20.00','currency'=>'MXN','module_id'=>$api->id,'is_active'=>true,'metadata'=>['api_monthly_request_limit'=>10000,'api_rate_limit_per_minute'=>60]]);
        $opsOffer = NetworkCommercialProduct::create(['code'=>'OPS-'.$suffix,'name'=>'Operations','type'=>'OPERATION_PACK','billing_type'=>'ONE_TIME','price'=>'50.00','currency'=>'MXN','included_operations'=>500,'is_active'=>true]);
        return [$plan, $planOffer, $apiOffer, $opsOffer];
    }

    protected function sendWebhook(PlatformPaymentAttempt $attempt, string $paymentId, string $status, ?string $requestId = null)
    {
        return $this->sendWebhookResponse($attempt, $this->payment($attempt, $paymentId, $status), $requestId ?? 'request-'.$paymentId);
    }

    protected function sendWebhookResponse(PlatformPaymentAttempt $attempt, array $payment, string $requestId)
    {
        Http::fake(['https://api.mercadopago.com/v1/payments/*' => Http::response($payment)]);
        return $this->signedPost($attempt, (string) $payment['id'], $requestId);
    }

    protected function signedPost(PlatformPaymentAttempt $attempt, string $paymentId, string $requestId)
    {
        return $this->signedPostToUrl($this->webhookUrl($attempt, $paymentId), $paymentId, $requestId);
    }

    protected function signedPostToUrl(string $url, string $paymentId, string $requestId, ?array $body = null)
    {
        $timestamp = (string) time();
        $manifest = 'id:'.strtolower($paymentId).';request-id:'.$requestId.';ts:'.$timestamp.';';
        $signature = hash_hmac('sha256', $manifest, 'webhook-secret');
        return $this->withHeaders(['x-request-id'=>$requestId,'x-signature'=>'ts='.$timestamp.',v1='.$signature])
            ->postJson($url, $body ?? ['type'=>'payment','data'=>['id'=>$paymentId]]);
    }

    protected function webhookUrl(PlatformPaymentAttempt $attempt, string $paymentId): string
    {
        return 'http://payments.zigo.local/api/payments/mercado-pago/webhook?onboarding_attempt='
            .$attempt->uuid.'&data[id]='.$paymentId;
    }

    protected function platformWebhookUrl(string $paymentId): string
    {
        return 'http://payments.zigo.local/api/payments/mercado-pago/platform/webhook?data[id]='.$paymentId;
    }

    protected function payment(PlatformPaymentAttempt $attempt, string $id, string $status): array
    {
        return ['id'=>$id,'status'=>$status,'external_reference'=>$attempt->external_reference,'transaction_amount'=>(string)$attempt->amount,'currency_id'=>$attempt->currency,'collector_id'=>'123456'];
    }

    protected function assertOperationalCounts(int $expected): void
    {
        foreach (['network_tenants','users','network_tenant_memberships','network_subscriptions','network_tenant_brandings','network_tenant_domains'] as $table) {
            $this->assertSame($expected, DB::table($table)->count(), $table);
        }
    }

    protected function schema(): void
    {
        foreach (['tenant_api_quota_policies','tenant_operation_allowances','platform_payment_events','platform_payment_attempts','tenant_saas_orders','saas_onboarding_events','saas_onboarding_applications','network_entitlement_capacities','network_subscription_events','network_entitlements','network_subscriptions','network_tenant_memberships','network_tenant_brandings','network_tenant_domains','network_commercial_products','network_plan_modules','network_tenants','network_plans','network_modules','users','empresas'] as $table) Schema::dropIfExists($table);
        Schema::create('empresas',function(Blueprint$t){$t->id();$t->timestamps();$t->boolean('estatus')->default(1);$t->string('contacto',50);$t->string('nombre',50);$t->string('email')->nullable()->unique();$t->string('telefono',10);});
        Schema::create('users',function(Blueprint$t){$t->id();$t->string('name');$t->string('apellido_paterno')->nullable();$t->string('apellido_materno')->nullable();$t->string('rfc')->nullable();$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();});
        Schema::create('network_modules',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
        Schema::create('network_plans',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
        Schema::create('network_tenants',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps();});
        Schema::create('network_plan_modules',function(Blueprint$t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();$t->unique(['plan_id','module_id']);});
        Schema::create('network_tenant_domains',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->string('domain')->unique();$t->string('type');$t->string('environment');$t->boolean('is_primary');$t->string('status');$t->timestamp('verified_at')->nullable();$t->timestamps();});
        Schema::create('network_tenant_brandings',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id')->unique();$t->string('brand_name')->nullable();$t->string('logo_path')->nullable();$t->string('primary_color',7)->nullable();$t->string('secondary_color',7)->nullable();$t->string('accent_color',7)->nullable();$t->string('favicon_path')->nullable();$t->string('support_email')->nullable();$t->string('support_phone')->nullable();$t->timestamps();});
        Schema::create('network_tenant_memberships',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps();$t->unique(['tenant_id','user_id']);});
        Schema::create('network_subscriptions',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');$t->unsignedInteger('operations_limit')->nullable();foreach(['started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at']as$c)$t->timestamp($c)->nullable();$t->timestamps();});
        Schema::create('network_entitlements',function(Blueprint$t){$t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('module_id');$t->string('code');$t->boolean('is_enabled');$t->unsignedInteger('limit_value')->nullable();$t->string('source');$t->timestamps();$t->unique(['subscription_id','module_id']);$t->unique(['subscription_id','code']);});
        Schema::create('network_entitlement_capacities',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('entitlement_id');$t->string('capability_code');$t->unsignedInteger('quantity');$t->string('source');$t->string('source_key');$t->boolean('is_enabled')->default(true);$t->timestamps();$t->unique(['entitlement_id','capability_code','source','source_key']);});
        Schema::create('network_subscription_events',function(Blueprint$t){$t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('actor_user_id')->nullable();$t->string('event');$t->string('from_status')->nullable();$t->string('to_status')->nullable();$t->json('metadata')->nullable();$t->timestamp('created_at')->nullable();});
        Schema::create('network_commercial_products',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->string('billing_type');$t->decimal('price',12,2);$t->char('currency',3);$t->unsignedBigInteger('module_id')->nullable();$t->unsignedBigInteger('plan_id')->nullable();$t->unsignedInteger('included_operations')->nullable();$t->boolean('is_active');$t->unsignedInteger('sort_order')->default(0);$t->json('metadata')->nullable();$t->timestamps();});
        (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up();
        (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up();
        Schema::table('saas_onboarding_applications',function(Blueprint$t){$t->unsignedBigInteger('legacy_empresa_id')->nullable();$t->boolean('owner_is_new')->nullable();});
        Schema::create('tenant_saas_orders',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('onboarding_application_id')->nullable()->unique();$t->unsignedBigInteger('commercial_product_id');$t->unsignedBigInteger('created_by_user_id');$t->string('purchase_key');$t->string('status');$t->string('payment_status');$t->unsignedInteger('quantity');$t->decimal('unit_amount',12,2);$t->decimal('subtotal',12,2);$t->decimal('tax_amount',12,2);$t->decimal('total_amount',12,2);$t->char('currency',3);$t->json('purchase_snapshot');$t->string('payment_provider')->nullable();$t->string('payment_reference')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('paid_at')->nullable();$t->timestamp('activated_at')->nullable();$t->timestamps();});
        Schema::create('platform_payment_attempts',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id')->nullable();$t->unsignedBigInteger('saas_order_id')->nullable();$t->unsignedBigInteger('onboarding_application_id')->nullable();$t->string('provider');$t->string('status');$t->string('provider_preference_id')->nullable();$t->string('provider_payment_id')->nullable();$t->string('external_reference')->unique();$t->decimal('amount',12,2);$t->char('currency',3);$t->text('init_point')->nullable();$t->timestamp('approved_at')->nullable();$t->timestamp('rejected_at')->nullable();$t->timestamps();$t->unique(['provider','provider_payment_id']);});
        Schema::create('platform_payment_events',function(Blueprint$t){$t->id();$t->string('provider');$t->string('event_key');$t->unsignedBigInteger('platform_payment_attempt_id')->nullable();$t->string('provider_payment_id')->nullable();$t->string('status');$t->string('error_code')->nullable();$t->timestamp('received_at');$t->timestamp('processed_at')->nullable();$t->timestamps();$t->unique(['provider','event_key']);});
        Schema::create('tenant_operation_allowances',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('saas_order_id')->unique();$t->unsignedInteger('operations');$t->timestamp('starts_at');$t->timestamp('expires_at');$t->timestamps();});
        Schema::create('tenant_api_quota_policies',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedInteger('monthly_request_limit');$t->unsignedInteger('rate_limit_per_minute');$t->timestamp('valid_from');$t->timestamp('valid_until')->nullable();$t->unsignedBigInteger('source_saas_order_id')->nullable();$t->timestamps();});
    }
}
