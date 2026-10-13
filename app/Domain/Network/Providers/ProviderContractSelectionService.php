<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShippingPricingRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderContractSelectionService
{
    public function __construct(private ProviderConsumerResolver $consumers, private ProviderQuoteOwnership $ownership,
        private ProviderConnectionService $connections, private LocalProviderEligibility $eligibility) {}

    private function reject(): never
    {
        throw ValidationException::withMessages(['provider_snapshot_uuid' => 'La oferta ya no es válida. Cotiza nuevamente.']);
    }

    public function assertCheckoutScope(B2cCotizacion $quote): void
    {
        if ($quote->provider !== 'tenant_provider_local') {
            return;
        }
        $consumer = $this->consumers->current();
        $offer = $quote->providerContract?->snapshot;
        if (! $consumer || ! $offer || (int) $consumer->id !== (int) $offer->consumer_tenant_id
            || ! $this->ownership->allows($quote)) {
            abort(403);
        }
    }

    public function select(B2cCotizacion $quote, string $uuid): ProviderContract
    {
        $consumer = $this->consumers->current();
        if (! $consumer) {
            $this->reject();
        }

        return DB::transaction(function () use ($quote, $uuid, $consumer) {
            $lockedQuote = B2cCotizacion::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if (! $this->ownership->allows($lockedQuote)) {
                $this->reject();
            }
            $offer = ProviderQuoteSnapshot::where('uuid', $uuid)->where('consumer_tenant_id', $consumer->id)
                ->where('b2c_cotizacion_id', $lockedQuote->id)->first();
            if (! $offer || ! hash_equals($offer->owner_binding, $this->ownership->binding())
                || ! hash_equals($offer->request_fingerprint, $this->ownership->fingerprint($lockedQuote))
                || $offer->expires_at->lte(now()) || $offer->valid_from?->gt(now())
                || $offer->capability !== 'LOCAL_SHIPPING' || $offer->quote_source !== 'tenant_provider_local') {
                $this->reject();
            }
            Tenant::whereIn('id', [$consumer->id, $offer->provider_tenant_id])->orderBy('id')->lockForUpdate()->get();
            $connection = ProviderConnection::whereKey($offer->provider_connection_id)->lockForUpdate()->first();
            $provider = Tenant::find($offer->provider_tenant_id);
            if (! $connection || ! $provider || (int) $connection->consumer_tenant_id !== (int) $consumer->id
                || (int) $connection->provider_tenant_id !== (int) $offer->provider_tenant_id
                || ! $this->connections->canConsume($consumer, $provider, 'LOCAL_SHIPPING')) {
                $this->reject();
            }
            $grant = DB::table('network_provider_connection_services')->where('connection_id', $connection->id)
                ->where('provider_tenant_id', $provider->id)->where('capability', 'LOCAL_SHIPPING')
                ->where('local_shipping_service_id', $offer->local_shipping_service_id)->lockForUpdate()->first();
            $service = LocalShippingService::whereKey($offer->local_shipping_service_id)->where('tenant_id', $provider->id)->lockForUpdate()->first();
            if (! $grant || ! $service || ! $this->connections->authorizedLocalServices($consumer, $provider)->contains('id', $service->id)
                || $service->pricing_strategy !== $offer->service_snapshot['pricing_strategy']
                || ! $this->eligibility->allows($service, $lockedQuote, true)) {
                $this->reject();
            }
            $tariff = LocalShippingPricingRule::whereKey($offer->local_shipping_pricing_rule_id)->where('tenant_id', $provider->id)
                ->where('service_id', $service->id)->where('active', true)
                ->where(fn ($q) => $q->whereNull('package_type')->orWhere('package_type', $offer->package_snapshot['type']))
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))->lockForUpdate()->first();
            if (! $tariff || $offer->expires_at->lte(now())
                || ($service->pricing_strategy === 'PACKAGE_FLAT' && $tariff->package_type !== $offer->package_snapshot['type'])) {
                $this->reject();
            }
            $existing = ProviderContract::where('b2c_cotizacion_id', $lockedQuote->id)->first();
            if ($existing) {
                if ((int) $existing->quote_snapshot_id !== (int) $offer->id) {
                    $this->reject();
                }

                return $existing;
            }
            if (! in_array($lockedQuote->estatus, ['COTIZADA', 'SELECCIONADA'], true) || $lockedQuote->hasAccreditedPayment()
                || $lockedQuote->hasGeneratedGuide() || ! empty($lockedQuote->logistico)) {
                $this->reject();
            }
            $contract = ProviderContract::forceCreate(['quote_snapshot_id' => $offer->id, 'b2c_cotizacion_id' => $lockedQuote->id,
                'selected_by_user_id' => auth()->id(), 'selected_at' => now()]);
            $rule = $offer->commercial_rule_snapshot;
            $lockedQuote->forceFill(['logistico' => $offer->service_snapshot['provider_name'], 'servicio' => $offer->service_snapshot['name'],
                'provider' => 'tenant_provider_local', 'carrier' => $offer->service_snapshot['carrier'],
                'service_code' => $offer->service_snapshot['code'], 'quote_source' => $offer->quote_source,
                'provider_quote_reference' => $contract->uuid, 'quote_request_fingerprint' => $offer->request_fingerprint,
                'quote_expires_at' => $offer->expires_at, 'provider_base_price' => $offer->provider_cost,
                'provider_subtotal' => $offer->provider_cost, 'provider_total' => null,
                'provider_extended_area_price' => 0,
                'precio' => $offer->total_amount, 'precio_sin_seguro' => $offer->total_amount,
                'zigo_final_price' => $offer->total_amount, 'zigo_profit_amount' => $offer->profit_amount,
                'zigo_margin_percentage' => $rule['margin_percentage'], 'zigo_fixed_fee' => $rule['fixed_fee'],
                'zigo_margin_amount' => round((float) $offer->provider_cost * (float) $rule['margin_percentage'] / 100, 2),
                'zigo_adjustment_type' => null, 'zigo_adjustment_value' => null, 'zigo_adjustment_amount' => 0,
                'zigo_discount_type' => null, 'zigo_discount_value' => null, 'zigo_discount_amount' => 0,
                'zigo_pricing_adjustment_id' => null, 'zigo_client_pricing_rule_id' => null,
                'zigo_pricing_rule_id' => $rule['id'], 'zigo_customer_segment' => $rule['customer_segment'],
                'estatus' => 'SELECCIONADA'])->save();

            return $contract;
        }, 5);
    }
}
