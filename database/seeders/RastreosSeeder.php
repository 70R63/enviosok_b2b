<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Rastreo_estatus;
class RastreosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Rastreo_estatus::create([
            'nombre' => 'CREADA',
            'estatus' => 1,       
        ]);

        Rastreo_estatus::create([
            'nombre' => 'RECOLECTADO',
            'estatus' => 1,       
        ]);

        Rastreo_estatus::create([
            'nombre' => 'TRANSITO',
            'estatus' => 1,       
        ]);

        Rastreo_estatus::create([
            'nombre' => 'ENTREGADO',
            'estatus' => 1,       
        ]);
    }
}
