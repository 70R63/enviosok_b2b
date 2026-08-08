<?php
namespace Database\Seeders;
use App\Domain\Network\Catalog\Models\Module;
use Illuminate\Database\Seeder;

class NetworkModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['B2C','B2C','channel'], ['B2B','B2B','channel'],
            ['SHIPPING','Shipping','core'], ['TRACKING','Tracking','core'],
            ['CRM','CRM','addon'], ['DRIVER','Driver','addon'],
            ['COMMERCE','Commerce','addon'], ['MARKETING','Marketing','addon'],
            ['GPS','GPS','addon'], ['WAREHOUSE','Warehouse','addon'],
            ['API','API','integration'], ['INVOICING','Facturación','addon'],
        ];
        foreach ($modules as $index => [$code,$name,$type]) {
            Module::updateOrCreate(['code'=>$code],[
                'name'=>$name, 'type'=>$type, 'is_active'=>true,
                'sort_order'=>($index+1)*10,
            ]);
        }
    }
}
