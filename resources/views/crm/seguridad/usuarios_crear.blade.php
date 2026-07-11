@extends('crm.layout')

@section('content')

<div class="title">Crear usuario</div>
<div class="subtitle">Alta de usuarios para CRM, Soporte, Negocios, API Hub o B2C.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.usuarios.guardar') }}">
        @csrf

        <label>Nombre</label>
        <input name="name" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Correo electrónico</label>
        <input type="email" name="email" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Contraseña</label>
        <input type="password" name="password" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Rol</label>
        <select name="roles_id" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">
            <option value="">Selecciona rol</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }} - {{ $role->slug }}</option>
            @endforeach
        </select>

        <button class="btn" type="submit">Guardar usuario</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.usuarios') }}">Cancelar</a>
    </form>
</div>

@endsection