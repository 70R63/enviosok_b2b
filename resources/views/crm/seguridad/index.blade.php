@extends('crm.layout')

@section('content')
    <div class="title">Seguridad</div>
    <div class="subtitle">Administración de usuarios, roles y permisos del sistema.</div>

    <div class="card">
        <p><strong>Usuarios:</strong> {{ $usuarios }}</p>
        <p><strong>Roles:</strong> {{ $roles }}</p>
        <p><strong>Permisos:</strong> {{ $permisos }}</p>

        <a class="btn" href="{{ route('crm.seguridad.usuarios') }}">Usuarios</a>
        <a class="btn" href="{{ route('crm.seguridad.roles') }}">Roles</a>
        <a class="btn" href="{{ route('crm.seguridad.permisos') }}">Permisos</a>
    </div>
@endsection