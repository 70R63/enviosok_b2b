@extends('tenant.driver.layout')
@section('title','Entrega')
@section('content')
@php($shipment=$assignment->shipment)
@php($pickup=in_array($shipment->status,['READY_FOR_PICKUP'],true))
@php($navigationAddress=$pickup?($shipment->sender_snapshot['address']??null):($shipment->recipient_snapshot['address']??null))
<h1>{{ $shipment->tracking_number }}</h1>
<section class="card"><p class="status">{{ str_replace('_',' ',$shipment->status) }}</p><p><strong>Destinatario</strong><br>{{ $shipment->recipient_snapshot['name']??'—' }}<br>{{ $shipment->recipient_snapshot['address']??'—' }}<br>{{ $shipment->recipient_snapshot['phone']??'' }}</p><p><strong>Remitente</strong><br>{{ $shipment->sender_snapshot['name']??'—' }}<br>{{ $shipment->sender_snapshot['address']??'—' }}</p><p><strong>Paquete</strong><br>{{ $shipment->package_snapshot['type']??'Paquete' }} · {{ $shipment->package_snapshot['weight']??'—' }} kg</p>@if(!empty($shipment->guide_snapshot['reference']))<p><strong>Referencia:</strong> {{ $shipment->guide_snapshot['reference'] }}</p>@endif</section>
@if($navigationAddress)
<section class="card"><h2>{{ $pickup ? 'IR POR EL PAQUETE' : 'IR A ENTREGA' }}</h2><a class="btn" rel="noopener noreferrer" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination={{ rawurlencode($navigationAddress) }}">Google Maps</a><a class="btn" rel="noopener noreferrer" target="_blank" href="https://waze.com/ul?q={{ rawurlencode($navigationAddress) }}&navigate=yes">Waze</a><a class="btn" rel="noopener noreferrer" target="_blank" href="https://maps.apple.com/?daddr={{ rawurlencode($navigationAddress) }}">Apple Maps</a></section>
@endif
@php($next=['READY_FOR_PICKUP'=>['PICKED_UP'=>'Paquete recolectado'],'PICKED_UP'=>['IN_TRANSIT'=>'Iniciar traslado'],'IN_TRANSIT'=>['OUT_FOR_DELIVERY'=>'Salir a entrega']][$shipment->status]??[])
@foreach($next as $status=>$label)<form method="POST" action="{{ route('tenant.driver.shipments.transition',$shipment->uuid) }}">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="btn">{{ $label }}</button></form>@endforeach
@if($shipment->status === 'DELIVERY_FAILED')@if($attemptsRemaining>0)<form method="POST" action="{{ route('tenant.driver.shipments.transition',$shipment->uuid) }}">@csrf<input type="hidden" name="status" value="OUT_FOR_DELIVERY"><button class="btn">Iniciar siguiente intento</button></form>@else<p class="card status">Límite de intentos alcanzado. El envío está pendiente de resolución por el Tenant Admin.</p>@endif @endif
@if($shipment->status === 'OUT_FOR_DELIVERY')<a class="btn" href="{{ route('tenant.driver.shipments.proof',$shipment->uuid) }}">Confirmar entrega</a><a class="btn" href="{{ route('tenant.driver.shipments.failure',$shipment->uuid) }}">Reportar entrega fallida</a>@endif
@endsection
