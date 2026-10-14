@extends('network.layout')
@section('title','Obligaciones provider - Network')
@section('content')
<h1 class="title">Obligaciones y liquidaciones provider</h1>
<p>Obligación contractual neta. Impuestos y bruto provider pendientes; no se realizan transferencias.</p>
<form method="GET" class="card grid"><div><label for="provider-filter">Provider</label><select id="provider-filter" name="provider_uuid"><option value="">Todos</option>@foreach($providers as $provider)<option value="{{ $provider->uuid }}" @selected(request('provider_uuid')===$provider->uuid)>{{ $provider->name }}</option>@endforeach</select></div>
<div><label for="state-filter">Estado financiero</label><select id="state-filter" name="status"><option value="">Todos</option>@foreach(['PENDING','IN_SERVICE','PAYABLE','REVIEW','SETTLED'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div><div><button class="btn">Filtrar</button></div></form>
<div class="card table-wrap"><table><thead><tr><th>Consumer / Provider</th><th>Referencia / Servicio</th><th>Fecha / Logística / Entrega</th><th>Costo neto provider</th><th>Liquidado</th><th>Pendiente</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($payables as $payable)<tr><td>{{ $payable->consumer->name }} / {{ $payable->provider->name }}</td><td>{{ $payable->fulfillment->shipment->tracking_number }}<br>{{ $payable->snapshot->service_snapshot['name'] }}</td>
<td>{{ $payable->created_at->format('d/m/Y H:i') }}<br>{{ $payable->fulfillment->shipment->status }}<br>Entregado: {{ $payable->events()->where('action','DELIVERED')->first()?->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $payable->provider_net_amount }} {{ $payable->currency }}</td><td>{{ $payable->settledAmount() }}</td><td>{{ $payable->remainingAmount() }}</td><td>{{ $payable->financialStatus() }}</td><td><a href="{{ route('network.provider-settlements.show',$payable->uuid) }}">Detalle</a></td></tr>
@empty<tr><td colspan="8">No hay obligaciones registradas. Verificar reconciliación si hay fulfillments históricos.</td></tr>@endforelse
</tbody></table>{{ $payables->links() }}</div>
@endsection
