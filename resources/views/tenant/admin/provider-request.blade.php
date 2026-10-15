@extends('tenant.admin.layout')
@section('title','Solicitud logística')
@section('content')
<h1>Solicitud {{ $shipment->tracking_number }}</h1>
<p>Recibida: {{ $fulfillment->created_at->format('d/m/Y H:i') }}</p>
<p>{{ data_get($shipment->guide_snapshot,'branding.brand_name') }} · {{ data_get($shipment->guide_snapshot,'service_name') }}</p>
<p>Decisión: {{ ['PENDING_PROVIDER'=>'Pendiente','ACCEPTED'=>'Aceptada','REJECTED'=>'Rechazada'][$fulfillment->acceptanceStatus()] }}</p>
@php
    $pickupPreference = app(\App\Domain\Network\Providers\ProviderPickupPreference::class);
    $pickupRequested = data_get($fulfillment->operation->metadata, 'pickup_request');
    $pickupConfirmation = data_get($fulfillment->operation->metadata, 'pickup_confirmation');
@endphp
<section class="z-card"><h2>Preferencia cliente</h2><p>{{ $pickupPreference->label($pickupRequested) }}</p></section>
@if($fulfillment->acceptanceStatus() === 'ACCEPTED')
    <section class="z-card"><h2>{{ $pickupConfirmation ? 'Recolección confirmada' : 'Solicitud aceptada · recolección por confirmar' }}</h2>
    @if($pickupConfirmation)
        <p>{{ $pickupPreference->label($pickupConfirmation) }}</p>
    @elseif($shipment->status === 'READY_FOR_PICKUP')
        <form method="POST" action="{{ route('tenant.admin.provider-inbox.pickup-confirmation', $fulfillment->uuid) }}">
            @csrf
            <label>Atención<select name="pickup_confirmation[mode]" required><option value="">Selecciona una opción</option><option value="ASAP">Lo antes posible</option><option value="WINDOW">Programar ventana</option></select></label>
            <p>Para programar ventana, completa fecha e inicio/fin ({{ config('app.timezone') }}).</p>
            <label>Fecha confirmada<input type="date" name="pickup_confirmation[confirmed_date]" min="{{ now(config('app.timezone'))->toDateString() }}"></label>
            <label>Hora inicio<input type="time" name="pickup_confirmation[window_start]"></label>
            <label>Hora fin<input type="time" name="pickup_confirmation[window_end]"></label>
            <button class="z-btn">Confirmar recolección</button>
        </form>
    @endif
    </section>
@endif
<p>Estado logístico: {{ str_replace('_',' ',$shipment->status) }}</p>
<section class="z-card"><h2>Avance operativo</h2><ol>@foreach($shipment->events as $event)<li>{{ $event->event_code === 'PICKUP_CONFIRMED' ? 'Recolección confirmada' : (['CREATED'=>'Envío creado','READY_FOR_PICKUP'=>'Recolección solicitada','PICKED_UP'=>'Paquete recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERED'=>'Entregado','DELIVERY_FAILED'=>'Entrega no realizada'][$event->status] ?? 'Actualización') }} · {{ $event->occurred_at->format('d/m/Y H:i') }}</li>@endforeach</ol></section>
@foreach(['Recolección'=>$shipment->sender_snapshot,'Entrega'=>$shipment->recipient_snapshot] as $title=>$party)
<section class="z-card"><h2>{{ $title }}</h2>@foreach(\App\Support\Presentation\AddressPresenter::lines($party) as $line)<p>{{ $line }}</p>@endforeach<p>Teléfono: {{ $party['phone'] }}</p>@if(data_get($party,'address.references'))<p>Referencias: {{ data_get($party,'address.references') }}</p>@endif</section>
@endforeach
<section class="z-card"><h2>Paquete</h2><p>{{ data_get($shipment->package_snapshot,'type') }} · {{ data_get($shipment->package_snapshot,'weight') }} kg</p><p>{{ data_get($shipment->package_snapshot,'length') }} × {{ data_get($shipment->package_snapshot,'width') }} × {{ data_get($shipment->package_snapshot,'height') }} cm</p>@if(data_get($shipment->package_snapshot,'content'))<p>{{ data_get($shipment->package_snapshot,'content') }}</p>@endif</section>
@if($fulfillment->decision)<p>Decisión registrada: {{ $fulfillment->decision->decided_at->format('d/m/Y H:i') }}</p>@if($fulfillment->decision->rejection_reason)<p>Motivo interno: {{ $fulfillment->decision->rejection_reason }}</p>@endif
@else
<form method="POST" action="{{ route('tenant.admin.provider-inbox.accept',$fulfillment->uuid) }}">@csrf<button class="z-btn">Aceptar solicitud</button></form>
<form method="POST" action="{{ route('tenant.admin.provider-inbox.reject',$fulfillment->uuid) }}">@csrf<label>Motivo interno opcional<input name="reason" maxlength="500"></label><button class="z-btn z-btn--outline">Rechazar solicitud</button></form>
@endif
@if($fulfillment->acceptanceStatus()==='ACCEPTED')<p>Solicitud disponible para el despacho del proveedor.</p>@if(app(\App\Domain\Network\Billing\EntitlementService::class)->has($tenant,'DRIVER'))<a class="z-btn" href="{{ route('tenant.admin.dispatch.pickups') }}">Abrir despacho</a>@endif @endif
@if($canDispatch && $drivers->isNotEmpty())
<form method="POST" action="{{ route('tenant.admin.drivers.assign',$shipment->uuid) }}">@csrf<label>Conductor<select name="driver_uuid" required><option value="">Seleccionar conductor</option>@foreach($drivers as $driver)<option value="{{ $driver->uuid }}">{{ $driver->code }} · {{ $driver->user->name }}</option>@endforeach</select></label><button class="z-btn">Asignar conductor</button></form>
@endif
<a href="{{ route('tenant.admin.provider-inbox.index') }}">Volver a solicitudes</a>
@endsection
