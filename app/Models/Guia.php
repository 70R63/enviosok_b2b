<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Log;

class Guia extends Model
{
    use HasFactory;

    protected $guarded = ['token'];


    /**
     * Agraga a la consulta los casos de negocio.
     *
     * 
    */

    protected static function boot()
    {
        parent::boot();        
        static::addGlobalScope('guia_empresa', function (Builder $builder) {
            $empresas = EmpresaEmpresas::where('id',auth()->user()->empresa_id)
                ->pluck('empresa_id')->toArray();

            $builder->whereIN('guias.empresa_id',$empresas);
            $builder->orderBy('guias.id', 'desc');
        });
    }

    public function scopeJoinSucursalAjuste($query) {
      Log::info(__CLASS__." ".__FUNCTION__);

      return $query->join('sucursals', 'sucursals.id', '=', 'guias.cia');
    }

    public function scopeJoinLtdAjuste($query) {
      Log::info(__CLASS__." ".__FUNCTION__);

      return $query->join('ltds', 'ltds.id', '=', 'guias.ltd_id');
    }

    public function scopeJoinEmpresaAjuste($query) {
      Log::info(__CLASS__." ".__FUNCTION__);

      return $query->join('empresas', 'empresas.id', '=', 'sucursals.empresa_id');
    }

}
