<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmpresaEmpresas;

class EmpresaEmpresasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        EmpresaEmpresas::create([
            'id' => '1',
            'empresa_id' =>"1" ,
        ]);
    }
}
