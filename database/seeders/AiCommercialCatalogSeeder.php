<?php

namespace Database\Seeders;

use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Database\Seeder;
use RuntimeException;

final class AiCommercialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('AI_COMMERCIAL_STAGE_CATALOG_PRODUCTION_BLOCKED');
        }

        $module = Module::query()->firstOrCreate(
            ['code' => 'AI_CORE'],
            ['name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 5],
        );

        $products = [
            [
                'code' => 'STAGE-AI-AGENTS', 'name' => 'Agentes IA',
                'description' => 'Capacidad base para crear y operar Agentes IA.',
                'type' => 'ADDON', 'price' => '299.00', 'billing_type' => 'MONTHLY', 'sort_order' => 200,
                'metadata' => ['ai_product' => true, 'ai_kind' => 'base', 'stage_provisional' => true, 'ai_capacities' => [
                    AiCapacityService::MAX_AGENTS => 1, AiCapacityService::MAX_WEBCHAT_CHANNELS => 1,
                    AiCapacityService::MAX_WHATSAPP_CHANNELS => 0, AiCapacityService::MONTHLY_RUNTIME_UNITS => 1000,
                    AiCapacityService::MONTHLY_ACTION_RUNS => 100, AiCapacityService::MONTHLY_CONVERSATIONS => 250,
                ]],
            ],
            [
                'code' => 'STAGE-AI-AGENT-ADDON', 'name' => 'Agente adicional',
                'description' => 'Añade un agente a tu capacidad actual.', 'type' => 'ADDON', 'price' => '99.00',
                'billing_type' => 'MONTHLY', 'sort_order' => 210,
                'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'stage_provisional' => true, 'ai_addons' => [AiCapacityService::MAX_AGENTS => 1]],
            ],
            [
                'code' => 'STAGE-AI-WHATSAPP', 'name' => 'WhatsApp',
                'description' => 'Añade un canal WhatsApp para Agentes IA.', 'type' => 'ADDON', 'price' => '149.00',
                'billing_type' => 'MONTHLY', 'sort_order' => 220,
                'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'stage_provisional' => true, 'ai_addons' => [AiCapacityService::MAX_WHATSAPP_CHANNELS => 1]],
            ],
            [
                'code' => 'STAGE-AI-CONVERSATIONS-250', 'name' => 'Conversaciones',
                'description' => 'Añade 250 conversaciones mensuales.', 'type' => 'ADDON', 'price' => '79.00',
                'billing_type' => 'MONTHLY', 'sort_order' => 230,
                'metadata' => ['ai_product' => true, 'ai_kind' => 'addon', 'stage_provisional' => true, 'ai_addons' => [AiCapacityService::MONTHLY_CONVERSATIONS => 250]],
            ],
        ];

        foreach ($products as $data) {
            NetworkCommercialProduct::query()->updateOrCreate(
                ['code' => $data['code']],
                $data + ['currency' => 'MXN', 'module_id' => $module->id, 'plan_id' => null, 'included_operations' => null, 'is_active' => true, 'is_public' => true, 'display_order' => $data['sort_order'], 'archived_at' => null],
            );
        }
    }
}
