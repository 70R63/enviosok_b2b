<?php

namespace Database\Seeders\Saldos;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Saldos\Bancos;

class BancosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        Bancos::create([
            'nombre' => 'BBVA',
            
        ]);

        Bancos::create([
            'nombre' => 'BANORTE',
            
        ]);

        Bancos::create([
            'nombre' => 'HSBC',
            
        ]);

        Bancos::create([
            'nombre' => 'BANAMEX',
            
        ]);

        Bancos::create([
            'nombre' => 'BANCO DEL BIENESTAR',
            
        ]);

        Bancos::create([
            'nombre' => 'BANCO AZTECA',
            
        ]);

        Bancos::create([
            'nombre' => 'AFIRME',
            
        ]);

        Bancos::create([
            'nombre' => 'ABC CAPITAL',
            
        ]);

        Bancos::create([
            'nombre' => 'BANCA MIFEL',
            
        ]);

        Bancos::create([
            'nombre' => 'BANCREA',
            
        ]);

        Bancos::create([
            'nombre' => 'COMPARTAMOS',
            
        ]);

        Bancos::create([
            'nombre' => 'BANCO DEL BAJIO',
            
        ]);

        Bancos::create([
            'nombre' => 'INBURSA',
            
        ]);
        Bancos::create([
            'nombre' => 'MULTIVA',
            
        ]);
        Bancos::create([
            'nombre' => 'SANTANDER',
            
        ]);
        Bancos::create([
            'nombre' => 'BANCOPPEL',
            
        ]);
        Bancos::create([
            'nombre' => 'CI BANCO',
            
        ]);
        Bancos::create([
            'nombre' => 'INTERCAM BANCO',
            
        ]);
        Bancos::create([
            'nombre' => 'SCOTIABANK',
            
        ]);
        Bancos::create([
            'nombre' => 'HEYBANCO',
            
        ]);
        Bancos::create([
            'nombre' => 'ACTINVER',
            
        ]);
        Bancos::create([
            'nombre' => 'AUTOFIN MEXICO',
            
        ]);
        Bancos::create([
            'nombre' => 'BANCO BASE',
            
        ]);
        Bancos::create([
            'nombre' => 'BANCO COVALTO',
            
        ]);
        Bancos::create([
            'nombre' => 'FORJADORES',
            
        ]);
        Bancos::create([
            'nombre' => 'INVEX',
            
        ]);
        Bancos::create([
            'nombre' => 'SABADELL',
            
        ]);
        Bancos::create([
            'nombre' => 'BANCO VE POR MAS',
            
        ]);

        Bancos::create([
            'nombre' => 'BANREGIO',
            
        ]);
        
    }
}
