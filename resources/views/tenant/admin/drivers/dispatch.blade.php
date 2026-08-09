@extends('tenant.admin.layout')
@section('title','Recolecciones pendientes')
@section('content')
<div class="eyebrow">DISPATCH</div><h1>Recolecciones pendientes</h1>
<section class="card table-wrap"><table><thead><tr><th>Tracking</th><th>Servicio</th><th>Origen</th><th>Destino</th><th>Solicitada</th><th>Estado</th><th>Asignación</th></tr></thead><tbody>
@forelse($shipments as $shipment)
<tr><td><a href="{{ route('tenant.admin.operations.show',$shipment->operation->uuid) }}"><code>{{ $shipment->tracking_number }}</code></a></td><td>{{ $shipment->service_code }}</td><td>CP {{ $shipment->sender_snapshot['postal_code'] ?? '—' }}</td><td>CP {{ $shipment->recipient_snapshot['postal_code'] ?? '—' }}</td><td>{{ optional($shipment->events->first()?->occurred_at)->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $shipment->status }}</td><td>
@if($shipment->activeDriverAssignment)
Asignado @if($canManage) · {{ $shipment->activeDriverAssignment->driverProfile->user->name }} @endif
@else
Sin asignar
@endif
</td></tr>
@empty<tr><td colspan="7">No hay recolecciones pendientes.</td></tr>@endforelse
</tbody></table></section>{{ $shipments->links() }}
@endsection
