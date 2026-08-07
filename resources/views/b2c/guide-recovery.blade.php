@extends('layouts.b2c-public')

@section('title', 'ZIGO | Estado de tu guía')

@push('styles')
<style>
    .eyebrow{margin:0 0 8px;color:#15803d;font-weight:900;text-transform:uppercase;letter-spacing:.08em;font-size:13px}.recovery-title{font-size:clamp(28px,5vw,42px);margin:0 0 12px}.status{margin:0 0 28px;color:#334155;font-size:18px;line-height:1.55}.details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0}.detail{padding:17px;background:#f8fafc;border-radius:14px}.detail dt{color:#64748b;font-size:13px;font-weight:800;margin-bottom:7px}.detail dd{margin:0;font-weight:800;overflow-wrap:anywhere}.download{display:inline-block;margin-top:28px;background:#3867f5;color:#fff;text-decoration:none;font-weight:900;padding:14px 22px;border-radius:12px}.confirmed{color:#15803d}@media(max-width:620px){.details{grid-template-columns:1fr}.status{font-size:16px}}
</style>
@endpush

@section('content')
<article class="public-card">
    <p class="eyebrow">Pago confirmado</p>
    <h1 class="recovery-title">Tu envío ZIGO</h1>
    <p class="status">{{ $publicStatus }}</p>
    <dl class="details">
        <div class="detail"><dt>Pago</dt><dd class="confirmed">Confirmado</dd></div>
        <div class="detail"><dt>Mensajería</dt><dd>{{ $cotizacion->logistico ?: ($cotizacion->carrier ?: 'Por confirmar') }}</dd></div>
        <div class="detail"><dt>Servicio</dt><dd>{{ $cotizacion->servicio ?: ($cotizacion->service_code ?: 'Por confirmar') }}</dd></div>
        <div class="detail"><dt>Origen / destino</dt><dd>{{ trim(($cotizacion->ciudad_origen ?: '').', '.($cotizacion->estado_origen ?: ''), ', ') ?: 'Origen' }} → {{ trim(($cotizacion->ciudad_destino ?: '').', '.($cotizacion->estado_destino ?: ''), ', ') ?: 'Destino' }}</dd></div>
        @if($cotizacion->guia_id)<div class="detail"><dt>WayBill</dt><dd>{{ $cotizacion->guia_id }}</dd></div>@endif
        @if($cotizacion->tracking_number)<div class="detail"><dt>Tracking</dt><dd>{{ $cotizacion->tracking_number }}</dd></div>@endif
        <div class="detail"><dt>Estado</dt><dd>{{ $publicStatus }}</dd></div>
    </dl>
    @if($documentAvailable)<a class="download" href="{{ route('guide-recovery.download', $recoveryToken) }}">Descargar guía</a>@endif
</article>
@endsection
