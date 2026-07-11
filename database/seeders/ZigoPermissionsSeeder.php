<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZigoPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles_permisos')->delete();
        DB::table('permisos')->delete();

        $permissions = [
            'crm.dashboard.ver',

            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',

            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',
            'roles.permisos',

            'permisos.ver',
            'permisos.crear',
            'permisos.editar',
            'permisos.eliminar',

            'empresas.ver',
            'empresas.crear',
            'empresas.editar',
            'empresas.eliminar',

            'prospectos.ver',
            'prospectos.crear',
            'prospectos.editar',
            'prospectos.asignar',

            'incidencias.ver',
            'incidencias.responder',
            'incidencias.cerrar',
            'incidencias.asignar',

            'guias.ver',
            'guias.crear',
            'guias.cancelar',
            'guias.rastrear',

            'pagos.ver',
            'pagos.validar',
            'adeudos.ver',
            'adeudos.crear',
            'adeudos.liquidar',

            'api.ver',
            'api.keys.crear',
            'api.keys.revocar',
            'api.logs.ver',
            'api.consumo.ver',

            'soporte.dashboard.ver',
            'negocios.dashboard.ver',
            'hub.dashboard.ver',
        ];

        foreach ($permissions as $permission) {
            DB::table('permisos')->insert([
                'name' => ucwords(str_replace(['.', '_'], ' ', $permission)),
                'slug' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sysadmin = DB::table('roles')->where('slug', 'sysadmin')->first();

        if ($sysadmin) {
            $allPermissions = DB::table('permisos')->pluck('id');

            foreach ($allPermissions as $permissionId) {
                DB::table('roles_permisos')->insert([
                    'roles_id' => $sysadmin->id,
                    'permisos_id' => $permissionId,
                ]);
            }
        }
    }
}
