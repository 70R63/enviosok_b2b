<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Servicio;

class ServicioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Servicio::create([
            'estatus' => '1',
            'nombre' => 'Terrestre',
            'descripcion' =>"Servicio de recoleccion Terrestre" ,
            'prioridad' => '4'
        ]);

        Servicio::create([
            'estatus' => '1',
            'nombre' => 'Dia Sig',
            'descripcion' =>"Servicio de recoleccion Dia Siguiente" ,
            'prioridad' => '2'
        ]);

        Servicio::create([
            'estatus' => '1',
            'nombre' => '2 Dias',
            'descripcion' =>"Servicio de recoleccion 2 Dias" ,
            'prioridad' => '3'
        ]);

        Servicio::create([
            'estatus' => '1',
            'nombre' => '930',
            'descripcion' =>"Servicio de recoleccion " ,
            'prioridad' => '1'
        ]);
    }
}
