@extends('crm.layout')

@section('content')
<div class="title">Identidades</div>
<div class="subtitle">Usuarios de una sola base, organizados por su rol y contexto operativo.</div>
@include('crm.seguridad.partials.nav')

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error')) <div class="alert alert-error">{{ session('error') }}</div> @endif

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
    @foreach(['internos'=>'Personal ZIGO','b2b'=>'Clientes B2B','b2c'=>'Clientes B2C','inactivos'=>'Inactivos','sin_clasificar'=>'Por revisar'] as $key=>$label)
        <a class="btn btn-small {{ $tab === $key ? '' : 'btn-gray' }}" href="{{ route('crm.seguridad.usuarios', ['tab'=>$key]) }}">{{ $label }}</a>
    @endforeach
</div>

@if($tab === 'inactivos')
    <div class="alert alert-info">Inactivación no disponible: la tabla users no tiene un estado persistente. Esta función requiere soporte de datos posterior y no se simula en esta pantalla.</div>
@else
    <form class="card filters filters-invoices" method="GET" action="{{ route('crm.seguridad.usuarios') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input name="q" value="{{ request('q') }}" placeholder="Buscar nombre o correo">
        <select name="role"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(request('role')===$role->slug)>{{ $role->name }}</option>@endforeach</select>
        @if(in_array($tab, ['b2b','sin_clasificar'], true))
            <select name="empresa_id"><option value="">Todas las empresas</option>@foreach($empresas as $empresa)<option value="{{ $empresa->id }}" @selected((string)request('empresa_id')===(string)$empresa->id)>{{ $empresa->nombre }}</option>@endforeach</select>
        @endif
        <select name="status"><option value="">Todos los estados disponibles</option><option value="active" @selected(request('status')==='active')>Activo</option></select>
        <div><button class="btn" type="submit">Filtrar</button> <a class="btn btn-gray" href="{{ route('crm.seguridad.usuarios', ['tab'=>$tab]) }}">Limpiar</a></div>
    </form>
@endif

<div style="margin-bottom:18px"><a class="btn" href="{{ route('crm.seguridad.usuarios.crear') }}">+ Crear usuario</a></div>

<div class="card table-wrap">
<table><thead><tr>
    <th>Nombre</th><th>Correo</th>
    @if($tab==='b2b')<th>Empresa</th>@endif
    <th>Rol</th>
    @if($tab==='internos')<th>Portal o área</th>@endif
    @if($tab==='b2c')<th>Verificación disponible</th>@endif
    <th>Estado</th><th>{{ $tab==='internos' ? 'Actualización' : 'Registro' }}</th><th>Acciones</th>
</tr></thead><tbody>
@forelse($usuarios as $usuario)
<tr>
    <td>{{ $usuario->name }}</td><td>{{ $usuario->email }}</td>
    @if($tab==='b2b')<td>{{ $usuario->company_name ?: 'Sin empresa disponible' }}</td>@endif
    <td>{{ $usuario->roles->pluck('name')->implode(', ') ?: 'Sin rol' }}</td>
    @if($tab==='internos')<td>{{ optional($usuario->roles->first())->name ?: 'Sin área' }}</td>@endif
    @if($tab==='b2c')<td>{{ $usuario->verification_available ? 'Sí' : 'No' }}</td>@endif
    <td><span class="badge badge-green">Activo</span></td>
    <td>{{ ($tab==='internos' ? $usuario->updated_at : $usuario->created_at)?->format('d/m/Y H:i') ?: 'Sin fecha' }}</td>
    <td><div class="table-actions">
        @php($protectedForAdmin = !$isSysadmin && $usuario->roles->whereIn('slug', ['sysadmin', 'admin'])->isNotEmpty())
        @if(!$protectedForAdmin)
            <a class="btn btn-small" href="{{ route('crm.seguridad.usuarios.editar',$usuario) }}">Editar</a>
            <form method="POST" action="{{ route('crm.seguridad.usuarios.eliminar',$usuario) }}">@csrf<button class="btn btn-small" style="background:#dc2626" onclick="return confirm('¿Eliminar esta identidad?')">Eliminar</button></form>
        @else
            <span class="muted">Protegido</span>
        @endif
    </div></td>
</tr>
@empty<tr><td colspan="9" class="empty-state">No hay identidades para esta clasificación y filtros.</td></tr>@endforelse
</tbody></table>
<div class="pagination-wrap">{{ $usuarios->links() }}</div>
</div>
@endsection
