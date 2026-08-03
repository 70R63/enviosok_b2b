@extends('devops.layout')
@section('title', 'Despliegues - ZIGO DevOps')
@section('content')
<div class="title">{{ $historyView ? 'Historial' : 'Despliegues' }}</div><div class="subtitle">Historial de paquetes y resultados.</div>
@if($canDeploy)<p><a class="btn" href="{{ url('/deployments/create') }}">Nuevo paquete</a></p>@endif
<div class="card table-wrap"><table><thead><tr><th>Paquete</th><th>Ambiente</th><th>Estado</th><th>Commit</th><th>SHA-256</th><th>Fecha</th><th>Usuario</th><th></th></tr></thead><tbody>
@forelse($deployments as $deployment)
<tr><td>{{ $deployment->package_name }}</td><td>{{ $deployment->environment === 'production' ? 'PRD' : 'Stage' }}</td><td><span class="badge badge-gray">{{ $deployment->status }}</span></td><td>{{ $deployment->commit_hash ?: '—' }}</td><td><code>{{ substr($deployment->package_sha256, 0, 12) }}…</code></td><td>{{ $deployment->created_at->format('d/m/Y H:i') }}</td><td>{{ $deployment->requester?->name ?: '—' }}</td><td><a class="btn btn-small" href="{{ url('/deployments/' . $deployment->id) }}">Ver</a></td></tr>
@empty<tr><td colspan="8" class="empty-state">No hay despliegues registrados.</td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $deployments->links() }}</div>
@endsection
