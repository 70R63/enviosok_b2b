@extends('tenant.layout')
@section('title','Estado del pago')
@section('content')
<div style="max-width:680px;margin:3rem auto"><section class="z-card z-stack" style="text-align:center">
@if($checkout->status==='PAID')
<span class="z-badge z-badge--success" style="margin:auto">Pago confirmado</span><h1>Tu envío está listo</h1>
@if($shipment)
<div class="z-grid" style="text-align:left"><div><div class="z-eyebrow">GUÍA / TRACKING</div><strong>{{ $shipment->tracking_number }}</strong></div><div><div class="z-eyebrow">SERVICIO</div><strong>{{ $shipment->service_code }}</strong></div><div><div class="z-eyebrow">TOTAL</div><strong>${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</strong></div></div>
<div class="z-card" style="text-align:left"><div class="z-eyebrow">RUTA</div><p><strong>Origen:</strong> {{ \App\Support\Presentation\AddressPresenter::compact(data_get($shipment->sender_snapshot,'address')) }}</p><p><strong>Destino:</strong> {{ \App\Support\Presentation\AddressPresenter::compact(data_get($shipment->recipient_snapshot,'address')) }}</p></div>
<div class="z-grid"><a class="z-btn" href="/app/envios/{{ $shipment->uuid }}/guia.pdf">Descargar guía</a><a class="z-btn z-btn--outline" href="/rastreo/{{ $shipment->tracking_number }}">Rastrear envío</a><a class="z-btn z-btn--outline" href="/app/envios">Ver mis envíos</a></div>
@else<p>Tu pago está confirmado y estamos preparando los datos de tu envío.</p><a class="z-btn" href="/app/envios">Ver mis envíos</a>@endif
@elseif($result==='failure')
<span class="z-badge z-badge--danger" style="margin:auto">Pago no completado</span><h1>No pudimos procesar tu pago</h1><p>Tu cotización sigue disponible para un nuevo intento.</p><a class="z-btn" href="/app/checkout/{{ $checkout->uuid }}/pago">Intentar nuevamente</a>
@else
<span class="z-badge z-badge--warning" style="margin:auto">Confirmando</span><h1>Estamos confirmando tu pago</h1><p>Tu pago se confirmará de forma segura. Puedes consultar el estado en unos momentos.</p><a class="z-btn z-btn--outline" href="/app/checkout/{{ $checkout->uuid }}/pago">Consultar estado</a>
@endif
</section></div>
@endsection
