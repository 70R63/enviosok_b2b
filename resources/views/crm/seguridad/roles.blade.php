@extends('crm.layout')

@section('content')

<div class="title">
    Roles del sistema
</div>

<div class="subtitle">
    Administración de perfiles de acceso de ZIGO.
</div>

<div style="margin-bottom:18px;">
    <a class="btn" href="{{ route('crm.seguridad.roles.crear') }}">
        + Crear rol
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
                <th width="170">Acciones</th>
            </tr>
        </thead>

        <tbody>

        @foreach($roles as $role)

            <tr>

                <td>{{ $role->id }}</td>

                <td>{{ $role->name }}</td>

                <td>{{ $role->slug }}</td>

                <td>

                    <a href="{{ route('crm.seguridad.roles.editar', $role->id) }}" class="btn">
                        Editar
                    </a>
                    <form method="POST" action="{{ route('crm.seguridad.roles.eliminar', $role->id) }}" style="display:inline;">
                        @csrf
                        <button class="btn" style="background:#ef4444" type="submit" onclick="return confirm('¿Eliminar este rol?')">
                            Eliminar
                        </button>
                        <a href="{{ route('crm.seguridad.roles.permisos', $role->id) }}" class="btn" style="background:#0f766e">
                            Permisos
                        </a>
                    </form>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>

@endsection