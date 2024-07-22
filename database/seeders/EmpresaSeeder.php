<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Empresa::create([
            'contacto' => 'Sysadmin 0 ',
            'nombre' =>"Empresa Sysadmin 0" 
            ,'email'    => 'jjj@gmail.com'
        ]);
    }
}
