<?php

namespace App\Traits;

use App\Models\Roles\Roles;
use App\Models\Roles\Permisos;
use Illuminate\Support\Facades\DB;

trait HasRolesAndPermisos
{
    public function roles()
    {
        return $this->belongsToMany(
            Roles::class,
            'users_roles',
            'user_id',
            'roles_id'
        );
    }

    public function permisos()
    {
        return $this->belongsToMany(
            Permisos::class,
            'users_permisos',
            'user_id',
            'permisos_id'
        );
    }

    public function hasRol($role)
    {
        if (strpos($role, ',') !== false) {
            $roles = explode(',', $role);

            foreach ($roles as $rol) {
                if ($this->roles->contains('slug', trim($rol))) {
                    return true;
                }
            }

            return false;
        }

        return $this->roles->contains('slug', trim($role));
    }

    public function hasPermiso(string $permisoSlug): bool
    {
        return DB::table('users_roles')
            ->join('roles_permisos', 'users_roles.roles_id', '=', 'roles_permisos.roles_id')
            ->join('permisos', 'roles_permisos.permisos_id', '=', 'permisos.id')
            ->where('users_roles.user_id', $this->id)
            ->where('permisos.slug', $permisoSlug)
            ->exists();
    }

    public function hasAnyPermiso(array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($this->hasPermiso($permiso)) {
                return true;
            }
        }

        return false;
    }
}