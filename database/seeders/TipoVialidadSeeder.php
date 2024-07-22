<?php

namespace Database\Seeders;

use App\Models\Catalogo;
use App\Models\CatalogoElemento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TipoVialidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cat=Catalogo::create([
            'codigo'=>'tiposVialidad',
            'nombre'=>'Tipos de Vialidad',
            'nombre_singular'=>'Tipo de vialidad',
            'editable'=>true
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Ampliación',
            'codigo'=>Str::slug('Ampliación')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Andador',
            'codigo'=>Str::slug('Andador')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Avenida',
            'codigo'=>Str::slug('Avenida')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Boulevard',
            'codigo'=>Str::slug('Boulevard')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Calle',
            'codigo'=>Str::slug('Calle')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Callejón',
            'codigo'=>Str::slug('Callejón')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Calzada',
            'codigo'=>Str::slug('Calzada')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Cerrada',
            'codigo'=>Str::slug('Cerrada')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Circuito',
            'codigo'=>Str::slug('Circuito')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Circunvalación',
            'codigo'=>Str::slug('Circunvalación')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Continuación',
            'codigo'=>Str::slug('Continuación')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Corredor',
            'codigo'=>Str::slug('Corredor')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Diagonal',
            'codigo'=>Str::slug('Diagonal')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Eje Vial',
            'codigo'=>Str::slug('Eje Vial')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Pasaje',
            'codigo'=>Str::slug('Pasaje')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Peatonal',
            'codigo'=>Str::slug('Peatonal')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Periférico',
            'codigo'=>Str::slug('Periférico')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Privada',
            'codigo'=>Str::slug('Privada')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Prolongación',
            'codigo'=>Str::slug('Prolongación')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Retorno',
            'codigo'=>Str::slug('Retorno')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'Viaducto',
            'codigo'=>Str::slug('Viaducto')
        ]);
        CatalogoElemento::updateOrCreate([
            'catalogo_id'=>$cat->id,
            'nombre'=>'No identificada',
            'codigo'=>Str::slug('No identificada')
        ]);
    }
}
