<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatalogoElemento extends Model
{
    use HasFactory,SoftDeletes;
    protected $table='catalogo_elementos';
    protected $guarded=[];

    public function padre(){
        return $this->belongsTo(CatalogoElemento::class,'padre_id');
    }
}
