@extends('tenant.driver.layout')
@section('title','Mis entregas')
@section('content')
<h1>MIS ENTREGAS</h1>
<section class="card"><p><strong>Estado:</strong> {{ $profile->isOnline() ? 'ONLINE' : 'OFFLINE' }}</p><p><strong>Disponibilidad:</strong> {{ $profile->availability_status }}</p>
<form method="POST" action="{{ route('tenant.driver.availability') }}">@csrf<input type="hidden" name="availability_status" value="AVAILABLE"><button class="btn">Disponible</button></form>
<form method="POST" action="{{ route('tenant.driver.availability') }}">@csrf<input type="hidden" name="availability_status" value="UNAVAILABLE"><button class="btn">No disponible</button></form></section>
<section class="card"><h2>Mi actividad</h2><p>Entregadas: <strong>{{ $delivered }}</strong></p><p>Ganancias generadas: <strong>{{ number_format((float)$earnings,2) }} {{ $policy?->currency ?? 'MXN' }}</strong></p><p>Pendientes de liquidación: <strong>{{ number_format((float)$earnings,2) }} {{ $policy?->currency ?? 'MXN' }}</strong></p><p class="muted">Esquema: {{ $policy?->compensation_type ?? 'Sin configurar' }}
@if($policy)
 · {{ $policy->settlement_frequency }}
 @if($policy->amount_per_delivery !== null)
 · {{ number_format((float)$policy->amount_per_delivery,2) }} {{ $policy->currency }} por entrega
 @endif
@endif
</p></section>
@forelse($assignments as $assignment)@php($shipment=$assignment->shipment)<a class="card" href="{{ route('tenant.driver.shipments.show',$shipment->uuid) }}"><strong>{{ $shipment->tracking_number }}</strong><p class="status">{{ str_replace('_',' ',$shipment->status) }}</p><p class="muted">Origen: CP {{ $shipment->sender_snapshot['postal_code']??'—' }} · Destino: CP {{ $shipment->recipient_snapshot['postal_code']??'—' }}</p><p>{{ $shipment->service_code }}</p></a>@empty<div class="card">No tienes entregas activas asignadas.</div>@endforelse
<section class="card"><h2>Entregas completadas</h2>
@forelse($completedDeliveries as $completed)
@php($completedEarning = $completedEarnings->get($completed->local_shipment_id))
<p><code>{{ $completed->shipment->tracking_number }}</code> · {{ $completed->shipment->status }} · {{ $completed->delivered_at }}@if($completedEarning) · {{ number_format((float)$completedEarning->amount, 2) }} {{ $completedEarning->currency }}@endif</p>
@empty
<p>Sin entregas completadas.</p>
@endforelse
</section>
@endsection
