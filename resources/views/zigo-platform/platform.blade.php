@extends('layouts.zigo-platform')
@section('title', 'Tu plataforma | ZIGO Platform')
@section('content')
<div class="wizard"><div class="steps"><span class="step active"></span><span class="step active"></span><span class="step active"></span><span class="step"></span></div>
<div class="card"><div class="eyebrow">Paso 3 de 4</div><h1>Tu plataforma</h1><p class="muted">Solicita el nombre de tu subdominio. Escribe únicamente el primer segmento.</p>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('zigo-platform.onboarding.platform.store',$application->public_token) }}">@csrf @method('PATCH')
<div class="field"><label for="requested_subdomain">Subdominio</label><input id="requested_subdomain" name="requested_subdomain" value="{{ old('requested_subdomain',$application->requested_subdomain) }}" placeholder="miempresa" required maxlength="63"><p class="muted">Vista previa: <strong><span id="preview">miempresa</span>{{ $domainSuffix }}.{{ $domainBase }}</strong></p></div>
<button class="btn btn-primary" type="submit">Revisar resumen</button></form></div></div>
<script>const i=document.getElementById('requested_subdomain'),p=document.getElementById('preview');i.addEventListener('input',()=>p.textContent=i.value.toLowerCase().replace(/[^a-z0-9-]/g,'')||'miempresa');</script>
@endsection
