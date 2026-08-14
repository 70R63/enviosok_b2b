<?php

namespace App\Console\Commands;

use App\Domain\Payments\Models\TenantPaymentAttempt;
use App\Domain\Payments\Models\TenantPaymentEvent;
use App\Domain\Payments\VerifiedTenantPaymentService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class StagePaymentApprove extends Command
{
    protected $signature = 'zigo:stage-payment-approve {attempt : Attempt ID, UUID or external_reference} {--confirm-stage : Confirm the stage-only simulation}';
    protected $description = 'Runs a provider-approved sandbox payment through the real ZIGO fulfillment pipeline.';

    public function handle(VerifiedTenantPaymentService $payments): int
    {
        if (! app()->environment(['stage', 'staging', 'local', 'testing'])) {
            $this->error('Stage payment simulation is disabled in this environment.');
            return self::FAILURE;
        }
        if (! $this->option('confirm-stage')) {
            $this->error('The --confirm-stage option is required.');
            return self::FAILURE;
        }

        try {
            $attempt = $this->resolveAttempt((string) $this->argument('attempt'));
            $attempt->loadMissing(['connection', 'checkout.operation']);

            if ($attempt->status === 'APPROVED' && $attempt->checkout?->status === 'PAID' && $this->shipmentExists($attempt)) {
                $this->warn('Attempt already fulfilled.');
                return self::SUCCESS;
            }

            $this->assertEligible($attempt);
            $providerPaymentId = 'SIM-STAGE-'.$attempt->uuid;
            $payment = [
                'id' => $providerPaymentId,
                'status' => 'approved',
                'external_reference' => $attempt->external_reference,
                'transaction_amount' => $attempt->amount,
                'currency_id' => $attempt->currency,
                'collector_id' => $attempt->connection->provider_account_id,
                'live_mode' => false,
            ];

            DB::transaction(function () use ($payments, $attempt, $payment, $providerPaymentId): void {
                $payments->process($attempt, $payment);
                TenantPaymentEvent::firstOrCreate(
                    ['provider' => 'MERCADO_PAGO', 'event_key' => 'stage-simulation:'.$attempt->uuid],
                    [
                        'payment_attempt_id' => $attempt->id,
                        'event_type' => 'STAGE_SIMULATION',
                        'provider_payment_id' => $providerPaymentId,
                        'status' => 'PROCESSED',
                        'received_at' => now(),
                        'processed_at' => now(),
                    ]
                );
            });

            Log::notice('Stage payment simulation fulfilled', [
                'source' => 'STAGE_SIMULATION',
                'actor' => 'CLI',
                'provider' => 'MERCADO_PAGO',
                'real_payment' => false,
                'attempt_uuid' => $attempt->uuid,
                'tenant_id' => $attempt->tenant_id,
                'provider_payment_id' => $providerPaymentId,
            ]);
            $this->info('Stage payment fulfilled through the real pipeline.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveAttempt(string $identifier): TenantPaymentAttempt
    {
        $matches = TenantPaymentAttempt::query()
            ->where(function ($query) use ($identifier): void {
                if (ctype_digit($identifier)) $query->orWhere('id', (int) $identifier);
                $query->orWhere('uuid', $identifier)->orWhere('external_reference', $identifier);
            })->limit(2)->get();

        if ($matches->count() !== 1) throw new RuntimeException('Payment attempt not found or ambiguous.');
        return $matches->first();
    }

    private function assertEligible(TenantPaymentAttempt $attempt): void
    {
        $checkout = $attempt->checkout;
        $connection = $attempt->connection;
        if ($attempt->provider !== 'MERCADO_PAGO') throw new RuntimeException('Attempt provider is not MERCADO_PAGO.');
        if (! in_array($attempt->status, ['CREATED', 'PENDING'], true)) throw new RuntimeException('Attempt status is not eligible.');
        if (! $checkout) throw new RuntimeException('Checkout does not exist.');
        if ((int) $checkout->tenant_id !== (int) $attempt->tenant_id) throw new RuntimeException('Checkout tenant mismatch.');
        if (! $connection || (int) $connection->tenant_id !== (int) $attempt->tenant_id || $connection->provider !== 'MERCADO_PAGO') throw new RuntimeException('Payment connection tenant mismatch.');
        $connectionEnvironment = strtolower((string) ($connection->metadata['environment'] ?? config('zigo_payments.providers.mercado_pago.environment')));
        if ($connectionEnvironment !== 'sandbox') throw new RuntimeException('Payment connection is not sandbox.');
        if (! filled($attempt->provider_preference_id)) throw new RuntimeException('Provider preference is missing.');
        if (filled($attempt->provider_payment_id)) throw new RuntimeException('Provider payment already exists.');
        if (! filled($attempt->external_reference)) throw new RuntimeException('External reference is missing.');
        if (! filled($attempt->amount) || ! filled($attempt->currency)) throw new RuntimeException('Amount or currency is missing.');
        if (bccomp((string) $attempt->amount, (string) $checkout->total_amount, 2) !== 0) throw new RuntimeException('Attempt amount does not match checkout.');
        if ((string) $attempt->currency !== (string) $checkout->currency) throw new RuntimeException('Attempt currency does not match checkout.');
        if (! filled($connection->provider_account_id)) throw new RuntimeException('Seller account is missing.');
        if ($checkout->status === 'PAID') throw new RuntimeException('Checkout is already paid.');
        if ($this->shipmentExists($attempt)) throw new RuntimeException('Shipment already exists.');
    }

    private function shipmentExists(TenantPaymentAttempt $attempt): bool
    {
        $operationId = $attempt->checkout?->tenant_operation_id;
        return $operationId && LocalShipment::where('tenant_operation_id', $operationId)->exists();
    }
}
