@extends('crm.layout')

@section('content')

<div class="title">Permisos del rol</div>
<div class="subtitle">{{ $role->name }} - {{ $role->slug }}</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.roles.permisos.guardar', $role->id) }}">
        @csrf

        <table>
            <thead>
                <tr>
                    <th width="80">Asignar</th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Slug</th>
                </tr>
            </thead>

            <tbody>
                @foreach($permisos as $permiso)
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                name="permisos[]"
                                value="{{ $permiso->id }}"
                                {{ in_array($permiso->id, $permisosAsignados) ? 'checked' : '' }}
                            >
                        </td>
                        <td>{{ $permiso->id }}</td>
                        <td>{{ $permiso->name }}</td>
                        <td>{{ $permiso->slug }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <button class="btn" type="submit">Guardar permisos</button>
            <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.roles') }}">Cancelar</a>
        </div>
    </form>
</div>

@endsection