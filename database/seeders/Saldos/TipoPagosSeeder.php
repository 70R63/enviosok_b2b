<?php

namespace Database\Seeders\Saldos;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Saldos\TipoPagos;

class TipoPagosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TipoPagos::create([
            'nombre' => 'CREDITO',
            
        ]);

        TipoPagos::create([
            'nombre' => 'PREPAGO',
            
        ]);
    }
}
