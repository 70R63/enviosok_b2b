@extends('crm.layout')

@section('content')

<div class="title">Editar usuario</div>
<div class="subtitle">Actualiza datos, contraseña y rol del usuario.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.usuarios.actualizar', $user->id) }}">
        @csrf

        <label>Nombre</label>
        <input name="name" value="{{ old('name', $user->name) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Correo electrónico</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Nueva contraseña opcional</label>
        <input type="password" name="password" placeholder="Dejar vacío para conservar la actual" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Rol</label>
        <select name="roles_id" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">
            <option value="">Selecciona rol</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ (int)$rolActual === (int)$role->id ? 'selected' : '' }}>
                    {{ $role->name }} - {{ $role->slug }}
                </option>
            @endforeach
        </select>

        <button class="btn" type="submit">Actualizar usuario</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.usuarios') }}">Cancelar</a>
    </form>
</div>

@endsection