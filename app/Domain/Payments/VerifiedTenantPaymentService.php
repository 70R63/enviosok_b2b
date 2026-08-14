<?php

namespace App\Domain\Payments;

use App\Domain\Network\Channels\B2C\CustomerCheckoutFulfillmentService;
use App\Domain\Payments\Models\TenantPaymentAttempt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VerifiedTenantPaymentService
{
    public function __construct(private CustomerCheckoutFulfillmentService $fulfillment) {}

    /** Process payment data that has already been retrieved from the trusted provider. */
    public function process(TenantPaymentAttempt $attempt, array $payment): TenantPaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $payment): TenantPaymentAttempt {
            $locked = TenantPaymentAttempt::with(['connection', 'checkout'])
                ->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $this->validate($locked, $payment);
            $providerPaymentId = (string) $payment['id'];
            $conflict = TenantPaymentAttempt::where('provider_payment_id', $providerPaymentId)
                ->whereKeyNot($locked->id)->exists();
            if ($conflict) throw new RuntimeException('PAYMENT_ID_CONFLICT');

            $status = strtolower((string) ($payment['status'] ?? ''));
            if ($status === 'approved') {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                ]);
                $this->fulfillment->approve($locked->checkout, 'MERCADO_PAGO', $providerPaymentId);
            } elseif (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
                $locked->update([
                    'provider_payment_id' => $providerPaymentId,
                    'status' => strtoupper($status === 'cancelled' ? 'CANCELED' : $status),
                    'rejected_at' => now(),
                ]);
            } else {
                $locked->update(['provider_payment_id' => $providerPaymentId, 'status' => 'PENDING']);
            }

            return $locked->fresh();
        });
    }

    private function validate(TenantPaymentAttempt $attempt, array $payment): void
    {
        if ($attempt->provider !== 'MERCADO_PAGO') throw new RuntimeException('PROVIDER_MISMATCH');
        if (! $attempt->checkout || (int) $attempt->checkout->tenant_id !== (int) $attempt->tenant_id) {
            throw new RuntimeException('CHECKOUT_TENANT_MISMATCH');
        }
        if (! $attempt->connection || (int) $attempt->connection->tenant_id !== (int) $attempt->tenant_id ||
            $attempt->connection->provider !== 'MERCADO_PAGO') {
            throw new RuntimeException('CONNECTION_TENANT_MISMATCH');
        }
        if (bccomp((string) $attempt->amount, (string) $attempt->checkout->total_amount, 2) !== 0) {
            throw new RuntimeException('CHECKOUT_AMOUNT_MISMATCH');
        }
        if ((string) $attempt->currency !== (string) $attempt->checkout->currency) {
            throw new RuntimeException('CHECKOUT_CURRENCY_MISMATCH');
        }
        if ((string) ($payment['id'] ?? '') === '' ||
            (string) ($payment['external_reference'] ?? '') !== $attempt->external_reference) {
            throw new RuntimeException('REFERENCE_MISMATCH');
        }
        if (bccomp((string) ($payment['transaction_amount'] ?? ''), (string) $attempt->amount, 2) !== 0) {
            throw new RuntimeException('AMOUNT_MISMATCH');
        }
        if ((string) ($payment['currency_id'] ?? '') !== $attempt->currency) {
            throw new RuntimeException('CURRENCY_MISMATCH');
        }
        if ((string) ($payment['collector_id'] ?? '') !== (string) $attempt->connection->provider_account_id) {
            throw new RuntimeException('SELLER_MISMATCH');
        }
    }
}
