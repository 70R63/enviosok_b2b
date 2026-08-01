<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CRM\CrmUserClassificationService;
use App\Services\CRM\CrmUserDependencyService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CrmSecurityController extends Controller
{
    private const BASE_ROLES = [
        'sysadmin',
        'admin',
        'adminops',
        'operaciones',
        'auditoria',
        'contraloria',
        'comercial',
        'cliente',
        'usuario',
    ];

    public function __construct(
        private CrmUserClassificationService $classification,
        private CrmUserDependencyService $dependencies
    ) {
    }

    public function index()
    {
        $this->authorizeSecurityAccess();
        $users = User::with('roles')->get();
        $counts = [
            'internos' => 0,
            'b2b' => 0,
            'b2c' => 0,
            'inactivos' => 0,
            'sin_clasificar' => 0,
        ];

        foreach ($users as $user) {
            $counts[$this->classification->classify($user)]++;
        }

        $counts['roles'] = DB::table('roles')->count();

        return view('crm.seguridad.index', compact('counts'));
    }

    public function usuarios(Request $request)
    {
        $this->authorizeSecurityAccess();
        $tab = in_array($request->query('tab'), [
            'internos', 'b2b', 'b2c', 'inactivos', 'sin_clasificar',
        ], true) ? $request->query('tab') : 'internos';

        $users = User::with('roles')->orderByDesc('updated_at')->get();
        $companyNames = Schema::hasTable('empresas')
            ? DB::table('empresas')->pluck('nombre', 'id')
            : collect();
        $verifiedUserIds = Schema::hasTable('b2c_identity_verifications')
            ? DB::table('b2c_identity_verifications')
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
            : collect();

        $users->each(function (User $user) {
            $user->security_type = $this->classification->classify($user);
        });
        $users->each(function (User $user) use ($companyNames, $verifiedUserIds) {
            $user->company_name = $companyNames->get($user->empresa_id);
            $user->verification_available = $verifiedUserIds
                ->contains((int) $user->id);
        });

        $filtered = $this->filterUsers($users, $request, $tab);
        $usuarios = $this->paginate($filtered, $request);
        $roles = DB::table('roles')->orderBy('name')->get();
        $empresas = Schema::hasTable('empresas')
            ? DB::table('empresas')->orderBy('nombre')->get()
            : collect();
        $isSysadmin = $this->isSysadmin();

        return view('crm.seguridad.usuarios', compact(
            'usuarios', 'roles', 'empresas', 'tab', 'isSysadmin'
        ));
    }

    public function crearUsuario()
    {
        $this->authorizeSecurityAccess();

        return view('crm.seguridad.usuarios_crear', $this->formOptions());
    }

    public function guardarUsuario(Request $request)
    {
        $this->authorizeSecurityAccess();
        $data = $this->validateUser($request);
        [$role, $empresaId] = $this->resolveAssignment($data);

        DB::transaction(function () use ($data, $role, $empresaId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'empresa_id' => $empresaId,
            ]);
            $user->roles()->sync([$role->id]);
        });

        return redirect()->route('crm.seguridad.usuarios')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function editarUsuario(User $user)
    {
        $this->authorizeUserMutation($user);
        $options = $this->formOptions();
        $options['user'] = $user->load('roles');
        $options['rolActual'] = optional($user->roles->first())->id;
        $options['tipoActual'] = $this->classification->formType($user);

        return view('crm.seguridad.usuarios_editar', $options);
    }

    public function actualizarUsuario(Request $request, User $user)
    {
        $this->authorizeUserMutation($user);
        $data = $this->validateUser($request, $user);
        [$role, $empresaId] = $this->resolveAssignment($data, $user);

        DB::transaction(function () use ($data, $role, $empresaId, $user) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'empresa_id' => $empresaId,
            ]);

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $user->roles()->sync([$role->id]);
        });

        return redirect()->route('crm.seguridad.usuarios')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function eliminarUsuario(User $user)
    {
        $this->authorizeUserMutation($user);

        if ((int) auth()->id() === (int) $user->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($this->hasRole($user, 'sysadmin') && $this->sysadminCount() <= 1) {
            return back()->with('error', 'No se puede eliminar al último sysadmin.');
        }

        $dependencies = $this->dependencies->detect($user);
        if ($dependencies !== []) {
            $detail = collect($dependencies)
                ->map(fn ($item) => $item['label'] . ': ' . $item['count'])
                ->implode(', ');

            return back()->with(
                'error',
                'No se puede eliminar: existen dependencias operativas ('
                . $detail . ').'
            );
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            DB::table('users_permisos')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function roles()
    {
        $this->authorizeSecurityAccess();
        $roles = DB::table('roles')
            ->leftJoin('users_roles', 'roles.id', '=', 'users_roles.roles_id')
            ->leftJoin('roles_permisos', 'roles.id', '=', 'roles_permisos.roles_id')
            ->select('roles.id', 'roles.name', 'roles.slug')
            ->selectRaw('COUNT(DISTINCT users_roles.user_id) AS users_count')
            ->selectRaw('COUNT(DISTINCT roles_permisos.permisos_id) AS permissions_count')
            ->groupBy('roles.id', 'roles.name', 'roles.slug')
            ->orderBy('roles.name')
            ->get();

        return view('crm.seguridad.roles', [
            'roles' => $roles,
            'isSysadmin' => $this->isSysadmin(),
            'baseRoles' => self::BASE_ROLES,
        ]);
    }

    public function permisos()
    {
        $this->authorizeSecurityAccess();
        $permisos = DB::table('permisos')->orderBy('id')->get();

        return view('crm.seguridad.permisos', compact('permisos'));
    }

    public function crearRol()
    {
        $this->authorizeSysadmin();
        return view('crm.seguridad.roles_crear');
    }

    public function guardarRol(Request $request)
    {
        $this->authorizeSysadmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:roles,slug'],
        ]);
        DB::table('roles')->insert([
            'name' => $data['name'],
            'slug' => mb_strtolower(trim($data['slug'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('crm.seguridad.roles')
            ->with('success', 'Rol creado correctamente.');
    }

    public function editarRol($id)
    {
        $this->authorizeSysadmin();
        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);
        abort_if(in_array($role->slug, self::BASE_ROLES, true), 403, 'Los roles base no pueden editarse.');
        return view('crm.seguridad.roles_editar', compact('role'));
    }

    public function actualizarRol(Request $request, $id)
    {
        $this->authorizeSysadmin();
        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);
        abort_if(in_array($role->slug, self::BASE_ROLES, true), 403, 'Los roles base no pueden editarse.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('roles', 'slug')->ignore($id)],
        ]);
        DB::table('roles')->where('id', $id)->update([
            'name' => $data['name'],
            'slug' => mb_strtolower(trim($data['slug'])),
            'updated_at' => now(),
        ]);
        return redirect()->route('crm.seguridad.roles')->with('success', 'Rol actualizado correctamente.');
    }

    public function eliminarRol($id)
    {
        $this->authorizeSysadmin();
        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);

        if (in_array($role->slug, self::BASE_ROLES, true)) {
            return back()->with('error', 'Los roles base no pueden eliminarse.');
        }

        if (DB::table('users_roles')->where('roles_id', $id)->exists()) {
            return back()->with('error', 'El rol tiene usuarios asignados.');
        }

        DB::transaction(function () use ($id) {
            DB::table('roles_permisos')->where('roles_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        });

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    public function permisosRol($id)
    {
        $this->authorizeSysadmin();
        $role = DB::table('roles')->where('id', $id)->first();
        abort_if(!$role, 404);
        $permisos = DB::table('permisos')->orderBy('id')->get();
        $groups = $permisos->groupBy(function ($permission) {
            $slug = trim((string) $permission->slug);
            return str_contains($slug, '.')
                ? ucfirst(strtok($slug, '.'))
                : 'General';
        });
        $permisosAsignados = DB::table('roles_permisos')
            ->where('roles_id', $id)->pluck('permisos_id')->all();

        return view('crm.seguridad.roles_permisos', compact(
            'role', 'groups', 'permisosAsignados'
        ));
    }

    public function guardarPermisosRol(Request $request, $id)
    {
        $this->authorizeSysadmin();
        abort_unless(DB::table('roles')->where('id', $id)->exists(), 404);
        $data = $request->validate([
            'permisos' => ['array'],
            'permisos.*' => ['integer', 'exists:permisos,id'],
        ]);
        DB::transaction(function () use ($id, $data) {
            DB::table('roles_permisos')->where('roles_id', $id)->delete();
            foreach ($data['permisos'] ?? [] as $permissionId) {
                DB::table('roles_permisos')->insert([
                    'roles_id' => $id,
                    'permisos_id' => $permissionId,
                ]);
            }
        });
        return redirect()->route('crm.seguridad.roles')->with('success', 'Permisos actualizados correctamente.');
    }

    public function crearPermiso() { $this->denyPermissionCrud(); }
    public function guardarPermiso(Request $request) { $this->denyPermissionCrud(); }
    public function editarPermiso($id) { $this->denyPermissionCrud(); }
    public function actualizarPermiso(Request $request, $id) { $this->denyPermissionCrud(); }
    public function eliminarPermiso($id) { $this->denyPermissionCrud(); }

    public function auditoria()
    {
        $this->authorizeSecurityAccess();
        $activity = User::with('roles')->orderByDesc('updated_at')->paginate(25);
        return view('crm.seguridad.auditoria', compact('activity'));
    }

    private function filterUsers(Collection $users, Request $request, string $tab): Collection
    {
        if ($tab === 'inactivos') {
            return collect();
        }

        return $users->filter(function (User $user) use ($request, $tab) {
            if ($user->security_type !== $tab) return false;
            $search = mb_strtolower(trim((string) $request->query('q')));
            if ($search !== '' && !str_contains(mb_strtolower($user->name . ' ' . $user->email), $search)) return false;
            $role = trim((string) $request->query('role'));
            if ($role !== '' && !$user->roles->contains('slug', $role)) return false;
            $company = $request->query('empresa_id');
            if ($company !== null && $company !== '' && (int) $user->empresa_id !== (int) $company) return false;
            $status = $request->query('status');
            return $status === null || $status === '' || $status === 'active';
        })->values();
    }

    private function paginate(Collection $items, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        return new LengthAwarePaginator(
            $items->forPage($page, 20)->values(),
            $items->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function formOptions(): array
    {
        $roles = DB::table('roles')->orderBy('name')->get();
        return [
            'internalRoles' => $roles->whereIn('slug', $this->classification->internalRoles())->values(),
            'businessRoles' => $roles->whereIn('slug', $this->classification->businessRoles())->values(),
            'empresas' => Schema::hasTable('empresas') ? DB::table('empresas')->orderBy('nombre')->get() : collect(),
            'b2cConfigured' => $this->classification->validB2cCompanyId() !== null,
            'isSysadmin' => $this->isSysadmin(),
        ];
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'user_type' => ['required', Rule::in(['interno', 'b2b', 'b2c_asistido'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'roles_id' => ['nullable', 'integer', 'exists:roles,id'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
            'status' => ['required', Rule::in(['active'])],
        ]);
    }

    private function resolveAssignment(array $data, ?User $user = null): array
    {
        $type = $data['user_type'];
        if ($type === 'b2c_asistido') {
            $companyId = $this->classification->validB2cCompanyId();
            abort_if($companyId === null, 422, 'La empresa pública B2C no está configurada.');
            $role = DB::table('roles')->where('slug', 'cliente')->first();
            abort_if(!$role, 422, 'El rol B2C cliente no está disponible.');
            return [$role, $companyId];
        }

        $role = DB::table('roles')->where('id', $data['roles_id'])->first();
        abort_if(!$role, 422, 'Selecciona un rol válido.');
        $allowed = $type === 'interno'
            ? $this->classification->internalRoles()
            : $this->classification->businessRoles();
        abort_unless(in_array($role->slug, $allowed, true), 422, 'La combinación de tipo y rol no es válida.');
        abort_if(
            !$this->isSysadmin()
            && in_array($role->slug, ['sysadmin', 'admin'], true),
            403,
            'Un admin no puede asignar roles administrativos superiores.'
        );

        if ($type === 'b2b') {
            abort_if(empty($data['empresa_id']), 422, 'La empresa es obligatoria para usuarios B2B.');
            return [$role, (int) $data['empresa_id']];
        }

        return [$role, (int) ($user?->empresa_id ?: auth()->user()->empresa_id)];
    }

    private function authorizeSecurityAccess(): void
    {
        abort_unless(auth()->user()->hasRol('sysadmin,admin'), 403);
    }

    private function authorizeSysadmin(): void
    {
        abort_unless($this->isSysadmin(), 403, 'Solo sysadmin puede realizar esta operación.');
    }

    private function authorizeUserMutation(User $user): void
    {
        $this->authorizeSecurityAccess();
        abort_if(
            !$this->isSysadmin()
            && ($this->hasRole($user, 'sysadmin') || $this->hasRole($user, 'admin')),
            403,
            'Un admin no puede modificar usuarios admin o sysadmin.'
        );
    }

    private function isSysadmin(): bool
    {
        return auth()->user()->hasRol('sysadmin');
    }

    private function hasRole(User $user, string $slug): bool
    {
        return $user->roles->contains('slug', $slug);
    }

    private function sysadminCount(): int
    {
        return DB::table('users_roles')->join('roles', 'roles.id', '=', 'users_roles.roles_id')->where('roles.slug', 'sysadmin')->distinct('users_roles.user_id')->count('users_roles.user_id');
    }

    private function denyPermissionCrud(): never
    {
        abort(403, 'El catálogo de permisos es de solo lectura.');
    }
}
