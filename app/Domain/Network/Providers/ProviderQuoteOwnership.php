<?php

namespace App\Domain\Network\Providers;

use App\Models\B2cCotizacion;
use Illuminate\Support\Str;

class ProviderQuoteOwnership
{
    public function allows(B2cCotizacion $quote): bool
    {
        if ($quote->user_id !== null) {
            return auth()->check() && (int) auth()->id() === (int) $quote->user_id;
        }

        if ($quote->referencia !== 'LANDING_PUBLICA') {
            return false;
        }
        if ((int) session('cotizacion_publica_id') === (int) $quote->id) {
            return true;
        }
        // pending_checkout_id alone is not proof of ownership of an unselected
        // offer. Resume an accepted guest contract only with its original nonce.
        if ($quote->provider === 'tenant_provider_local' && (int) session('b2c_pending_checkout_id') === (int) $quote->id) {
            $offer = $quote->providerContract?->snapshot;

            return $offer && hash_equals($offer->owner_binding, $this->binding());
        }

        return false;
    }

    public function binding(): string
    {
        if (! session()->has('tenant_provider_quote_nonce')) {
            session()->put('tenant_provider_quote_nonce', Str::random(64));
        }

        return hash('sha256', (string) session('tenant_provider_quote_nonce'));
    }

    public function input(B2cCotizacion $quote): array
    {
        return ['package' => ['type' => $quote->tipo_envio, 'weight' => (string) $quote->peso,
            'weight_real' => $quote->peso_real, 'weight_volumetric' => $quote->peso_volumetrico,
            'weight_billable' => $quote->peso_facturable, 'weight_unit' => 'kg', 'dimension_unit' => 'cm',
            'dimensions' => $quote->medidas, 'insurance' => (bool) $quote->requiere_seguro_envio],
            'origin' => ['postal_code' => $quote->cp_origen, 'settlement' => $quote->colonia_origen],
            'destination' => ['postal_code' => $quote->cp_destino, 'settlement' => $quote->colonia_destino]];
    }

    public function fingerprint(B2cCotizacion $quote): string
    {
        return hash('sha256', json_encode($this->input($quote), JSON_THROW_ON_ERROR));
    }
}
