<?php

namespace Database\Seeders;

use App\Models\ZigoContractRate;
use Illuminate\Database\Seeder;

class ZigoContractRatesSeeder extends Seeder
{
    public function run()
    {
        $rates = [
            [
                'name' =>
                    'Estafeta Terrestre contractual',
                'carrier' => 'ESTAFETA',
                'service_code' => 'TERRESTRE',
                'service_name' => 'Terrestre',
                'included_weight_kg' => 5,
                'base_price' => 108,
                'additional_weight_unit_kg' => 1,
                'additional_weight_price' => 7.50,
                'tax_percentage' => 16,
                'currency' => 'MXN',
                'source' =>
                    'CONTRATO_INICIAL_ZIGO',
                'valid_from' => null,
                'valid_to' => null,
                'active' => true,
                'notes' =>
                    'Importes contractuales antes de IVA.',
            ],
            [
                'name' =>
                    'Estafeta Día siguiente contractual',
                'carrier' => 'ESTAFETA',
                'service_code' =>
                    'DIA_SIGUIENTE',
                'service_name' =>
                    'Día siguiente',
                'included_weight_kg' => 1,
                'base_price' => 128,
                'additional_weight_unit_kg' => 1,
                'additional_weight_price' => 30,
                'tax_percentage' => 16,
                'currency' => 'MXN',
                'source' =>
                    'CONTRATO_INICIAL_ZIGO',
                'valid_from' => null,
                'valid_to' => null,
                'active' => true,
                'notes' =>
                    'Importes contractuales antes de IVA.',
            ],
        ];

        foreach ($rates as $rate) {
            ZigoContractRate::updateOrCreate(
                [
                    'carrier' =>
                        $rate['carrier'],
                    'service_code' =>
                        $rate['service_code'],
                    'source' =>
                        $rate['source'],
                ],
                $rate
            );
        }
    }
}
