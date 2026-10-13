<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\PackageValidator;
use App\Domain\Shipping\Local\Pricing\LocalPricingEngine;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class LocalProviderQuoteEvaluator
{
    public function __construct(private ProviderConnectionService $connections, private PackageValidator $packages,
        private LocalPricingEngine $pricing, private LocalProviderCommercialPricing $commercial) {}

    public function options(Tenant $consumer, Tenant $provider, B2cCotizacion $quote, string $segment): array
    {
        $type = strtolower((string) $quote->tipo_envio);
        $weight = (float) $quote->peso;
        if (! in_array($type, ['caja', 'sobre'], true) || $weight <= 0 || ! is_finite($weight)
            || $quote->requiere_seguro_envio || ! preg_match('/^\d{5}$/', (string) $quote->cp_origen)
            || ! preg_match('/^\d{5}$/', (string) $quote->cp_destino)) {
            return [];
        }
        $dimensions = $type === 'caja' ? preg_split('/x/i', (string) $quote->medidas) : [null, null, null];
        if (count($dimensions) !== 3 || ($type === 'caja' && collect($dimensions)->contains(
            fn ($v) => ! is_numeric($v) || (float) $v <= 0 || ! is_finite((float) $v)))) {
            return [];
        }
        $package = ['weight' => $weight, 'length' => $dimensions[0], 'width' => $dimensions[1], 'height' => $dimensions[2]];
        $options = [];
        foreach ($this->connections->authorizedLocalServices($consumer, $provider) as $service) {
            try {
                // A CP-only public quote has no trustworthy road distance.
                if (! in_array($service->pricing_strategy, ['FLAT', 'PACKAGE_FLAT'], true) || $service->currency !== 'MXN'
                    || ! $this->covered($service, $provider->id, 'originZone', $quote->cp_origen)
                    || ! $this->covered($service, $provider->id, 'destinationZone', $quote->cp_destino)) {
                    continue;
                }
                $rule = LocalShippingPackageRule::query()->where('tenant_id', $provider->id)->where('active', true)
                    ->where('package_type', $type)->where(fn ($q) => $q->where('service_id', $service->id)->orWhereNull('service_id'))
                    ->orderByRaw('CASE WHEN service_id IS NULL THEN 1 ELSE 0 END')->orderBy('id')->first();
                if (! $rule) {
                    continue;
                }
                $this->packages->validate($rule, $package);
                foreach (['weight' => 'max_weight', 'length' => 'max_length', 'width' => 'max_width', 'height' => 'max_height'] as $field => $limit) {
                    if ($service->{$limit} !== null && ($package[$field] === null || (float) $package[$field] > (float) $service->{$limit})) {
                        continue 2;
                    }
                }
                $service->setRelation('pricingRules', $service->pricingRules()->where('tenant_id', $provider->id)
                    ->where('active', true)->where(fn ($q) => $q->whereNull('package_type')->orWhere('package_type', $type))
                    ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
                    ->where(fn ($q) => $q->whereNull('valid_from')->orWhereNull('valid_to')->orWhereColumn('valid_from', '<=', 'valid_to'))
                    ->orderBy('id')->get());
                $price = $this->pricing->price($service, $type, null);
                $commercial = $this->commercial->calculate($consumer, $provider, $price->totalAmount, $segment, $type);
                if (! $commercial) {
                    continue;
                }
                $options[] = $this->normalize($consumer, $provider, $service, $quote, $price->matchedRuleId, $commercial);
            } catch (ValidationException|\DomainException) {
                continue;
            } catch (Throwable $exception) {
                Log::warning('Tenant provider service quote unavailable', ['provider_tenant_id' => $provider->id,
                    'service_id' => $service->id, 'exception' => get_class($exception)]);
            }
        }

        return $options;
    }

    private function covered(LocalShippingService $service, int $providerId, string $relation, string $cp): bool
    {
        return $service->{$relation}()->where('tenant_id', $providerId)->where('status', 'active')
            ->whereHas('postalCodes', fn ($q) => $q->where('tenant_id', $providerId)->where('postal_code', $cp)
                ->where('active', true)->where(fn ($v) => $v->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                ->where(fn ($v) => $v->whereNull('valid_to')->orWhere('valid_to', '>', now())))->exists();
    }

    private function normalize(Tenant $consumer, Tenant $provider, LocalShippingService $service, B2cCotizacion $quote,
        ?int $tariffId, array $commercial): array
    {
        $key = 'local_'.hash('sha256', $consumer->uuid.'|'.$provider->uuid.'|'.$service->id);

        return ['provider' => 'tenant_provider', 'carrier' => $this->commercial->carrierKey($consumer, $provider),
            'provider_source' => 'tenant_provider_local', 'capability' => 'LOCAL_SHIPPING', 'option_key' => $key,
            'logistico' => $provider->name, 'servicio' => $service->name, 'service_code' => $service->code,
            'logo' => null, 'entrega' => $service->sla_text ?: ($service->estimated_max_hours ? $service->estimated_max_hours.' h' : 'Por confirmar'),
            'base_price' => (float) $commercial['provider_cost'], 'provider_cost' => (float) $commercial['provider_cost'],
            'precio_base' => (float) $commercial['provider_cost'], 'precio' => (float) $commercial['customer_price'],
            'commercial_price' => (float) $commercial['customer_price'], 'currency' => $service->currency,
            'rate_source' => 'local_shipping_pricing_rules', 'selection_enabled' => false,
            'estimated_delivery_date' => null, 'periodicity_name' => '', 'operating_days' => [], 'zone_code' => '',
            'is_reexpedition' => false, 'extended_area' => false, 'restriction' => false, 'restriction_description' => '',
            'weight_billable' => (float) $quote->peso, 'dimensions' => $quote->medidas ?: 'No aplica', 'insurance_enabled' => false,
            // Internal only. Views must never serialize the complete option.
            '_tenant_provider' => ['consumer_tenant_id' => $consumer->id, 'provider_tenant_id' => $provider->id,
                'service_id' => $service->id, 'tariff_rule_id' => $tariffId, 'commercial_rule_id' => $commercial['rule_id'],
                'pricing_strategy' => $service->pricing_strategy, 'profit_amount' => $commercial['profit_amount']]];
    }
}
