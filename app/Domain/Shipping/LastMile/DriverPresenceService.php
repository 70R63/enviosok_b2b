<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Shipping\LastMile\Models\DriverProfile;

final class DriverPresenceService
{
    public function heartbeat(DriverProfile $profile): DriverProfile
    {
        $interval = max(10, (int) config('zigo_driver.heartbeat_write_interval_seconds', 30));
        if ($profile->last_seen_at === null || $profile->last_seen_at->lt(now()->subSeconds($interval))) {
            $profile->forceFill(['last_seen_at' => now()])->save();
        }

        return $profile;
    }

    public function setAvailability(DriverProfile $profile, string $availability): DriverProfile
    {
        if (! in_array($availability, ['AVAILABLE', 'UNAVAILABLE'], true)) {
            throw new \DomainException('Disponibilidad inválida.');
        }
        $profile->forceFill(['availability_status' => $availability, 'last_seen_at' => now()])->save();

        return $profile->fresh();
    }
}
