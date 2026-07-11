@extends('crm.layout')

@section('content')

<div class="title">Permisos</div>
<div class="subtitle">Catálogo general de permisos del sistema.</div>

<div style="margin-bottom:18px;">
    <a class="btn" href="{{ route('crm.seguridad.permisos.crear') }}">
        + Crear permiso
    </a>
</div>

@if(session('success'))
    <div class="card" style="background:#dcfce7;color:#166534;font-weight:900;margin-bottom:18px;">
        {{ session('success') }}
    </div>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th width="80">ID</th>
                <th>Nombre</th>
                <th>Slug</th>
                <th width="260">Acciones</th>
            </tr>
        </thead>

        <tbody>
            @foreach($permisos as $permiso)
                <tr>
                    <td>{{ $permiso->id }}</td>
                    <td>{{ $permiso->name }}</td>
                    <td>{{ $permiso->slug }}</td>
                    <td>
                        <a href="{{ route('crm.seguridad.permisos.editar', $permiso->id) }}" class="btn">
                            Editar
                        </a>

                        <form method="POST" action="{{ route('crm.seguridad.permisos.eliminar', $permiso->id) }}" style="display:inline;">
                            @csrf
                            <button class="btn" style="background:#ef4444" type="submit" onclick="return confirm('¿Eliminar este permiso?')">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection