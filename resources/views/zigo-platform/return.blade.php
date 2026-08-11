@extends('layouts.zigo-platform')
@section('title', 'Estado de pago | ZIGO Platform')
@section('content')
<div class="wizard"><div class="card"><div class="eyebrow">Estado de tu solicitud</div>
@if(in_array($application->status,['PAID','PROVISIONING']))<h1>Pago recibido y estamos preparando tu plataforma</h1><p class="muted">La activación se realizará cuando finalice la preparación.</p>
@elseif($application->status==='ACTIVE')<h1>Tu plataforma está activa</h1>
@else<h1>Estamos verificando tu pago</h1><p class="muted">El resultado del navegador no confirma el pago. Actualizaremos esta solicitud después de verificarla directamente con el proveedor.</p>@endif
<a class="btn btn-secondary" href="{{ route('zigo-platform.onboarding.summary',$application->public_token) }}">Ver resumen</a></div></div>
@endsection
