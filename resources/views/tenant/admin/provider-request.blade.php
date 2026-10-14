@extends('tenant.admin.layout')
@section('title','Solicitud logística')
@section('content')
<h1>Solicitud {{ $shipment->tracking_number }}</h1>
<p>Recibida: {{ $fulfillment->created_at->format('d/m/Y H:i') }}</p>
<p>{{ data_get($shipment->guide_snapshot,'branding.brand_name') }} · {{ data_get($shipment->guide_snapshot,'service_name') }}</p>
<p>Decisión: {{ ['PENDING_PROVIDER'=>'Pendiente','ACCEPTED'=>'Aceptada','REJECTED'=>'Rechazada'][$fulfillment->acceptanceStatus()] }}</p>
<p>Estado logístico: {{ str_replace('_',' ',$shipment->status) }}</p>
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
