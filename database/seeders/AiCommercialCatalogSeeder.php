<?php

namespace Database\Seeders;

use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Catalog\Models\{Module,Plan};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        $planCaps = [
            'AI_TRIAL' => ['name'=>'Agentes IA Trial','monthly'=>'0.00','annual'=>null,'trial_days'=>7,'caps'=>[AiCapacityService::MAX_AGENTS=>1,AiCapacityService::MAX_WEBCHAT_CHANNELS=>1,AiCapacityService::MAX_WHATSAPP_CHANNELS=>0,AiCapacityService::MONTHLY_RUNTIME_UNITS=>100,AiCapacityService::MONTHLY_ACTION_RUNS=>20,AiCapacityService::MONTHLY_CONVERSATIONS=>50]],
            'AI_INICIAL' => ['name'=>'Agentes IA Inicial','monthly'=>'599.00','annual'=>'5990.00','caps'=>[AiCapacityService::MAX_AGENTS=>1,AiCapacityService::MAX_WEBCHAT_CHANNELS=>1,AiCapacityService::MAX_WHATSAPP_CHANNELS=>0,AiCapacityService::MONTHLY_RUNTIME_UNITS=>1000,AiCapacityService::MONTHLY_ACTION_RUNS=>100,AiCapacityService::MONTHLY_CONVERSATIONS=>150]],
            'AI_CRECIMIENTO' => ['name'=>'Agentes IA Crecimiento','monthly'=>'1299.00','annual'=>'12990.00','caps'=>[AiCapacityService::MAX_AGENTS=>2,AiCapacityService::MAX_WEBCHAT_CHANNELS=>2,AiCapacityService::MAX_WHATSAPP_CHANNELS=>1,AiCapacityService::MONTHLY_RUNTIME_UNITS=>2000,AiCapacityService::MONTHLY_ACTION_RUNS=>200,AiCapacityService::MONTHLY_CONVERSATIONS=>500]],
            'AI_PRO' => ['name'=>'Agentes IA Pro','monthly'=>'2499.00','annual'=>'24990.00','caps'=>[AiCapacityService::MAX_AGENTS=>5,AiCapacityService::MAX_WEBCHAT_CHANNELS=>5,AiCapacityService::MAX_WHATSAPP_CHANNELS=>2,AiCapacityService::MONTHLY_RUNTIME_UNITS=>5000,AiCapacityService::MONTHLY_ACTION_RUNS=>500,AiCapacityService::MONTHLY_CONVERSATIONS=>1500]],
            'AI_ENTERPRISE' => ['name'=>'Agentes IA Enterprise','monthly'=>null,'annual'=>null,'caps'=>[]],
        ];
        foreach ($planCaps as $code=>$data) {
            $plan=Plan::query()->updateOrCreate(['code'=>$code],['name'=>$data['name'],'description'=>'Plan comercial de Agentes IA','status'=>'active','monthly_price'=>$data['monthly'],'annual_price'=>$data['annual'],'currency'=>'MXN']);
            $plan->modules()->syncWithoutDetaching([$module->id=>['is_included'=>true,'limit_value'=>null]]);
            foreach ($data['caps'] as $cap=>$qty) DB::table('network_plan_module_capacities')->updateOrInsert(['plan_id'=>$plan->id,'module_id'=>$module->id,'capability_code'=>$cap],['quantity'=>$qty,'created_at'=>now(),'updated_at'=>now()]);
        }
        $products = [
            [
                'code' => 'AI-TRIAL', 'name' => 'Agentes IA Trial',
                'description' => 'Capacidad base para crear y operar Agentes IA.',
                'type' => 'PLAN', 'price' => '0.00', 'billing_type' => 'MONTHLY', 'sort_order' => 190, 'plan_code'=>'AI_TRIAL',
                'metadata' => ['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_TRIAL','trial'=>true,'trial_days'=>7,'stage_provisional'=>true],
            ],
            ['code'=>'AI-INICIAL-MONTHLY','name'=>'Inicial','description'=>'Empieza con IA en tu sitio.','type'=>'PLAN','price'=>'599.00','billing_type'=>'MONTHLY','sort_order'=>200,'plan_code'=>'AI_INICIAL','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_INICIAL']],
            ['code'=>'AI-INICIAL-ANNUAL','name'=>'Inicial anual','description'=>'12 meses por el precio de 10.','type'=>'PLAN','price'=>'5990.00','billing_type'=>'ANNUAL','sort_order'=>201,'plan_code'=>'AI_INICIAL','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_INICIAL','annual_savings_months'=>2]],
            ['code'=>'AI-CRECIMIENTO-MONTHLY','name'=>'Crecimiento','description'=>'Atiende Web y WhatsApp.','type'=>'PLAN','price'=>'1299.00','billing_type'=>'MONTHLY','sort_order'=>210,'plan_code'=>'AI_CRECIMIENTO','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_CRECIMIENTO','featured'=>true]],
            ['code'=>'AI-CRECIMIENTO-ANNUAL','name'=>'Crecimiento anual','description'=>'12 meses por el precio de 10.','type'=>'PLAN','price'=>'12990.00','billing_type'=>'ANNUAL','sort_order'=>211,'plan_code'=>'AI_CRECIMIENTO','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_CRECIMIENTO','annual_savings_months'=>2]],
            ['code'=>'AI-PRO-MONTHLY','name'=>'Pro','description'=>'Automatiza varios procesos.','type'=>'PLAN','price'=>'2499.00','billing_type'=>'MONTHLY','sort_order'=>220,'plan_code'=>'AI_PRO','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_PRO']],
            ['code'=>'AI-PRO-ANNUAL','name'=>'Pro anual','description'=>'12 meses por el precio de 10.','type'=>'PLAN','price'=>'24990.00','billing_type'=>'ANNUAL','sort_order'=>221,'plan_code'=>'AI_PRO','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_PRO','annual_savings_months'=>2]],
            ['code'=>'AI-ENTERPRISE','name'=>'Enterprise','description'=>'Diseñado para tu operación. Solicita una cotización.','type'=>'PLAN','price'=>'0.00','billing_type'=>'MONTHLY','sort_order'=>230,'plan_code'=>'AI_ENTERPRISE','metadata'=>['ai_product'=>true,'ai_kind'=>'base','ai_plan_code'=>'AI_ENTERPRISE','contact_required'=>true]],
            [
                'code' => 'AI-ADDON-AGENT', 'name' => '+1 Agente IA',
                'description' => 'Añade un agente a tu capacidad actual.', 'type' => 'ADDON', 'price' => '299.00',
                'billing_type' => 'MONTHLY', 'sort_order' => 210,
                'metadata' => ['ai_product'=>true,'ai_kind'=>'addon','ai_addons'=>[AiCapacityService::MAX_AGENTS=>1]],
            ],
            [
                'code' => 'AI-ADDON-WEBCHAT', 'name' => '+1 Webchat', 'description'=>'Añade un canal Webchat.', 'type'=>'ADDON','price'=>'149.00','billing_type'=>'MONTHLY','sort_order'=>220,
                'metadata'=>['ai_product'=>true,'ai_kind'=>'addon','ai_addons'=>[AiCapacityService::MAX_WEBCHAT_CHANNELS=>1]],
            ],
            [
                'code' => 'AI-ADDON-WHATSAPP', 'name' => '+1 WhatsApp', 'description'=>'Añade un canal WhatsApp.','type'=>'ADDON','price'=>'349.00','billing_type'=>'MONTHLY','sort_order'=>230,
                'metadata'=>['ai_product'=>true,'ai_kind'=>'addon','ai_addons'=>[AiCapacityService::MAX_WHATSAPP_CHANNELS=>1]],
            ],
            ['code'=>'AI-ADDON-CONV-500','name'=>'+500 conversaciones','description'=>'Añade 500 conversaciones mensuales.','type'=>'ADDON','price'=>'399.00','billing_type'=>'MONTHLY','sort_order'=>240,'metadata'=>['ai_product'=>true,'ai_kind'=>'addon','ai_addons'=>[AiCapacityService::MONTHLY_CONVERSATIONS=>500]]],
            ['code'=>'AI-ADDON-CONV-1000','name'=>'+1000 conversaciones','description'=>'Añade 1000 conversaciones mensuales.','type'=>'ADDON','price'=>'699.00','billing_type'=>'MONTHLY','sort_order'=>241,'metadata'=>['ai_product'=>true,'ai_kind'=>'addon','ai_addons'=>[AiCapacityService::MONTHLY_CONVERSATIONS=>1000]]],
        ];

        foreach ($products as $data) {
            NetworkCommercialProduct::query()->updateOrCreate(
                ['code' => $data['code']],
                $data + ['currency'=>'MXN','module_id'=>$module->id,'plan_id'=>isset($data['plan_code']) ? optional(Plan::where('code',$data['plan_code'])->first())->id : null,'included_operations'=>null,'is_active'=>true,'is_public'=>true,'display_order'=>$data['sort_order'],'archived_at'=>null],
            );
        }
    }
}
