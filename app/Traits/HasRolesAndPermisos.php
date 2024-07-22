<?php


namespace App\Traits;

use App\Models\Roles\Roles;
use App\Models\Roles\Permisos;

use Log;

trait HasRolesAndPermisos
{
/*
	public function isAdmin()
    {
        if($this->roles->contains('slug', 'admin')){
            return true;
        }
    }
*/
	public function roles()
	{
		return $this->belongsToMany(Roles::class,'users_roles');

	}

	public function permisos()
	{
		return $this->belongsToMany(Permisos::class,'users_permisos');

	}

	 public function hasRol($role)
    {        
        Log::info($role);
        if( strpos($role, ',') !== false ){//check if this is an list of roles

            $listOfRoles = explode(',',$role);

            foreach ($listOfRoles as $role) {                    
                if ($this->roles->contains('slug', $role)) {
                    return true;
                }
            }
        }else{                
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }

        return false;
    }

}
