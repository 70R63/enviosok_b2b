@extends('network.layout')
@section('title','API Hub')
@section('content')
<div class="hero"><div><div class="eyebrow">COMMERCIAL API</div><h1 class="title">ZIGO API Hub</h1><p class="subtitle">Observabilidad tenant-aware sin revelar claves ni secretos.</p></div></div>
<div class="grid"><div class="card metric"><span>Requests del mes</span><strong>{{ number_format($usage) }}</strong></div><div class="card metric"><span>Errores</span><strong>{{ number_format($apiErrors) }}</strong></div><div class="card metric"><span>Webhooks fallidos</span><strong>{{ number_format($failedWebhooks) }}</strong></div></div>
<section class="card table-wrap"><table><thead><tr><th>Tenant</th><th>Client</th><th>Entorno</th><th>Estado</th><th>Keys activas</th><th>Último uso</th></tr></thead><tbody>@forelse($clients as $client)<tr><td>{{ $client->tenant->name }}</td><td>{{ $client->name }}</td><td>{{ $client->environment }}</td><td>{{ $client->status }}</td><td>{{ $client->keys_count }}</td><td>{{ $client->last_used_at?->format('d/m/Y H:i')??'—' }}</td></tr>@empty<tr><td colspan="6">No hay clientes API registrados.</td></tr>@endforelse</tbody></table>{{ $clients->links() }}</section>
@endsection
