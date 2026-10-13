<?php

namespace App\Domain\Network\Providers;

use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Domain\Shipping\Local\PackageValidator;
use App\Models\B2cCotizacion;
use Illuminate\Validation\ValidationException;

class LocalProviderEligibility
{
    public function __construct(private PackageValidator $packages) {}

    public function allows(LocalShippingService $service, B2cCotizacion $quote, bool $lock = false): bool
    {
        $type = strtolower((string) $quote->tipo_envio);
        $weight = (float) $quote->peso;
        if (! in_array($type, ['caja', 'sobre'], true) || $weight <= 0 || ! is_finite($weight)
            || $quote->requiere_seguro_envio || ! preg_match('/^\d{5}$/', (string) $quote->cp_origen)
            || ! preg_match('/^\d{5}$/', (string) $quote->cp_destino)
            || ! in_array($service->pricing_strategy, ['FLAT', 'PACKAGE_FLAT'], true) || $service->currency !== 'MXN') {
            return false;
        }
        $dimensions = $type === 'caja' ? preg_split('/x/i', (string) $quote->medidas) : [null, null, null];
        if (count($dimensions) !== 3 || ($type === 'caja' && collect($dimensions)->contains(
            fn ($v) => ! is_numeric($v) || (float) $v <= 0 || ! is_finite((float) $v)))) {
            return false;
        }
        if (! $this->covered($service->origin_zone_id, $service->tenant_id, $quote->cp_origen, $lock)
            || ! $this->covered($service->destination_zone_id, $service->tenant_id, $quote->cp_destino, $lock)) {
            return false;
        }
        $rule = LocalShippingPackageRule::query()->where('tenant_id', $service->tenant_id)->where('active', true)
            ->where('package_type', $type)->where(fn ($q) => $q->where('service_id', $service->id)->orWhereNull('service_id'))
            ->orderByRaw('CASE WHEN service_id IS NULL THEN 1 ELSE 0 END')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $rule) {
            return false;
        }
        $package = ['weight' => $weight, 'length' => $dimensions[0], 'width' => $dimensions[1], 'height' => $dimensions[2]];
        try {
            $this->packages->validate($rule, $package);
        } catch (ValidationException) {
            return false;
        }
        foreach (['weight' => 'max_weight', 'length' => 'max_length', 'width' => 'max_width', 'height' => 'max_height'] as $field => $limit) {
            if ($service->{$limit} !== null && ($package[$field] === null || (float) $package[$field] > (float) $service->{$limit})) {
                return false;
            }
        }

        return true;
    }

    private function covered(int $zoneId, int $providerId, string $cp, bool $lock): bool
    {
        $zone = LocalShippingZone::query()->whereKey($zoneId)->where('tenant_id', $providerId)->where('status', 'active')
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if (! $zone) {
            return false;
        }

        return LocalShippingZonePostalCode::query()->where('zone_id', $zoneId)->where('tenant_id', $providerId)
            ->where('postal_code', $cp)->where('active', true)
            ->where(fn ($v) => $v->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($v) => $v->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->when($lock, fn ($q) => $q->lockForUpdate())->first() !== null;
    }
}
