<?php

namespace Database\Seeders\CatalogosSistema;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\PlazoCreditos;

class PlazoCreditosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PlazoCreditos::create([
            'nombre' => '15 dias',
            'dias' => '15',
            
        ]);

        PlazoCreditos::create([
            'nombre' => '30 dias',
            'dias' => '30',
        ]);

        PlazoCreditos::create([
            'nombre' => '45 dias',
            'dias' => '45',
        ]);


        PlazoCreditos::create([
            'nombre' => '60 dias',
            'dias' => '60',
        ]);
    }
}
