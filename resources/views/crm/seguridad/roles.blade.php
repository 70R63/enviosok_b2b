@extends('crm.layout')
@section('content')
<div class="title">Roles</div><div class="subtitle">Perfiles de acceso y alcance asignado.</div>
@include('crm.seguridad.partials.nav')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if($isSysadmin)<div style="margin-bottom:18px"><a class="btn" href="{{ route('crm.seguridad.roles.crear') }}">+ Crear rol</a></div>@endif
<div class="card table-wrap"><table><thead><tr><th>Nombre</th><th>Descripción</th><th>Usuarios</th><th>Permisos</th><th>Acciones</th></tr></thead><tbody>
@foreach($roles as $role)<tr>
<td><strong>{{ $role->name }}</strong><div class="muted">{{ $role->slug }}</div></td>
<td class="muted">Sin descripción disponible</td><td>{{ $role->users_count }}</td><td>{{ $role->permissions_count }}</td>
<td><div class="table-actions">
@if($isSysadmin)
<a class="btn btn-small" style="background:#0f766e" href="{{ route('crm.seguridad.roles.permisos',$role->id) }}">Permisos</a>
@if(!in_array($role->slug,$baseRoles,true))
<a class="btn btn-small" href="{{ route('crm.seguridad.roles.editar',$role->id) }}">Editar</a>
<form method="POST" action="{{ route('crm.seguridad.roles.eliminar',$role->id) }}">@csrf<button class="btn btn-small" style="background:#dc2626" onclick="return confirm('¿Eliminar este rol?')">Eliminar</button></form>
@endif
@else<span class="muted">Consulta</span>@endif
</div></td></tr>@endforeach
</tbody></table></div>
@endsection
