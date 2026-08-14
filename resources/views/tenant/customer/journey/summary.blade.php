@extends('tenant.layout')
@section('title','Resumen del envío')
@section('content')
@php($proofName=data_get($checkout->proof_option_snapshot,'name'))
@php($summaryOrigin=data_get($checkout->quote_snapshot,'route.origin'))
@php($summaryDestination=data_get($checkout->quote_snapshot,'route.destination'))
@include('tenant.customer.journey.steps',['currentStep'=>4])
<header class="z-page-header"><div><div class="z-eyebrow">REVISA Y PAGA</div><h1>Todo listo para pagar</h1><p class="z-muted">Confirma servicio, ruta, paquete e importe.</p></div></header>
<div class="z-detail-grid">
<section class="z-card"><h2>Resumen del envío</h2><div class="z-stack">
<div><div class="z-eyebrow">SERVICIO</div><strong>{{ data_get($checkout->quote_snapshot,'service','Servicio de envío') }}</strong><div class="z-muted">{{ data_get($checkout->quote_snapshot,'provider','ZIGO Local') }}</div></div>
<div><div class="z-eyebrow">ORIGEN</div><strong>{{ is_array($summaryOrigin) ? ($summaryOrigin['address'] ?? \App\Support\Presentation\AddressPresenter::full($summaryOrigin)) : ($summaryOrigin ?: '—') }}</strong></div>
<div><div class="z-eyebrow">DESTINO</div><strong>{{ is_array($summaryDestination) ? ($summaryDestination['address'] ?? \App\Support\Presentation\AddressPresenter::full($summaryDestination)) : ($summaryDestination ?: '—') }}</strong></div>
<div><div class="z-eyebrow">PAQUETE</div><strong>{{ ucfirst(data_get($checkout->quote_snapshot,'package.type','Paquete')) }} · {{ data_get($checkout->quote_snapshot,'package.weight','—') }} kg facturables</strong></div>
@if(data_get($checkout->shipping_data_snapshot,'pickup_requested'))<div class="z-alert z-alert--success">Recolección solicitada para después del pago aprobado.</div>@endif
@if(filled($proofName))<div><div class="z-eyebrow">ENTREGA</div><strong>{{ $proofName }}</strong></div>@endif
</div></section>
<section class="z-card"><h2>Resumen comercial</h2><div class="journey-summary">
<div><span>Subtotal</span><strong>${{ number_format((float)data_get($checkout->quote_snapshot,'subtotal',$checkout->shipping_amount),2) }}</strong></div>
<div><span>IVA</span><strong>${{ number_format((float)data_get($checkout->quote_snapshot,'tax',0),2) }}</strong></div>
@if((float)$checkout->evidence_amount>0)<div><span>Evidencia</span><strong>${{ number_format((float)$checkout->evidence_amount,2) }}</strong></div>@endif
<div class="total"><strong>TOTAL</strong><strong>${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</strong></div></div>
<p class="z-help">Disponible hasta {{ $checkout->expires_at?->format('d/m/Y H:i') }}.</p><form method="POST" action="{{ route('tenant.customer.app.checkout.continue',$checkout->uuid,false) }}">@csrf<button class="z-btn z-btn--block">Continuar al pago</button></form></section>
</div>
@endsection
