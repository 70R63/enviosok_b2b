@extends('crm.layout')
@section('content')
<div class="title">Crear identidad</div><div class="subtitle">Alta asistida con combinaciones válidas de tipo, rol y empresa.</div>
@include('crm.seguridad.partials.nav')
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<div class="card"><form method="POST" action="{{ route('crm.seguridad.usuarios.guardar') }}">@csrf
@include('crm.seguridad.partials.user_form')
<button class="btn" type="submit">Guardar usuario</button> <a class="btn btn-gray" href="{{ route('crm.seguridad.usuarios') }}">Cancelar</a>
</form></div>
@endsection
