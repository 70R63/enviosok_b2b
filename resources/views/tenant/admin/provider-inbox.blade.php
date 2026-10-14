@extends('tenant.admin.layout')
@section('title','Solicitudes recibidas')
@section('content')
<h1>Solicitudes recibidas</h1>
<p>La decisión del operador y el avance logístico se registran por separado.</p>
<form method="GET"><label>Decisión <select name="status"><option value="">Todas</option>@foreach(['PENDING_PROVIDER'=>'Pendiente','ACCEPTED'=>'Aceptada','REJECTED'=>'Rechazada'] as $status=>$label)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $label }}</option>@endforeach</select></label><button class="z-btn">Filtrar</button></form>
@forelse($fulfillments as $item)
<article class="z-card"><h2>{{ $item->shipment->tracking_number }}</h2><p>{{ $item->created_at->format('d/m/Y H:i') }} · {{ data_get($item->shipment->guide_snapshot,'service_name') }}</p>
<p>Decisión: {{ ['PENDING_PROVIDER'=>'Pendiente','ACCEPTED'=>'Aceptada','REJECTED'=>'Rechazada'][$item->acceptanceStatus()] }}</p>
<p>Estado logístico: {{ str_replace('_',' ',$item->shipment->status) }}</p>
<a class="z-btn" href="{{ route('tenant.admin.provider-inbox.show',$item->uuid) }}">Ver solicitud</a></article>
@empty<p>No hay solicitudes.</p>@endforelse
{{ $fulfillments->links() }}
@endsection
