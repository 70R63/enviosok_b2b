<?php

namespace App\Console\Commands;

use App\Domain\Network\Commerce\Contracts\PlatformPaymentProvider;
use App\Domain\Network\Commerce\Models\{PlatformPaymentAttempt, PlatformPaymentEvent};
use App\Domain\Network\Commerce\PlatformPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class StagePlatformPaymentApprove extends Command
{
    protected $signature = 'zigo:stage-platform-payment-approve {attempt : Platform attempt ID, UUID or external_reference} {--confirm-stage : Confirm the stage-only simulation}';
    protected $description = 'Approves a platform SaaS payment through the real pipeline without calling Mercado Pago.';

    public function handle(PlatformPaymentService $payments, PlatformPaymentProvider $provider): int
    {
        if (! app()->environment(['stage', 'staging', 'local', 'testing'])) {
            $this->error('Platform payment simulation is disabled in this environment.');
            return self::FAILURE;
        }
        if (! $this->option('confirm-stage')) {
            $this->error('The --confirm-stage option is required.');
            return self::FAILURE;
        }

        try {
            $attempt = $this->resolveAttempt((string) $this->argument('attempt'));
            $attempt->load(['order', 'onboarding']);

            if ($attempt->status === 'APPROVED') {
                $this->info('Attempt already fulfilled.');
                return self::SUCCESS;
            }

            $this->assertEligible($attempt);
            if (! filled($provider->accountId())) {
                throw new RuntimeException('Platform collector account is not configured.');
            }
            $providerPaymentId = 'SIM-STAGE-PLATFORM-'.$attempt->uuid;
            $payment = [
                'id' => $providerPaymentId,
                'status' => 'approved',
                'external_reference' => $attempt->external_reference,
                'transaction_amount' => $attempt->amount,
                'currency_id' => $attempt->currency,
                'collector_id' => $provider->accountId(),
                'live_mode' => false,
            ];

            $event = PlatformPaymentEvent::firstOrCreate(
                ['provider' => 'MERCADO_PAGO', 'event_key' => 'stage-platform-simulation:'.$attempt->uuid],
                [
                    'platform_payment_attempt_id' => $attempt->id,
                    'provider_payment_id' => $providerPaymentId,
                    'status' => 'RECEIVED',
                    'received_at' => now(),
                ],
            );
            if ($event->status === 'PROCESSED') {
                $this->info('Attempt already fulfilled.');
                return self::SUCCESS;
            }

            $payments->processVerifiedPayment($attempt, $payment, $event);

            Log::notice('Stage platform payment simulation fulfilled', [
                'source' => 'STAGE_SIMULATION',
                'actor' => 'CLI',
                'provider' => 'MERCADO_PAGO',
                'real_payment' => false,
                'attempt_uuid' => $attempt->uuid,
                'provider_payment_id' => $providerPaymentId,
            ]);
            $this->info('Stage platform payment fulfilled through the real pipeline.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveAttempt(string $identifier): PlatformPaymentAttempt
    {
        $matches = PlatformPaymentAttempt::query()
            ->where(function ($query) use ($identifier): void {
                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
                $query->orWhere('uuid', $identifier)->orWhere('external_reference', $identifier);
            })->limit(2)->get();

        if ($matches->count() !== 1) {
            throw new RuntimeException('Platform payment attempt not found or ambiguous.');
        }

        return $matches->first();
    }

    private function assertEligible(PlatformPaymentAttempt $attempt): void
    {
        if ($attempt->provider !== 'MERCADO_PAGO') {
            throw new RuntimeException('Attempt provider is not MERCADO_PAGO.');
        }
        if (! in_array($attempt->status, ['CREATED', 'PENDING'], true)) {
            throw new RuntimeException('Attempt status is not eligible.');
        }
        if (! $attempt->order && ! $attempt->onboarding) {
            throw new RuntimeException('Attempt ownership is missing.');
        }
        if ($attempt->order && $attempt->onboarding) {
            throw new RuntimeException('Attempt ownership is ambiguous.');
        }
        if ($attempt->order && (int) $attempt->order->tenant_id !== (int) $attempt->tenant_id) {
            throw new RuntimeException('Order tenant mismatch.');
        }
        if ($attempt->onboarding && $attempt->tenant_id !== null) {
            throw new RuntimeException('Onboarding attempt must not have a tenant.');
        }
        if (! filled($attempt->provider_preference_id)) {
            throw new RuntimeException('Provider preference is missing.');
        }
        if (filled($attempt->provider_payment_id)) {
            throw new RuntimeException('Provider payment already exists.');
        }
        if (! filled($attempt->external_reference)) {
            throw new RuntimeException('External reference is missing.');
        }
        if (! filled($attempt->amount) || (float) $attempt->amount <= 0 || ! filled($attempt->currency)) {
            throw new RuntimeException('Amount or currency is missing or invalid.');
        }
    }
}
