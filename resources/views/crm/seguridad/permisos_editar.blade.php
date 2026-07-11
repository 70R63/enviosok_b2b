@extends('crm.layout')

@section('content')

<div class="title">Editar permiso</div>
<div class="subtitle">Actualización del permiso seleccionado.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.permisos.actualizar', $permiso->id) }}">
        @csrf

        <label>Nombre</label>
        <input name="name" value="{{ old('name', $permiso->name) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Slug</label>
        <input name="slug" value="{{ old('slug', $permiso->slug) }}" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">

        <button class="btn" type="submit">Actualizar permiso</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.permisos') }}">Cancelar</a>
    </form>
</div>

@endsection