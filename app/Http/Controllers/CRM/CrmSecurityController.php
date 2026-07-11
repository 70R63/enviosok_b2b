<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class CrmSecurityController extends Controller
{
    private function validarSysadmin()
    {
        $role = optional(auth()->user()->roles->first())->slug;

        if ($role !== 'sysadmin') {
            abort(403, 'Solo SYSADMIN puede administrar seguridad.');
        }
    }

    public function index()
    {
        $this->validarSysadmin();

        return view('crm.seguridad.index', [
            'usuarios' => User::count(),
            'roles' => DB::table('roles')->count(),
            'permisos' => DB::table('permisos')->count(),
        ]);
    }

    public function usuarios()
    {
        $this->validarSysadmin();

        $usuarios = User::with('roles')->orderBy('id')->get();

        return view('crm.seguridad.usuarios', compact('usuarios'));
    }

    public function roles()
    {
        $this->validarSysadmin();

        $roles = DB::table('roles')->orderBy('id')->get();

        return view('crm.seguridad.roles', compact('roles'));
    }

    public function permisos()
    {
        $this->validarSysadmin();

        $permisos = DB::table('permisos')->orderBy('id')->get();

        return view('crm.seguridad.permisos', compact('permisos'));
    }

    public function crearUsuario()
{
    $this->validarSysadmin();

    $roles = DB::table('roles')->orderBy('name')->get();

    return view('crm.seguridad.usuarios_crear', compact('roles'));
}

    public function guardarUsuario(Request $request)
    {
        $this->validarSysadmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles_id' => ['required', 'integer'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        DB::table('users_roles')->insert([
            'user_id' => $user->id,
            'roles_id' => $data['roles_id'],
        ]);

        return redirect()
            ->route('crm.seguridad.usuarios')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function editarUsuario(User $user)
    {
        $this->validarSysadmin();

        $roles = DB::table('roles')->orderBy('name')->get();
        $rolActual = DB::table('users_roles')->where('user_id', $user->id)->value('roles_id');

        return view('crm.seguridad.usuarios_editar', compact('user', 'roles', 'rolActual'));
    }

    public function actualizarUsuario(Request $request, User $user)
    {
        $this->validarSysadmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'roles_id' => ['required', 'integer'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        DB::table('users_roles')->where('user_id', $user->id)->delete();

        DB::table('users_roles')->insert([
            'user_id' => $user->id,
            'roles_id' => $data['roles_id'],
        ]);

        return redirect()
            ->route('crm.seguridad.usuarios')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function eliminarUsuario(User $user)
    {
        $this->validarSysadmin();

        $role = optional($user->roles->first())->slug;

        if ($role === 'sysadmin') {
            return redirect()
                ->route('crm.seguridad.usuarios')
                ->with('success', 'No se puede eliminar un usuario SYSADMIN.');
        }

        DB::table('users_roles')->where('user_id', $user->id)->delete();
        $user->delete();

        return redirect()
            ->route('crm.seguridad.usuarios')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    public function crearRol()
{
    $this->validarSysadmin();

    return view('crm.seguridad.roles_crear');
}

public function guardarRol(Request $request)
{
    $this->validarSysadmin();

    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'slug' => ['required', 'string', 'max:100', 'unique:roles,slug'],
    ]);

    DB::table('roles')->insert([
        'name' => $data['name'],
        'slug' => strtolower(trim($data['slug'])),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()->route('crm.seguridad.roles')
        ->with('success', 'Rol creado correctamente.');
}

public function editarRol($id)
{
    $this->validarSysadmin();

    $role = DB::table('roles')->where('id', $id)->first();

    abort_if(!$role, 404);

    return view('crm.seguridad.roles_editar', compact('role'));
}

public function actualizarRol(Request $request, $id)
    {
        $this->validarSysadmin();

        $role = DB::table('roles')->where('id', $id)->first();

        abort_if(!$role, 404);

        if ($role->slug === 'sysadmin') {
            return redirect()->route('crm.seguridad.roles')
                ->with('success', 'El rol SYSADMIN no puede editarse.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:roles,slug,' . $id],
        ]);

        DB::table('roles')->where('id', $id)->update([
            'name' => $data['name'],
            'slug' => strtolower(trim($data['slug'])),
            'updated_at' => now(),
        ]);

        return redirect()->route('crm.seguridad.roles')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function eliminarRol($id)
    {
        $this->validarSysadmin();

        $role = DB::table('roles')->where('id', $id)->first();

        abort_if(!$role, 404);

        $rolesProtegidos = [
            'sysadmin',
            'admin',
            'cliente',
        ];

        if (in_array($role->slug, $rolesProtegidos)) {
            return redirect()
                ->route('crm.seguridad.roles')
                ->with('success', 'Este rol base no puede eliminarse.');
        }

        $usuariosConRol = DB::table('users_roles')
            ->where('roles_id', $id)
            ->count();

        if ($usuariosConRol > 0) {
            return redirect()
                ->route('crm.seguridad.roles')
                ->with('success', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        DB::table('roles_permisos')->where('roles_id', $id)->delete();
        DB::table('roles')->where('id', $id)->delete();

        return redirect()
            ->route('crm.seguridad.roles')
            ->with('success', 'Rol eliminado correctamente.');
    }

    public function permisosRol($id)
    {
        $this->validarSysadmin();

        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);

        $permisos = DB::table('permisos')->orderBy('id')->get();

        $permisosAsignados = DB::table('roles_permisos')
            ->where('roles_id', $id)
            ->pluck('permisos_id')
            ->toArray();

        return view('crm.seguridad.roles_permisos', compact('role', 'permisos', 'permisosAsignados'));
    }

    public function guardarPermisosRol(Request $request, $id)
    {
        $this->validarSysadmin();

        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);

        DB::table('roles_permisos')->where('roles_id', $id)->delete();

        foreach ($request->input('permisos', []) as $permisoId) {
            DB::table('roles_permisos')->insert([
                'roles_id' => $id,
                'permisos_id' => $permisoId,
            ]);
        }

        return redirect()
            ->route('crm.seguridad.roles')
            ->with('success', 'Permisos actualizados correctamente.');
    }

    public function crearPermiso()
    {
        $this->validarSysadmin();

        return view('crm.seguridad.permisos_crear');
    }

    public function guardarPermiso(Request $request)
    {
        $this->validarSysadmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100'],
        ]);

        DB::table('permisos')->insert([
            'name' => $data['name'],
            'slug' => strtolower(trim($data['slug'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('crm.seguridad.permisos')
            ->with('success', 'Permiso creado correctamente.');
    }

    public function editarPermiso($id)
    {
        $this->validarSysadmin();

        $permiso = DB::table('permisos')->where('id', $id)->first();

        abort_if(!$permiso, 404);

        return view('crm.seguridad.permisos_editar', compact('permiso'));
    }

    public function actualizarPermiso(Request $request, $id)
    {
        $this->validarSysadmin();

        $permiso = DB::table('permisos')->where('id', $id)->first();

        abort_if(!$permiso, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100'],
        ]);

        DB::table('permisos')->where('id', $id)->update([
            'name' => $data['name'],
            'slug' => strtolower(trim($data['slug'])),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('crm.seguridad.permisos')
            ->with('success', 'Permiso actualizado correctamente.');
    }

    public function eliminarPermiso($id)
    {
        $this->validarSysadmin();

        $asignado = DB::table('roles_permisos')
            ->where('permisos_id', $id)
            ->count();

        if ($asignado > 0) {
            return redirect()
                ->route('crm.seguridad.permisos')
                ->with('success', 'No se puede eliminar el permiso porque está asignado a uno o más roles.');
        }

        DB::table('permisos')->where('id', $id)->delete();

        return redirect()
            ->route('crm.seguridad.permisos')
            ->with('success', 'Permiso eliminado correctamente.');
    }
}