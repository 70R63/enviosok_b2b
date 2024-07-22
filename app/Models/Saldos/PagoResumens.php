<?php

namespace App\Models\Saldos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EmpresaEmpresas;

use Log;

class PagoResumens extends Model
{
    use HasFactory;


    public function scopeEmpresas($query) {
        Log::info(__CLASS__." ".__FUNCTION__);

        $empresas = EmpresaEmpresas::where('id',auth()->user()->empresa_id)
                ->pluck('empresa_id')->toArray();

        return $query->whereIN('empresa_id',$empresas);

    }
    
    
}
