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
}
