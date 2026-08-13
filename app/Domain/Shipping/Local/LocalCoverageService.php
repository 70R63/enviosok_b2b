<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Services\ZigoPostalCodeService;
use Illuminate\Validation\ValidationException;

final class LocalCoverageService
{
    public function __construct(private ZigoPostalCodeService $postalCodes) {}

    public function zoneFor(string $postalCode): ?LocalShippingZone
    {
        return LocalShippingZone::query()->where('status', 'active')
            ->whereHas('postalCodes', fn ($query) => $query->where('postal_code', $postalCode))->first();
    }

    public function assign(LocalShippingZone $zone, string $postalCode): LocalShippingZonePostalCode
    {
        $lookup = $this->postalCodes->lookup($postalCode);
        if (! ($lookup['success'] ?? false)) {
            throw ValidationException::withMessages(['postal_code' => $lookup['message'] ?? 'Código postal inválido.']);
        }
        return LocalShippingZonePostalCode::firstOrCreate(['zone_id' => $zone->id, 'postal_code' => $postalCode]);
    }

    public function assignForTenant(int $tenantId, LocalShippingZone $zone, string $postalCode, array $metadata = []): LocalShippingZonePostalCode
    {
        abort_unless((int)$zone->tenant_id === $tenantId, 404); $lookup=$this->postalCodes->lookup($postalCode);
        if(!($lookup['success']??false)) throw ValidationException::withMessages(['postal_codes'=>$lookup['message']??'Código postal inválido.']);
        return LocalShippingZonePostalCode::updateOrCreate(['tenant_id'=>$tenantId,'zone_id'=>$zone->id,'postal_code'=>$postalCode],['state'=>$lookup['estado'],'municipality'=>$lookup['municipio'],'active'=>true,'metadata'=>$metadata]);
    }
}
