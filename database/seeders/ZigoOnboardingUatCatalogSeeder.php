<?php

namespace Database\Seeders;

use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Database\Seeder;
use RuntimeException;

final class ZigoOnboardingUatCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->assertEnvironmentAllowed();

        $modules = collect($this->modules())->mapWithKeys(function (array $data): array {
            $module = Module::query()->updateOrCreate(['code' => $data['code']], $data);
            return [$data['code'] => $module];
        });

        foreach ($this->plans() as $position => $definition) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'], 'description' => $definition['description'],
                    'status' => 'active', 'monthly_price' => $definition['monthly'],
                    'annual_price' => $definition['annual'], 'currency' => 'MXN',
                    'included_operations' => $definition['operations'],
                ],
            );

            foreach ($definition['modules'] as $code) {
                $plan->modules()->syncWithoutDetaching([
                    $modules[$code]->id => ['is_included' => true, 'limit_value' => null],
                ]);
            }

            foreach (['MONTHLY' => 'monthly', 'ANNUAL' => 'annual'] as $billingType => $priceKey) {
                NetworkCommercialProduct::query()->updateOrCreate(
                    ['code' => 'UAT-'.$definition['code'].'-'.$billingType],
                    [
                        'name' => $definition['name'], 'description' => $definition['description'],
                        'type' => 'PLAN', 'billing_type' => $billingType,
                        'price' => $definition[$priceKey], 'currency' => 'MXN',
                        'plan_id' => $plan->id, 'module_id' => null,
                        'included_operations' => $definition['operations'], 'is_active' => true,
                        'sort_order' => ($position * 10) + ($billingType === 'ANNUAL' ? 2 : 1),
                        'metadata' => [
                            'uat_test_data' => true, 'not_approved_for_production' => true,
                            'allowance_label' => 'Hasta '.number_format($definition['operations']).' envíos '.($billingType === 'ANNUAL' ? 'al año' : 'al mes'),
                        ],
                    ],
                );
            }
        }

        foreach (['MONTHLY' => '25.50', 'ANNUAL' => '255.00'] as $billingType => $price) {
            NetworkCommercialProduct::query()->updateOrCreate(
                ['code' => 'UAT-API-'.$billingType],
                [
                    'name' => 'API Hub', 'description' => 'Capacidad opcional de integración para UAT.',
                    'type' => 'MODULE', 'billing_type' => $billingType, 'price' => $price,
                    'currency' => 'MXN', 'module_id' => $modules['API']->id, 'plan_id' => null,
                    'included_operations' => null, 'is_active' => true, 'sort_order' => 90,
                    'metadata' => ['uat_test_data' => true, 'api_monthly_request_limit' => 10000, 'api_rate_limit_per_minute' => 60],
                ],
            );
        }

        NetworkCommercialProduct::query()->updateOrCreate(
            ['code' => 'UAT-OPS-500'],
            [
                'name' => '500 operaciones UAT', 'description' => 'Paquete de volumen para pruebas de onboarding.',
                'type' => 'OPERATION_PACK', 'billing_type' => 'ONE_TIME', 'price' => '50.00',
                'currency' => 'MXN', 'module_id' => null, 'plan_id' => null,
                'included_operations' => 500, 'is_active' => true, 'sort_order' => 100,
                'metadata' => ['uat_test_data' => true, 'not_approved_for_production' => true],
            ],
        );

        $this->command?->warn('Catálogo ZIGO onboarding UAT creado. Los importes son TEST DATA y no están aprobados para producción.');
    }

    private function assertEnvironmentAllowed(): void
    {
        $environment = app()->environment();
        if ($environment === 'production') {
            throw new RuntimeException('ZIGO_ONBOARDING_UAT_CATALOG_PRODUCTION_BLOCKED');
        }
        if (!in_array($environment, ['local', 'stage', 'staging', 'testing'], true) && !app()->runningUnitTests()) {
            throw new RuntimeException('ZIGO_ONBOARDING_UAT_CATALOG_ENVIRONMENT_NOT_ALLOWED');
        }
    }

    private function modules(): array
    {
        return [
            ['code'=>'WHITE_LABEL','name'=>'Portal white-label','description'=>'Portal bajo la marca del tenant.','type'=>'core','is_active'=>true,'sort_order'=>10],
            ['code'=>'QUOTES','name'=>'Cotizaciones','description'=>'Cotización de servicios.','type'=>'core','is_active'=>true,'sort_order'=>20],
            ['code'=>'CUSTOMERS','name'=>'Clientes','description'=>'Gestión de clientes.','type'=>'core','is_active'=>true,'sort_order'=>30],
            ['code'=>'SHIPPING','name'=>'Envíos','description'=>'Operación de envíos.','type'=>'core','is_active'=>true,'sort_order'=>40],
            ['code'=>'TRACKING','name'=>'Tracking','description'=>'Seguimiento de envíos.','type'=>'core','is_active'=>true,'sort_order'=>50],
            ['code'=>'CRM','name'=>'CRM','description'=>'Gestión comercial.','type'=>'addon','is_active'=>true,'sort_order'=>60],
            ['code'=>'LOCAL_SHIPPING','name'=>'Envíos locales','description'=>'Operación local.','type'=>'channel','is_active'=>true,'sort_order'=>70],
            ['code'=>'DRIVER','name'=>'ZIGO Driver','description'=>'Operación de conductores.','type'=>'channel','is_active'=>true,'sort_order'=>80],
            ['code'=>'SUPPORT','name'=>'Soporte','description'=>'Centro de soporte.','type'=>'addon','is_active'=>true,'sort_order'=>90],
            ['code'=>'API','name'=>'API Hub','description'=>'Integraciones API.','type'=>'integration','is_active'=>true,'sort_order'=>100],
        ];
    }

    private function plans(): array
    {
        return [
            ['code'=>'UAT-ZIGO-ESENCIAL','name'=>'ZIGO Esencial','description'=>'Base white-label para comenzar a vender y operar.','monthly'=>'100.00','annual'=>'1000.00','operations'=>100,'modules'=>['WHITE_LABEL','QUOTES','CUSTOMERS','SHIPPING','TRACKING']],
            ['code'=>'UAT-ZIGO-OPERACION','name'=>'ZIGO Operación','description'=>'Más herramientas para coordinar una operación en crecimiento.','monthly'=>'500.00','annual'=>'5000.00','operations'=>500,'modules'=>['WHITE_LABEL','QUOTES','CUSTOMERS','SHIPPING','TRACKING','CRM','LOCAL_SHIPPING','SUPPORT']],
            ['code'=>'UAT-ZIGO-PLATFORM','name'=>'ZIGO Platform','description'=>'Capacidades completas para integrar y escalar tu plataforma.','monthly'=>'999.00','annual'=>'9990.00','operations'=>1000,'modules'=>['WHITE_LABEL','QUOTES','CUSTOMERS','SHIPPING','TRACKING','CRM','LOCAL_SHIPPING','DRIVER','SUPPORT','API']],
        ];
    }
}
