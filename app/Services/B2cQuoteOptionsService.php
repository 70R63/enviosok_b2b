<?php

namespace App\Services;

use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Log;
use Throwable;

class B2cQuoteOptionsService
{
    public function __construct(private ZigoProviderRateService $national, private TenantProviderQuoteSource $local) {}

    public function options(B2cCotizacion $quote, callable $priceNational, string $segment): array
    {
        $national = [];
        try {
            $base = $this->national->getOptionsForCotizacion($quote);
        } catch (Throwable $exception) {
            Log::warning('B2C - proveedor de tarifas no disponible', ['cotizacion_id' => $quote->id,
                'exception' => get_class($exception)]);
            session()->flash('rate_error', 'No fue posible obtener tarifas de Estafeta en este momento. Intenta nuevamente.');
            $base = [];
        }
        foreach ($base as $option) {
            try {
                $national[] = $priceNational($option);
            } catch (Throwable $exception) {
                Log::warning('B2C national commercial quote unavailable', ['exception' => get_class($exception)]);
            }
        }
        try {
            $local = $this->local->options($quote, $segment);
        } catch (Throwable $exception) {
            Log::warning('B2C local quote source unavailable', ['exception' => get_class($exception)]);
            $local = [];
        }

        return array_merge($national, $local);
    }
}
