@extends('crm.layout')

@section('content')
    <div class="title">Usuarios</div>
    <div class="subtitle">Usuarios registrados y roles asignados.</div>

    <div style="margin-bottom:18px;">
        <a class="btn" href="{{ route('crm.seguridad.usuarios.crear') }}">
            + Crear usuario
        </a>
    </div>

    @if(session('success'))
        <div class="card" style="background:#dcfce7;color:#166534;font-weight:900;">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @foreach($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->id }}</td>
                        <td>{{ $usuario->name }}</td>
                        <td>{{ $usuario->email }}</td>
                        <td>
                            @forelse($usuario->roles as $role)
                                <span>{{ $role->slug }}</span>
                            @empty
                                -
                            @endforelse
                        </td>
                        <td>
                            <a class="btn" href="{{ route('crm.seguridad.usuarios.editar', $usuario->id) }}">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('crm.seguridad.usuarios.eliminar', $usuario->id) }}" style="display:inline;">
                                @csrf
                                <button class="btn" style="background:#ef4444" type="submit" onclick="return confirm('¿Eliminar este usuario?')">
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