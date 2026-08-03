@extends('devops.layout')
@section('title', 'Nuevo paquete - ZIGO DevOps')
@section('content')
<div class="title">Nuevo paquete</div><div class="subtitle">Carga un ZIP para registrarlo. El contenido no se ejecutará durante la validación.</div>
@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="card" method="POST" action="{{ url('/deployments') }}" enctype="multipart/form-data">
@csrf
<div style="display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px;">
<div><label for="environment"><strong>Ambiente</strong></label><select id="environment" name="environment" required><option value="stage" @selected(old('environment') === 'stage')>Stage</option><option value="production" @selected(old('environment') === 'production')>PRD</option></select></div>
<div><label for="package"><strong>Paquete ZIP</strong></label><input id="package" type="file" name="package" accept=".zip,application/zip" required><div class="muted">Máximo {{ config('zigo_devops.max_package_mb') }} MB. Debe incluir deploy-manifest.json.</div></div>
</div><div style="display:flex;justify-content:flex-end;margin-top:22px;"><button class="btn" type="submit">Registrar paquete</button></div>
</form>
@endsection
