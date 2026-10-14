<?php

namespace App\Domain\Network\Providers;

use App\Models\B2cCotizacion;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProviderPaymentObserver
{
    public function saved(B2cCotizacion $quote): void
    {
        if ($quote->provider !== 'tenant_provider_local' || $quote->payment_verification_status !== 'VERIFIED'
            || ! $quote->payment_verified_at || ! $quote->wasChanged(['payment_verified_at', 'payment_verification_status', 'payment_status'])) {
            return;
        }
        $id = $quote->id;
        DB::afterCommit(static function () use ($id): void {
            try {
                B2cCotizacion::whereKey($id)->update(['provider_fulfillment_attempted_at' => now()]);
                app(ProviderFulfillmentService::class)->fulfill(B2cCotizacion::findOrFail($id));
                $fresh = B2cCotizacion::findOrFail($id);
                $email = (string) ($fresh->user?->email ?: $fresh->remitente_email);
                if ($email !== '') {
                    app(GuideRecoveryService::class)->guideAvailable($fresh, $email);
                }
            } catch (\Throwable $exception) {
                // Payment remains committed; recovery retries the same durable quote.
                Log::warning('provider_fulfillment.pending', ['quote_id' => $id, 'exception' => get_class($exception),
                    'reason' => ProviderFulfillmentService::failureCode($exception)]);
            }
        });
    }
}
