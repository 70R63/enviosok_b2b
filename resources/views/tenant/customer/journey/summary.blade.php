@extends('tenant.layout')
@section('title','Resumen del envío')
@section('content')
@php($proofName=data_get($checkout->proof_option_snapshot,'name'))
@include('tenant.customer.journey.steps',['currentStep'=>4])
<header class="z-page-header"><div><div class="z-eyebrow">REVISA Y PAGA</div><h1>Todo listo para pagar</h1><p class="z-muted">Confirma que los datos de tu envío sean correctos.</p></div></header>
<div class="z-grid">
<section class="z-card"><h2>Resumen del envío</h2><div class="z-stack">
<div><div class="z-eyebrow">ORIGEN</div><strong>{{ $checkout->quote_snapshot['route']['origin'] }}</strong></div>
<div><div class="z-eyebrow">DESTINO</div><strong>{{ $checkout->quote_snapshot['route']['destination'] }}</strong></div>
<div><div class="z-eyebrow">SERVICIO</div><strong>{{ $checkout->quote_snapshot['service'] ?? 'Servicio de envío' }}</strong><div class="z-muted">{{ $checkout->quote_snapshot['delivery'] ?? 'Tiempo según servicio contratado' }}</div></div>
<div><div class="z-eyebrow">PAQUETE Y PESO</div><strong>{{ ucfirst($checkout->quote_snapshot['package']['type'] ?? 'Paquete') }} · {{ $checkout->quote_snapshot['package']['weight'] ?? '—' }} kg</strong></div>
@if(filled($proofName))<div><div class="z-eyebrow">ENTREGA</div><strong>{{ $proofName }}</strong></div>@endif
</div></section>
<section class="z-card"><h2>Total</h2><div class="journey-summary"><div><span>Envío</span><strong>${{ number_format((float)$checkout->shipping_amount,2) }}</strong></div>@if((float)$checkout->evidence_amount > 0 || filled($proofName))<div><span>Evidencia</span><strong>${{ number_format((float)$checkout->evidence_amount,2) }}</strong></div>@endif<div class="total"><strong>TOTAL</strong><strong>${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</strong></div></div><p class="z-help">Disponible hasta {{ $checkout->expires_at?->format('d/m/Y H:i') }}.</p><form method="POST" action="/app/checkout/{{ $checkout->uuid }}/continuar">@csrf<button class="z-btn z-btn--block">Pagar ${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }} con Mercado Pago</button></form></section>
</div>
@endsection
