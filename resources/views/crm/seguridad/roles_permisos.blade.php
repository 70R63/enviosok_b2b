@extends('crm.layout')
@section('content')
<div class="title">Permisos del rol</div><div class="subtitle">{{ $role->name }} — asignación exclusiva de sysadmin.</div>
@include('crm.seguridad.partials.nav')
<form method="POST" action="{{ route('crm.seguridad.roles.permisos.guardar',$role->id) }}">@csrf
@foreach($groups as $module=>$permissions)
<div class="card"><h2 style="margin-top:0">{{ $module }}</h2>
@if($module==='General')<p class="muted">Slugs sin módulo determinista; se conservan sin modificar.</p>@endif
<div class="table-wrap"><table style="min-width:600px"><thead><tr><th>Asignar</th><th>Nombre</th><th>Slug</th></tr></thead><tbody>
@foreach($permissions as $permission)<tr><td><input style="width:auto" type="checkbox" name="permisos[]" value="{{ $permission->id }}" @checked(in_array($permission->id,$permisosAsignados))></td><td>{{ $permission->name }}</td><td>{{ $permission->slug }}</td></tr>@endforeach
</tbody></table></div></div>
@endforeach
<button class="btn" type="submit">Guardar permisos</button> <a class="btn btn-gray" href="{{ route('crm.seguridad.roles') }}">Cancelar</a>
</form>
@endsection
