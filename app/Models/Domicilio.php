<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domicilio extends Model
{
    protected $table = 'domicilios';
    protected $guarded = [];
    use HasFactory,SoftDeletes;
    /**
     * Devuelve el modelo propietario del domicilio
     */
    public function modelo(){
        return $this->morphTo();
    }
    public function getDomicilioCompletoAttribute(){
        return $this->tipoVialidad->nombre.' '.$this->calle.' #'.$this->no_exterior.', '.$this->colonia.' '.$this->cp.', '.$this->municipio_alcaldia.', '.$this->estado;
    }
    public function getSepomexAttribute(){
        return SEPOMEX::where('d_codigo',$this->cp)->first();
    }
}
