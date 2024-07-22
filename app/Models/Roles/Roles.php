<?php

namespace App\Models\Roles;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{

   protected $table = 'roles';
   protected $guarded = [];

   public function permisos()
    {
        return $this->belongsToMany(Permisos::class,"roles_permisos");
    }

       public function allRolesPermisos()
    {
        return $this->belongsToMany(Permisos::class,"roles_permisos");
    }


}
