<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Ltd extends Model
{
    use HasFactory;

    protected $guarded = []; 

    /**
     * Agraga a la consulta el estatus 1.
     *
     * 
    */

    protected static function boot()
    {
        parent::boot();        
        static::addGlobalScope('estatus', function (Builder $builder) {
            $builder->where('ltds.estatus', '1');
        });
    }

}
