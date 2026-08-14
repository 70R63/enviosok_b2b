@extends('tenant.layout')
@section('title','Estado del pago')
@section('content')
<div style="max-width:760px;margin:2rem auto"><section class="z-panel z-stack" style="text-align:center">
@if($checkout->status==='PAID')
<div class="z-success-mark"><x-zigo.icon name="success" size="32" /></div><div><span class="z-badge z-badge--success" style="margin:auto">Pago confirmado</span><h1 class="z-title" style="margin:.75rem 0 .25rem">Tu envío está listo</h1></div>
@if($shipment)
<div><strong style="font-size:1.15rem">{{ $shipment->tracking_number }}</strong> <button class="z-copy" type="button" data-copy="{{ $shipment->tracking_number }}">Copiar</button></div>
<div class="z-route" style="text-align:left"><div class="z-route__point"><div class="z-eyebrow">ORIGEN</div><div class="z-route__city">{{ \App\Support\Presentation\AddressPresenter::compact(data_get($shipment->sender_snapshot,'address')) }}</div></div><div class="z-route__line"></div><div class="z-route__point"><div class="z-eyebrow">DESTINO</div><div class="z-route__city">{{ \App\Support\Presentation\AddressPresenter::compact(data_get($shipment->recipient_snapshot,'address')) }}</div></div></div>
<div class="z-cluster" style="justify-content:center"><strong>{{ $shipment->service_code }}</strong><span aria-hidden="true">·</span><strong class="z-metric-value">${{ number_format((float)$checkout->total_amount,2) }} {{ $checkout->currency }}</strong><span class="z-badge z-badge--success">Envío creado</span></div>
<div class="z-actions" style="justify-content:center"><a class="z-btn" href="/rastreo/{{ $shipment->tracking_number }}"><x-zigo.icon name="tracking" />Rastrear envío</a><a class="z-btn z-btn--outline" href="/app/envios/{{ $shipment->uuid }}/guia.pdf"><x-zigo.icon name="download" />Descargar guía</a><a class="z-btn z-btn--ghost" href="/app/envios">Ver mis envíos</a></div>
<details class="z-panel" style="text-align:left"><summary><strong>Detalles del envío</strong></summary><p><span class="z-muted">Origen:</span> {{ \App\Support\Presentation\AddressPresenter::full(data_get($shipment->sender_snapshot,'address')) }}</p><p><span class="z-muted">Destino:</span> {{ \App\Support\Presentation\AddressPresenter::full(data_get($shipment->recipient_snapshot,'address')) }}</p></details>
@else<p>Tu pago está confirmado y estamos preparando los datos de tu envío.</p><a class="z-btn" href="/app/envios">Ver mis envíos</a>@endif
@elseif($result==='failure')
<span class="z-badge z-badge--danger" style="margin:auto">Pago no completado</span><h1>No pudimos procesar tu pago</h1><p>Tu cotización sigue disponible para un nuevo intento.</p><a class="z-btn" href="/app/checkout/{{ $checkout->uuid }}/pago">Intentar nuevamente</a>
@else
<span class="z-badge z-badge--warning" style="margin:auto">Confirmando</span><h1>Estamos confirmando tu pago</h1><p>Tu pago se confirmará de forma segura. Puedes consultar el estado en unos momentos.</p><a class="z-btn z-btn--outline" href="/app/checkout/{{ $checkout->uuid }}/pago">Consultar estado</a>
@endif
</section></div>
@endsection
@push('scripts')<script>document.querySelector('[data-copy]')?.addEventListener('click',async e=>{await navigator.clipboard.writeText(e.currentTarget.dataset.copy);e.currentTarget.textContent='Copiado'})</script>@endpush
