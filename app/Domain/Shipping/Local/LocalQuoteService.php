<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShippingService;
use Illuminate\Support\Facades\Schema;

final class LocalQuoteService
{
    public function __construct(private LocalCoverageService $coverage) {}

    public function quote(array $package): array
    {
        if (! Schema::hasTable('local_shipping_services')) return [];
        $origin = $this->coverage->zoneFor($package['cp_origen']);
        $destination = $this->coverage->zoneFor($package['cp_destino']);
        if (! $origin || ! $destination) return [];

        return LocalShippingService::query()->where('status', 'active')
            ->where('origin_zone_id', $origin->id)->where('destination_zone_id', $destination->id)
            ->get()->filter(fn (LocalShippingService $service) => $this->fits($service, $package))
            ->map(fn (LocalShippingService $service) => [
                'provider' => 'ZIGO_LOCAL', 'carrier' => config('zigo_local.provider_name', 'ZIGO Local'),
                'service_code' => $service->code, 'service' => $service->name,
                'delivery' => $this->estimate($service), 'price' => (float) $service->base_price,
            ])->values()->all();
    }

    private function fits(LocalShippingService $service, array $package): bool
    {
        foreach (['weight' => 'max_weight', 'length' => 'max_length', 'width' => 'max_width', 'height' => 'max_height'] as $input => $limit) {
            if ($service->{$limit} !== null && (float) ($package[$input] ?? 0) > (float) $service->{$limit}) return false;
        }
        return true;
    }

    private function estimate(LocalShippingService $service): ?string
    {
        if ($service->estimated_min_hours === null && $service->estimated_max_hours === null) return null;
        $min = $service->estimated_min_hours ?? $service->estimated_max_hours;
        $max = $service->estimated_max_hours ?? $min;
        return $min === $max ? $min.' h' : $min.'–'.$max.' h';
    }
}
