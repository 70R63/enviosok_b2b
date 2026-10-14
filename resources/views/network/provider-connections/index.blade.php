@extends('network.layout')
@section('title', 'Relaciones provider - Network')
@section('content')
<h1 class="title">Relaciones consumer / provider</h1>
<p>Los servicios, cobertura y tarifas son administrados por el proveedor.</p>
<p><a class="btn" href="{{ route('network.provider-connections.create') }}">Crear relación</a></p>
<div class="card table-wrap"><table>
<thead><tr><th>Consumer</th><th>Provider</th><th>Capability</th><th>Estado</th><th>Vigencia</th><th></th></tr></thead>
<tbody>@forelse($connections as $connection)
<tr><td>{{ $connection->consumer->name }}</td><td>{{ $connection->provider->name }}</td><td>{{ $connection->capability }}</td>
<td>{{ $connection->status === 'active' ? 'Activa' : 'Inactiva' }}</td><td>{{ $connection->valid_from?->format('d/m/Y H:i') ?? 'Sin inicio' }} / {{ $connection->valid_to?->format('d/m/Y H:i') ?? 'Sin fin' }}</td>
<td><a class="btn" href="{{ route('network.provider-connections.edit', $connection) }}">Configurar / readiness</a></td></tr>
@empty<tr><td colspan="6">No hay relaciones configuradas.</td></tr>@endforelse</tbody>
</table>{{ $connections->links() }}</div>
@endsection
