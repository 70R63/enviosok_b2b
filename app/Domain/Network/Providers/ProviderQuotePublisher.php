<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Models\B2cCotizacion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProviderQuotePublisher
{
    public function __construct(private ProviderConsumerResolver $consumers, private ProviderQuoteOwnership $ownership) {}

    public function selectedOptions(B2cCotizacion $quote): ?array
    {
        if ($quote->provider !== 'tenant_provider_local') {
            return null;
        }
        $offer = $quote->providerContract?->snapshot;
        $consumer = $this->consumers->current();
        if (! $offer || ! $consumer || (int) $consumer->id !== (int) $offer->consumer_tenant_id || ! $this->ownership->allows($quote)) {
            return [];
        }

        return [$offer->option()];
    }

    public function publish(B2cCotizacion $quote, array $options): array
    {
        try {
            if (! $quote->exists || ! Schema::hasTable('network_provider_quote_snapshots')
                || ! Schema::hasTable('network_provider_contracts') || ! $this->ownership->allows($quote)) {
                return $options;
            }
            $consumer = $this->consumers->current();
            if (! $consumer || ProviderContract::where('b2c_cotizacion_id', $quote->id)->exists()) {
                return $options;
            }
        } catch (Throwable $exception) {
            Log::warning('Provider offer publication unavailable', ['exception' => get_class($exception)]);

            return $options;
        }
        foreach ($options as &$option) {
            if (($option['provider_source'] ?? null) !== 'tenant_provider_local') {
                continue;
            }
            try {
                $meta = $option['_tenant_provider'];
                if ((int) $meta['consumer_tenant_id'] !== (int) $consumer->id) {
                    continue;
                }
                $connection = ProviderConnection::where('consumer_tenant_id', $consumer->id)
                    ->where('provider_tenant_id', $meta['provider_tenant_id'])->where('capability', 'LOCAL_SHIPPING')->firstOrFail();
                $input = $this->ownership->input($quote);
                $expires = now()->addMinutes(30);
                foreach ([$connection->valid_to, $meta['tariff_snapshot']['valid_to'] ?? null, $meta['service_valid_to'] ?? null] as $end) {
                    if ($end && $expires->gt($end)) {
                        $expires = Carbon::parse($end);
                    }
                }
                if ($expires->lte(now())) {
                    continue;
                }
                $net = BigDecimal::of($meta['economics']['customer_price']);
                $rate = BigDecimal::of((string) config('zigo_commercial_pricing.vat_rate', 0.16));
                if ($rate->isLessThan('0') || $rate->isGreaterThan('1')) {
                    continue;
                }
                $tax = $net->multipliedBy($rate)->toScale(2, RoundingMode::HALF_UP);
                // Existing B2C money fields are DECIMAL(10,2).
                if ($net->plus($tax)->isGreaterThan('99999999.99')
                    || BigDecimal::of($meta['economics']['provider_cost'])->isGreaterThan('99999999.99')) {
                    continue;
                }
                $snapshot = ProviderQuoteSnapshot::forceCreate([
                    'b2c_cotizacion_id' => $quote->id, 'consumer_tenant_id' => $consumer->id,
                    'provider_tenant_id' => $meta['provider_tenant_id'], 'provider_connection_id' => $connection->id,
                    'local_shipping_service_id' => $meta['service_id'], 'capability' => 'LOCAL_SHIPPING',
                    'local_shipping_pricing_rule_id' => $meta['tariff_rule_id'], 'commercial_pricing_rule_id' => $meta['commercial_rule_id'],
                    'quote_source' => 'tenant_provider_local', 'request_fingerprint' => $this->ownership->fingerprint($quote),
                    'owner_binding' => $this->ownership->binding(), 'package_snapshot' => $input['package'],
                    'origin_snapshot' => $input['origin'] + ['scope' => 'postal_quote', 'city' => $quote->ciudad_origen,
                        'state' => $quote->estado_origen, 'street' => $quote->remitente_direccion, 'exterior' => $quote->remitente_num_ext],
                    'destination_snapshot' => $input['destination'] + ['scope' => 'postal_quote', 'city' => $quote->ciudad_destino,
                        'state' => $quote->estado_destino, 'street' => $quote->destinatario_direccion, 'exterior' => $quote->destinatario_num_ext],
                    'service_snapshot' => ['provider_name' => $option['logistico'], 'name' => $option['servicio'],
                        'code' => $option['service_code'], 'carrier' => $option['carrier'], 'delivery' => $option['entrega'],
                        'pricing_source' => 'local_shipping_pricing_rules', 'pricing_strategy' => $meta['pricing_strategy'], 'provider_cost_basis' => 'net'],
                    'tariff_snapshot' => $meta['tariff_snapshot'], 'commercial_rule_snapshot' => $meta['economics']['rule_snapshot'],
                    'provider_cost' => $meta['economics']['provider_cost'], 'subtotal_amount' => (string) $net,
                    'tax_rate' => (string) $rate, 'tax_amount' => (string) $tax,
                    'total_amount' => (string) $net->plus($tax), 'profit_amount' => $meta['economics']['profit_amount'],
                    'currency' => $option['currency'], 'valid_from' => now(), 'expires_at' => $expires,
                ]);
                $option = array_merge($option, $snapshot->publicMetadata(), ['provider_snapshot_uuid' => $snapshot->uuid,
                    'selection_enabled' => true, 'precio' => (float) $snapshot->total_amount,
                    'commercial_price' => (float) $snapshot->total_amount]);
            } catch (Throwable $exception) {
                Log::warning('Provider offer publication unavailable', ['exception' => get_class($exception)]);
            }
        }
        unset($option);

        return $options;
    }
}
