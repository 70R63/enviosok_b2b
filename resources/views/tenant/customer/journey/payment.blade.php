@extends('tenant.layout')
@section('title','Pago')
@section('content')
@php($paymentTaxRate=(float)($checkout->tax_rate??0))
@include('tenant.customer.journey.steps',['currentStep'=>5])
<div style="max-width:680px;margin:2rem auto"><section class="z-card z-stack" style="text-align:center">
@if($checkout->status==='EXPIRED')<span class="z-badge z-badge--danger" style="margin:auto">Checkout expirado</span><h1>Esta cotización expiró</h1><p class="z-muted">No se consumió Usage, no se creó envío y no se generó guía.</p><a class="z-btn" href="/app/cotizar">Volver a cotizar</a>
@elseif($checkout->status==='PAID')<span class="z-badge z-badge--success" style="margin:auto">Pago confirmado</span><h1>Tu envío está confirmado</h1><a class="z-btn" href="/app/envios">Ver mi envío</a>
@else
<div class="z-eyebrow">{{ $tenant->branding?->brand_name ?? $tenant->name }}</div><h1>Completa tu pago</h1><p>Servicio seleccionado</p><div class="journey-summary"><div><span>Subtotal</span><strong>${{ number_format((float)($checkout->subtotal_amount ?? $checkout->total_amount),2) }}</strong></div><div><span>IVA{{ $paymentTaxRate>0 ? ' ('.number_format($paymentTaxRate*100,0).'%)' : '' }}</span><strong>${{ number_format((float)($checkout->tax_amount??0),2) }}</strong></div><div class="total"><strong>Total</strong><strong>${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</strong></div></div>
@if($attempt?->status==='REJECTED')<div class="z-alert z-alert--danger">No pudimos procesar tu pago. Intenta nuevamente.</div>@elseif($attempt?->status==='PENDING')<div class="z-alert z-alert--warning">Pago pendiente de confirmación.</div>@endif
@if($connection?->isConnected())<form method="POST" action="{{ route('tenant.customer.app.checkout.mercado-pago.create',['checkout'=>$checkout->uuid]) }}">@csrf<button class="z-btn z-btn--block">Pagar ${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }} con Mercado Pago</button></form><p class="z-help">Serás dirigido a Mercado Pago para completar tu pago.</p>
@else<div class="z-alert" role="status">El comercio aún no tiene habilitados los pagos en línea. Contacta a soporte.</div>@endif
<a href="/app">Volver a Mi cuenta</a>@endif
</section></div>
@endsection
