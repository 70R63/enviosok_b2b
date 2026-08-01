@extends('crm.layout')
@section('content')
<div class="title">Editar identidad</div><div class="subtitle">Actualización protegida de datos y clasificación.</div>
@include('crm.seguridad.partials.nav')
@if($tipoActual === null)<div class="alert alert-info">Esta identidad no tiene una clasificación determinista. Selecciona explícitamente un tipo válido para corregirla.</div>@endif
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="card"><form method="POST" action="{{ route('crm.seguridad.usuarios.actualizar',$user) }}">@csrf
@include('crm.seguridad.partials.user_form')
<button class="btn" type="submit">Actualizar usuario</button> <a class="btn btn-gray" href="{{ route('crm.seguridad.usuarios') }}">Cancelar</a>
</form></div>
@endsection
