<?php

namespace Database\Seeders\Saldos;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Saldos\Pagos;

class PagosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $x= 13;
        for ($i=0; $i < 28; $i++) { 
            Pagos::create([
                'empresa_id' => 88
                ,'usuario_id' => 1
                ,'banco_id' => 1+$i
                ,'tipo_pago_id' => 2
                ,'referencia' => md5($i+$x)
                ,'importe' => 100+$i
                ,'fecha_deposito' => '2023-08-21'
                ,'hora_deposito' => '13:29'
                
            ]);
        }
        

    }
}
