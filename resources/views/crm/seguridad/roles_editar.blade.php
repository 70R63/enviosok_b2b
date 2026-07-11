@extends('crm.layout')

@section('content')
<div class="title">Editar rol</div>
<div class="subtitle">Actualización de perfil de acceso.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.roles.actualizar', $role->id) }}">
        @csrf

        <label>Nombre</label>
        <input name="name" value="{{ old('name', $role->name) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Slug</label>
        <input name="slug" value="{{ old('slug', $role->slug) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">

        <button class="btn" type="submit">Actualizar rol</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.roles') }}">Cancelar</a>
    </form>
</div>
@endsection