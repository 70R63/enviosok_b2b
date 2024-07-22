<?php

namespace App\Models\Saldos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use DateTimeInterface;
use Log;

class GuiasExternas extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','estatus','no_guias', 'importe_total', 'file_nombre'];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format("Y-m-d H:i:s");
    }

    public function scopeJoinUsuario($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('users', 'users.id', '=', 'user_id');
    }
}
