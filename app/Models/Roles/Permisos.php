<?php

namespace App\Models\Roles;

use Illuminate\Database\Eloquent\Model;

class Permisos extends Model
{

   protected $table = 'permisos';
   protected $guarded = [];

      public function roles()
    {
        return $this->belongsToMany(Roles::class,"roles_permisos");
    }
}
