<?php

namespace Database\Seeders;

use App\Models\ZigoCommercialConceptRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ZigoUatCommercialConceptRulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->assertEnvironmentAllowed();

        foreach ($this->rules() as $rule) {
            $model = ZigoCommercialConceptRule::query()->updateOrCreate(
                [
                    'carrier' => $rule['carrier'],
                    'service' => $rule['service'],
                    'segment' => $rule['segment'],
                    'plan' => $rule['plan'],
                    'package_type' => $rule['package_type'],
                    'concept' => $rule['concept'],
                ],
                $rule
            );

            $action = $model->wasRecentlyCreated ? 'created' : 'updated';
            $context = [
                'action' => $action,
                'rule_id' => $model->id,
                'carrier' => $model->carrier,
                'segment' => $model->segment,
                'concept' => $model->concept,
                'package_type' => $model->package_type,
            ];
            Log::info('ZIGO UAT commercial concept rule seeded.', $context);
            $this->command?->info(sprintf(
                '%s: [%d] %s (%s)',
                strtoupper($action),
                $model->id,
                $model->name,
                $model->concept
            ));
        }
    }

    private function assertEnvironmentAllowed(): void
    {
        $environment = app()->environment();
        $productionAllowed = (bool) config(
            'zigo_devops.allow_uat_commercial_concept_rules_in_production',
            false
        );

        if ($environment === 'production' && !$productionAllowed) {
            throw new RuntimeException(
                'ZIGO_UAT_COMMERCIAL_CONCEPT_RULES_PRODUCTION_BLOCKED'
            );
        }

        if (!in_array($environment, ['local', 'stage', 'staging'], true)
            && !app()->runningUnitTests()
            && !($environment === 'production' && $productionAllowed)) {
            throw new RuntimeException(
                'ZIGO_UAT_COMMERCIAL_CONCEPT_RULES_ENVIRONMENT_NOT_ALLOWED'
            );
        }
    }

    private function rules(): array
    {
        return [
            $this->rule('Margen área extendida B2C', 'area_extendida', 'porcentaje', 10),
            $this->rule('Margen kg extra B2C', 'kg_extra', 'porcentaje', 10),
            $this->rule('Seguro B2C sin utilidad adicional', 'seguro', 'sin_margen', 0),
            $this->rule('Margen otros cargos B2C', 'otros', 'porcentaje', 10),
        ];
    }

    private function rule(string $name, string $concept, string $type, float $value): array
    {
        return [
            'name' => $name,
            'carrier' => 'ESTAFETA',
            'service' => 'all',
            'segment' => 'b2c',
            'plan' => null,
            'package_type' => 'all',
            'concept' => $concept,
            'adjustment_type' => $type,
            'value' => $value,
            'priority' => 100,
            'starts_at' => null,
            'ends_at' => null,
            'active' => true,
        ];
    }
}
