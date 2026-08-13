<?php

namespace App\Console\Commands;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingPricingRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SeedTenantLogisticsDemo extends Command
{
    protected $signature = 'zigo:tenant-logistics-demo {tenant : Tenant ID or slug}';

    protected $description = 'Creates the idempotent RC4.9 demo logistics configuration for one tenant.';

    public function handle(): int
    {
        $tenant = Tenant::where('id', $this->argument('tenant'))
            ->orWhere('slug', $this->argument('tenant'))
            ->firstOrFail();

        DB::transaction(function () use ($tenant): void {
            $zone = LocalShippingZone::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'postal-pool',
                ],
                [
                    'name' => 'Cobertura por CP',
                    'coverage_mode' => 'POSTAL_POOL',
                    'status' => 'active',
                ]
            );

            $local = $this->service(
                $tenant->id,
                $zone->id,
                'entrega-local',
                'Entrega local',
                'DISTANCE_TIERS_OVERAGE'
            );
            $direct = $this->service(
                $tenant->id,
                $zone->id,
                'directo',
                'Directo',
                'FLAT'
            );

            $localPricingRules = [
                ['from_km' => '0', 'to_km' => '7', 'amount' => '89'],
                ['from_km' => '7.001', 'to_km' => '15', 'amount' => '109'],
                ['from_km' => '15.001', 'to_km' => '25', 'amount' => '139'],
                [
                    'from_km' => '25.001',
                    'to_km' => null,
                    'amount' => '139',
                    'included_distance_km' => '25',
                    'overage_price_per_km' => '20',
                    'overage_rounding' => 'PROPORTIONAL',
                ],
            ];

            foreach ($localPricingRules as $rule) {
                LocalShippingPricingRule::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'service_id' => $local->id,
                        'from_km' => $rule['from_km'],
                    ],
                    $rule + ['active' => true]
                );
            }

            LocalShippingPricingRule::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'service_id' => $direct->id,
                    'from_km' => null,
                ],
                [
                    'amount' => '179',
                    'active' => true,
                ]
            );

            $packageRules = [
                ['sobre', '1', null, null, null],
                ['caja', '40', '60', '50', '40'],
            ];

            foreach ($packageRules as $package) {
                LocalShippingPackageRule::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'service_id' => null,
                        'package_type' => $package[0],
                    ],
                    [
                        'max_weight_kg' => $package[1],
                        'max_dimension_1_cm' => $package[2],
                        'max_dimension_2_cm' => $package[3],
                        'max_dimension_3_cm' => $package[4],
                        'active' => true,
                    ]
                );
            }
        });

        $this->info(
            'Configuración demo actualizada para '.$tenant->name.'. No se agregó cobertura postal.'
        );

        return self::SUCCESS;
    }

    private function service(
        int $tenantId,
        int $zoneId,
        string $code,
        string $name,
        string $strategy
    ): LocalShippingService {
        return LocalShippingService::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'name' => $name,
                'origin_zone_id' => $zoneId,
                'destination_zone_id' => $zoneId,
                'service_level' => 'scheduled',
                'base_cost' => '0',
                'base_price' => '0',
                'currency' => 'MXN',
                'status' => 'active',
                'published' => true,
                'pricing_strategy' => $strategy,
                'sort_order' => 0,
            ]
        );
    }
}
