<?php

namespace App\Models\API;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;

class Reportes extends Model
{
    use HasFactory;

    protected $guarded = []; 

    public function scopeTipo($query, $tipo_id) {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        $query->where('tipo', $tipo_id);

        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        return $query;
    }
}
