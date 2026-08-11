@extends('layouts.zigo-platform')
@section('title', 'Resumen | ZIGO Platform')
@section('content')
@php($snapshot=$application->commercial_snapshot_json)
<div class="wizard"><div class="steps"><span class="step active"></span><span class="step active"></span><span class="step active"></span><span class="step active"></span></div>
<div class="card"><div class="eyebrow">Paso 4 de 4</div><h1>Resumen comercial</h1>
<div class="summary"><div><span class="muted">Empresa</span><br><strong>{{ $application->company_name }}</strong></div><div><span class="muted">Contacto</span><br><strong>{{ $application->contact_name }} {{ $application->contact_last_name }}</strong><br>{{ $application->contact_email }}</div><div><span class="muted">Plan</span><br><strong>{{ $snapshot['plan']['name'] }}</strong></div><div><span class="muted">Periodicidad</span><br>{{ $application->billing_period==='annual'?'Anual':'Mensual' }}</div><div><span class="muted">Subdominio</span><br><strong>{{ $application->requested_subdomain }}.{{ config('zigo_onboarding.subdomain_base') }}</strong></div><div><span class="muted">Operaciones adicionales</span><br>{{ $snapshot['requested_operations'] ? number_format($snapshot['requested_operations']) : 'Sin paquete adicional' }}</div></div>
@if(!empty($snapshot['modules']))<h2>Módulos</h2><ul>@foreach($snapshot['modules'] as $module)<li>{{ $module['name'] }}{{ $module['included']?' (incluido)':'' }}</li>@endforeach</ul>@endif
<div class="summary"><div>Subtotal</div><div>${{ $snapshot['subtotal'] }} {{ $snapshot['currency'] }}</div><div>Impuestos</div><div>${{ $snapshot['tax_amount'] }} {{ $snapshot['currency'] }}</div><div class="total">Total</div><div class="total">${{ $snapshot['total'] }} {{ $snapshot['currency'] }}</div></div>
<p class="muted">Tu acceso administrativo se activará después de confirmar el pago.</p>
<form method="post" action="{{ route('zigo-platform.onboarding.checkout',$application->public_token) }}">@csrf<button class="btn btn-primary" type="submit">Pagar con Mercado Pago</button></form></div></div>
@endsection
