<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Reportes\ReportesTipo;

class ReportesTipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ReportesTipo::create([
            'nombre' => 'Ventas',
            'descripcion' => 'Valor para obtener el nombre de tipo de reporte ventas'
            
        ]);

        ReportesTipo::create([
            'nombre' => 'Repesaje',
            'descripcion' => 'Valor para obtener el nombre de tipo de reporte repesaje'
            
        ]);
    }
}
