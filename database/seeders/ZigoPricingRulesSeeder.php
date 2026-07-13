<?php

namespace Database\Seeders;

use App\Models\ZigoPricingRule;
use App\Models\ZigoPricingAdjustment;
use Illuminate\Database\Seeder;

class ZigoPricingRulesSeeder extends Seeder
{
    public function run()
    {
        $rules = [
            [
                'name' => 'Público anonymous sobre',
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'anonymous',
                'plan' => null,
                'package_type' => 'sobre',
                'margin_percentage' => 50,
                'fixed_fee' => 0,
                'min_price' => null,
                'active' => true,
            ],
            [
                'name' => 'B2C logueado sobre',
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'b2c',
                'plan' => null,
                'package_type' => 'sobre',
                'margin_percentage' => 40,
                'fixed_fee' => 0,
                'min_price' => null,
                'active' => true,
            ],
            [
                'name' => 'B2C logueado caja',
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'b2c',
                'plan' => null,
                'package_type' => 'caja',
                'margin_percentage' => 45,
                'fixed_fee' => 10,
                'min_price' => null,
                'active' => true,
            ],
            [
                'name' => 'B2B Starter general',
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'b2b',
                'plan' => 'STARTER',
                'package_type' => 'all',
                'margin_percentage' => 30,
                'fixed_fee' => 0,
                'min_price' => null,
                'active' => true,
            ],
            [
                'name' => 'API Starter general',
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'api',
                'plan' => 'STARTER',
                'package_type' => 'all',
                'margin_percentage' => 25,
                'fixed_fee' => 0,
                'min_price' => null,
                'active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            ZigoPricingRule::updateOrCreate(
                [
                    'carrier' => $rule['carrier'],
                    'customer_segment' => $rule['customer_segment'],
                    'plan' => $rule['plan'],
                    'package_type' => $rule['package_type'],
                ],
                $rule
            );
        }

        ZigoPricingAdjustment::updateOrCreate(
            [
                'carrier' => 'ESTAFETA',
                'customer_segment' => 'b2c',
                'package_type' => 'all',
                'name' => 'Temporada alta B2C +10%',
            ],
            [
                'adjustment_type' => 'surcharge_percentage',
                'adjustment_value' => 10,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'active' => false,
            ]
        );
    }
}