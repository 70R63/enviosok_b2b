<?php

namespace App\Models\API;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

//General
use Log;
class Guia extends Model
{
    use HasFactory;



    /**
     * Realiza la consulta de guias pendientes de entrega.
     *
     * @param    $query
     * @param  Integer $ltdId
     * 
     * @return $query
     */

    public function scopePendienteEntrega($query, $ltdId) {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        
        $query->select('id','ltd_id', 'tracking_number')
                ->where('ltd_id',$ltdId)            
                ->whereIN('rastreo_estatus',array(1,2,3,6))
                //->offset(0)->limit(10)
                ;
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        return $query;
   }

}
