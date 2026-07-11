@extends('crm.layout')

@section('content')

<div class="title">Crear permiso</div>
<div class="subtitle">Alta de permiso para asignarlo después a roles.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.permisos.guardar') }}">
        @csrf

        <label>Nombre</label>
        <input name="name" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Slug</label>
        <input name="slug" required placeholder="ej. usuarios.crear" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">

        <button class="btn" type="submit">Guardar permiso</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.permisos') }}">Cancelar</a>
    </form>
</div>

@endsection