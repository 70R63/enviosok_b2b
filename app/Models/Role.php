<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'roles_permisos',
            'roles_id',
            'permisos_id'
        );
    }
}