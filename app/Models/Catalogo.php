<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Catalogo extends Model
{
    use HasFactory;
    protected $table='catalogos';
    protected $guarded=[];

    public function elementos(){
        return $this->hasMany(CatalogoElemento::class,'catalogo_id','id');
    }

    public function padre(){
        return $this->belongsTo(Catalogo::class,'padre_id');
    }

    public function hijos(){
        return $this->hasMany(Catalogo::class,'padre_id','id');
    }
}
