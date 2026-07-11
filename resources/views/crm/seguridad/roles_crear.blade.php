@extends('crm.layout')

@section('content')
<div class="title">Crear rol</div>
<div class="subtitle">Alta de perfiles de acceso para las consolas ZIGO.</div>

<div class="card">
    <form method="POST" action="{{ route('crm.seguridad.roles.guardar') }}">
        @csrf

        <label>Nombre</label>
        <input name="name" required style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;">

        <label>Slug</label>
        <input name="slug" required placeholder="ej. soporte, cliente_b2b, api_client" style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:20px;">

        <button class="btn" type="submit">Guardar rol</button>
        <a class="btn" style="background:#64748b" href="{{ route('crm.seguridad.roles') }}">Cancelar</a>
    </form>
</div>
@endsection