<?php

namespace App\Domain\Network\Commerce;

use App\Domain\Network\Commerce\Contracts\PlatformPaymentProvider;
use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt, PlatformPaymentEvent, TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\OnboardingStateService;
use App\Jobs\ProvisionSaasOnboardingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PlatformPaymentService
{
    public function __construct(
        private PlatformPaymentProvider $provider,
        private TenantSaasActivationService $activation,
        private OnboardingStateService $onboardingStates,
    ) {}

    public function initialize(TenantSaasOrder $order): PlatformPaymentAttempt
    {
        $attempt = DB::transaction(function () use ($order): PlatformPaymentAttempt {
            $locked = TenantSaasOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'PENDING_PAYMENT' && !$locked->expires_at?->isPast(), 409);
            $old = PlatformPaymentAttempt::where('saas_order_id', $locked->id)
                ->whereIn('status', ['CREATED', 'PENDING'])->latest()->first();
            return $old ?? PlatformPaymentAttempt::create([
                'tenant_id' => $locked->tenant_id, 'saas_order_id' => $locked->id,
                'provider' => 'MERCADO_PAGO', 'status' => 'CREATED',
                'external_reference' => 'zs_'.Str::lower(Str::random(48)),
                'amount' => $locked->total_amount, 'currency' => $locked->currency,
            ]);
        });
        if ($attempt->init_point) return $attempt;
        return $this->finishInitialization($attempt, $this->provider->createCheckout($attempt, $order));
    }

    public function initializeOnboarding(SaasOnboardingApplication $application): PlatformPaymentAttempt
    {
        $attempt = DB::transaction(function () use ($application): PlatformPaymentAttempt {
            $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->status === SaasOnboardingApplication::PENDING_PAYMENT
                && $locked->commercial_snapshot_json && $locked->subdomain_reserved_until?->isFuture(),
                409,
            );
            $old = PlatformPaymentAttempt::where('onboarding_application_id', $locked->id)
                ->whereIn('status', ['CREATED', 'PENDING'])->latest()->first();
            return $old ?? PlatformPaymentAttempt::create([
                'onboarding_application_id' => $locked->id,
                'provider' => 'MERCADO_PAGO', 'status' => 'CREATED',
                'external_reference' => 'zo_'.Str::lower(Str::random(48)),
                'amount' => $locked->total, 'currency' => $locked->currency,
            ]);
        });
        if ($attempt->init_point) return $attempt;
        return $this->finishInitialization(
            $attempt,
            $this->provider->createOnboardingCheckout($attempt, $application),
        );
    }

    private function finishInitialization(PlatformPaymentAttempt $attempt, array $preference): PlatformPaymentAttempt
    {
        $key = config('zigo_payments.providers.mercado_pago.environment') === 'production'
            ? 'init_point' : 'sandbox_init_point';
        $url = (string) ($preference[$key] ?? $preference['init_point'] ?? '');
        abort_unless(filled($preference['id'] ?? null) && $this->safeProviderUrl($url), 502);
        $attempt->update([
            'provider_preference_id' => $preference['id'], 'init_point' => $url, 'status' => 'PENDING',
        ]);
        return $attempt->fresh();
    }

    public function webhook(Request $request): void
    {
        $paymentId = $this->webhookPaymentId($request);
        if ($paymentId === '') {
            Log::warning('ZIGO_PLATFORM_WEBHOOK_REJECT reason='.PlatformWebhookValidationResult::PAYMENT_ID_MISSING);
            abort(401);
        }

        $validation = $this->provider->webhookValidationResult($request, $paymentId);
        if ($validation !== PlatformWebhookValidationResult::VALID_SIGNATURE) {
            Log::warning('ZIGO_PLATFORM_WEBHOOK_REJECT reason='.$validation);
            abort(401);
        }
        Log::info('ZIGO_PLATFORM_WEBHOOK_SIGNATURE_VALID');

        $hintUuid = (string) ($request->query('onboarding_attempt') ?: $request->query('saas_attempt'));
        $hint = $hintUuid !== ''
            ? PlatformPaymentAttempt::where('uuid', $hintUuid)->where('provider', 'MERCADO_PAGO')->first()
            : null;
        $eventKey = (string) $request->header('x-request-id').':'.$paymentId;
        $event = PlatformPaymentEvent::firstOrCreate(
            ['provider' => 'MERCADO_PAGO', 'event_key' => $eventKey],
            [
                'platform_payment_attempt_id' => $hint?->id,
                'provider_payment_id' => $paymentId,
                'status' => 'RECEIVED', 'received_at' => now(),
            ],
        );
        if ($event->status === 'PROCESSED') return;

        try {
            $payment = $this->provider->retrievePayment($paymentId);
            $externalReference = (string) ($payment['external_reference'] ?? '');
            $attempt = PlatformPaymentAttempt::where('provider', 'MERCADO_PAGO')
                ->where('external_reference', $externalReference)->first();
            if (!$attempt || ($hint && !$hint->is($attempt))) {
                throw new RuntimeException('REFERENCE_MISMATCH');
            }
            $event->update([
                'platform_payment_attempt_id' => $attempt->id,
                'provider_payment_id' => $paymentId,
                'status' => 'PROCESSING', 'processed_at' => null, 'error_code' => null,
            ]);
            $this->processVerifiedPayment($attempt, $payment, $event);
        } catch (Throwable $exception) {
            $errorCode = $this->safePaymentErrorCode($exception);
            $event->update([
                'status' => 'INCONSISTENT',
                'error_code' => $errorCode,
                'processed_at' => now(),
            ]);
            Log::warning('SaaS platform payment rejected', [
                'event_key' => $eventKey,
                'attempt_uuid' => $event->attempt?->uuid,
                'reason' => $errorCode,
            ]);
        }
    }

    public function reconcileOnboardingReturn(
        SaasOnboardingApplication $application,
        string $providerPaymentId,
    ): ?PlatformPaymentEvent {
        $providerPaymentId = trim($providerPaymentId);
        if ($providerPaymentId === '' || strlen($providerPaymentId) > 128
            || !preg_match('/^[A-Za-z0-9._-]+$/D', $providerPaymentId)) {
            return null;
        }

        $attempt = PlatformPaymentAttempt::query()
            ->where('onboarding_application_id', $application->id)
            ->where('provider', 'MERCADO_PAGO')
            ->whereIn('status', ['CREATED', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELED'])
            ->latest('id')
            ->first();
        if (!$attempt) {
            Log::warning('ZIGO_PLATFORM_RETURN_RECONCILIATION_REJECT reason=ATTEMPT_NOT_FOUND');
            return null;
        }

        $event = PlatformPaymentEvent::firstOrCreate(
            [
                'provider' => 'MERCADO_PAGO',
                'event_key' => 'return:'.$attempt->uuid.':'.$providerPaymentId,
            ],
            [
                'platform_payment_attempt_id' => $attempt->id,
                'provider_payment_id' => $providerPaymentId,
                'status' => 'RECEIVED',
                'received_at' => now(),
            ],
        );
        if ($event->status === 'PROCESSED') {
            return $event;
        }

        try {
            $payment = $this->provider->retrievePayment($providerPaymentId);
            $this->processVerifiedPayment($attempt, $payment, $event);
        } catch (Throwable $exception) {
            $errorCode = $this->safePaymentErrorCode($exception);
            $event->update([
                'status' => 'INCONSISTENT',
                'error_code' => $errorCode,
                'processed_at' => now(),
            ]);
            Log::warning('ZIGO_PLATFORM_RETURN_RECONCILIATION_REJECT reason='.$errorCode);
        }

        return $event->fresh();
    }

    private function webhookPaymentId(Request $request): string
    {
        foreach ([$request->input('data.id'), $request->query('data_id'), $request->query('data.id')] as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    /**
     * Applies a provider payment after its payload has been verified.
     * Both real webhooks and the guarded STAGE simulator use this authority.
     */
    public function processVerifiedPayment(
        PlatformPaymentAttempt $attempt,
        array $payment,
        PlatformPaymentEvent $event,
    ): void {
        $this->validate($attempt, $payment);

        $status = strtolower((string) ($payment['status'] ?? ''));
        if ($attempt->onboarding_application_id) {
            $this->processOnboardingPayment($attempt, $event, $payment, $status);
        } else {
            $this->processSaasOrderPayment($attempt, $event, $payment, $status);
        }
    }

    private function processOnboardingPayment(
        PlatformPaymentAttempt $attempt,
        PlatformPaymentEvent $event,
        array $payment,
        string $status,
    ): void {
        $dispatchId = null;
        DB::transaction(function () use ($attempt, $event, $payment, $status, &$dispatchId): void {
            $lockedEvent = PlatformPaymentEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($lockedEvent->status === 'PROCESSED') return;
            $locked = PlatformPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $providerPaymentId = (string) $payment['id'];
            $this->assertPaymentIdAvailable($locked, $providerPaymentId);

            if ($locked->status === 'APPROVED'
                && $locked->provider_payment_id === $providerPaymentId) {
                $lockedEvent->update([
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                    'error_code' => null,
                ]);
                return;
            }

            if ($status === 'approved') {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => 'APPROVED', 'approved_at' => $locked->approved_at ?? now(),
                ]);
                $onboarding = SaasOnboardingApplication::whereKey($locked->onboarding_application_id)
                    ->lockForUpdate()->firstOrFail();
                if (in_array($onboarding->status, [
                    SaasOnboardingApplication::PENDING_PAYMENT,
                    SaasOnboardingApplication::EXPIRED,
                ], true)) {
                    $onboarding = $this->onboardingStates->transition(
                        $onboarding,
                        SaasOnboardingApplication::PAID,
                        'PAYMENT_VERIFIED',
                        'verified_payment',
                        correlationKey: 'mp:'.$providerPaymentId,
                        paymentEventId: $lockedEvent->id,
                        metadata: ['provider' => 'MERCADO_PAGO'],
                    );
                    $dispatchId = $onboarding->id;
                }
            } elseif (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => $status === 'rejected' ? 'REJECTED' : 'CANCELED',
                    'rejected_at' => now(),
                ]);
            } else {
                $locked->update(['provider_payment_id' => $providerPaymentId, 'status' => 'PENDING']);
            }
            $lockedEvent->update(['status' => 'PROCESSED', 'processed_at' => now(), 'error_code' => null]);
        });

        if ($dispatchId) {
            DB::afterCommit(fn () => ProvisionSaasOnboardingJob::dispatch($dispatchId));
        }
    }

    private function processSaasOrderPayment(
        PlatformPaymentAttempt $attempt,
        PlatformPaymentEvent $event,
        array $payment,
        string $status,
    ): void {
        DB::transaction(function () use ($attempt, $event, $payment, $status): void {
            $locked = PlatformPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $providerPaymentId = (string) $payment['id'];
            $this->assertPaymentIdAvailable($locked, $providerPaymentId);
            if ($status === 'approved') {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => 'APPROVED', 'approved_at' => now(),
                ]);
                $order = TenantSaasOrder::whereKey($locked->saas_order_id)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'ACTIVATED') {
                    $order->update([
                        'status' => 'PAID', 'payment_status' => 'APPROVED',
                        'payment_provider' => 'MERCADO_PAGO',
                        'payment_reference' => $providerPaymentId, 'paid_at' => now(),
                    ]);
                    $this->activation->activate($order->fresh());
                }
            } elseif (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => $status === 'rejected' ? 'REJECTED' : 'CANCELED',
                    'rejected_at' => now(),
                ]);
                $locked->order()->update(['payment_status' => $status === 'rejected' ? 'REJECTED' : 'CANCELED']);
            } else {
                $locked->update(['provider_payment_id' => $providerPaymentId, 'status' => 'PENDING']);
            }
            $event->update(['status' => 'PROCESSED', 'processed_at' => now(), 'error_code' => null]);
        });
    }

    private function assertPaymentIdAvailable(PlatformPaymentAttempt $attempt, string $providerPaymentId): void
    {
        if ($providerPaymentId === ''
            || ($attempt->provider_payment_id !== null && $attempt->provider_payment_id !== $providerPaymentId)
            || PlatformPaymentAttempt::where('provider_payment_id', $providerPaymentId)
            ->whereKeyNot($attempt->id)->exists()) {
            throw new RuntimeException('PAYMENT_ID_CONFLICT');
        }
    }

    private function validate(PlatformPaymentAttempt $attempt, array $payment): void
    {
        if ((string) ($payment['id'] ?? '') === ''
            || (string) ($payment['external_reference'] ?? '') !== $attempt->external_reference) {
            throw new RuntimeException('REFERENCE_MISMATCH');
        }
        if (bccomp((string) ($payment['transaction_amount'] ?? ''), (string) $attempt->amount, 2) !== 0) {
            throw new RuntimeException('AMOUNT_MISMATCH');
        }
        if ((string) ($payment['currency_id'] ?? '') !== $attempt->currency) {
            throw new RuntimeException('CURRENCY_MISMATCH');
        }
        if ((string) ($payment['collector_id'] ?? '') !== $this->provider->accountId()) {
            throw new RuntimeException('PLATFORM_ACCOUNT_MISMATCH');
        }
    }

    private function safeProviderUrl(string $url): bool
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https'
            && preg_match('/(^|\.)mercadopago\.com(\.mx)?$/i', (string) ($parts['host'] ?? ''));
    }

    private function safePaymentErrorCode(Throwable $exception): string
    {
        $allowed = [
            'REFERENCE_MISMATCH', 'AMOUNT_MISMATCH', 'CURRENCY_MISMATCH',
            'PLATFORM_ACCOUNT_MISMATCH', 'PAYMENT_ID_CONFLICT',
        ];
        $message = Str::upper(trim($exception->getMessage()));
        return in_array($message, $allowed, true) ? $message : 'PAYMENT_VERIFICATION_ERROR';
    }
}
