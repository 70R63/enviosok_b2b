@extends('tenant.layout')
@section('title','Estado del pago')
@section('content')<div style="max-width:680px;margin:3rem auto"><section class="z-card z-stack" style="text-align:center">
@if($checkout->status==='PAID')<span class="z-badge z-badge--success" style="margin:auto">Pago confirmado</span><h1>Tu envío está listo</h1><p>Mercado Pago confirmó la operación de forma segura.</p><a class="z-btn" href="/app/envios">Ver mi envío</a>
@elseif($result==='failure')<span class="z-badge z-badge--danger" style="margin:auto">Pago no completado</span><h1>No pudimos procesar tu pago</h1><p>Tu cotización sigue disponible para un nuevo intento.</p><a class="z-btn" href="/app/checkout/{{ $checkout->uuid }}/pago">Intentar nuevamente</a>
@else<span class="z-badge z-badge--warning" style="margin:auto">Confirmando</span><h1>Estamos confirmando tu pago</h1><p>El regreso del navegador no aprueba el pago. Esperamos la confirmación segura de Mercado Pago.</p><a class="z-btn z-btn--outline" href="/app/checkout/{{ $checkout->uuid }}/pago">Consultar estado</a>@endif
</section></div>@endsection
