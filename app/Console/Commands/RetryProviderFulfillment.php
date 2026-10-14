<?php

namespace App\Console\Commands;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Models\B2cCotizacion;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RetryProviderFulfillment extends Command
{
    protected $signature = 'network:provider-fulfillment:retry {--quote= : Persisted B2C quote ID} {--limit=100}';

    protected $description = 'Retry verified local provider purchases without charging or dispatching externally';

    public function handle(ProviderFulfillmentService $fulfillments): int
    {
        $failed = false;
        $quotes = B2cCotizacion::where('provider', 'tenant_provider_local')->where('payment_verification_status', 'VERIFIED')
            ->whereNotNull('payment_verified_at')->whereIn('estatus', ['PAGADA', 'GUIA_GENERADA'])
            ->when($this->option('quote'), fn ($q) => $q->whereKey($this->option('quote')))
            ->whereHas('providerContract')
            ->when(! $this->option('quote'), fn ($q) => $q->where(function ($q): void {
                $q->whereHas('providerContract', fn ($q) => $q->whereNotIn('id', ProviderFulfillment::select('provider_contract_id')))
                    ->orWhere(fn ($q) => $q->whereNull('guide_generated_email_sent_at')->where(function ($q): void {
                        $q->where(fn ($q) => $q->whereNotNull('remitente_email')->where('remitente_email', '<>', ''))
                            ->orWhereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''));
                    }));
            }))
            // Old failed rows rotate behind unattempted/older rows instead of starving a batch.
            ->orderBy('provider_fulfillment_attempted_at')->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get();
        foreach ($quotes as $quote) {
            B2cCotizacion::whereKey($quote->id)->update(['provider_fulfillment_attempted_at' => now()]);
            try {
                $fulfillments->fulfill($quote);
                $email = (string) ($quote->user?->email ?: $quote->remitente_email);
                if ($email !== '') {
                    app(GuideRecoveryService::class)->guideAvailable($quote->fresh(), $email);
                }
                $this->line('Fulfilled quote '.$quote->id);
            } catch (\Throwable $exception) {
                $failed = true;
                Log::warning('provider_fulfillment.retry_pending', ['quote_id' => $quote->id, 'exception' => get_class($exception),
                    'reason' => ProviderFulfillmentService::failureCode($exception)]);
                $this->warn('Quote '.$quote->id.' remains pending review.');
            }
        }

        return $failed ? 1 : 0;
    }
}
