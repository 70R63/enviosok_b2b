@extends('crm.layout')
@section('content')
<div class="title">Permisos</div><div class="subtitle">Catálogo técnico de solo lectura.</div>
@include('crm.seguridad.partials.nav')
<div class="alert alert-info">Los permisos continúan en base de datos, pero su CRUD operativo está deshabilitado. La asignación se administra desde Roles por un sysadmin.</div>
<div class="card table-wrap"><table style="min-width:600px"><thead><tr><th>Nombre</th><th>Slug</th><th>Módulo visual</th></tr></thead><tbody>
@foreach($permisos as $permiso)<tr><td>{{ $permiso->name }}</td><td>{{ $permiso->slug }}</td><td>{{ str_contains($permiso->slug,'.') ? ucfirst(strtok($permiso->slug,'.')) : 'General' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
