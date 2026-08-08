@extends('network.layout')
@section('title','Dashboard - ZIGO Network')
@section('content')
<div class="title">ZIGO NETWORK</div><div class="subtitle">Consola interna de administración SaaS.</div>
<div class="grid">
<div class="card metric"><span>Tenants activos</span><strong>{{ $activeTenants }}</strong></div>
<div class="card metric"><span>Planes activos</span><strong>{{ $activePlans }}</strong></div>
<div class="card metric"><span>Módulos activos</span><strong>{{ $activeModules }}</strong></div>
<div class="card metric"><span>Operaciones del mes</span><strong>{{ $monthlyOperations ?? 'N/A' }}</strong><small class="muted">Pendiente dominio Usage</small></div>
</div>
<div class="card"><h2>Tenants</h2><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Slug</th><th>Estado</th><th>Plan</th><th>Uso</th><th>Acciones</th></tr></thead><tbody>
@forelse($tenants as $tenant)<tr><td>{{ $tenant->name }}</td><td>{{ $tenant->slug }}</td><td><span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></td><td>Sin plan</td><td>N/A</td><td><a class="btn" href="{{ route('network.tenants.show',$tenant) }}">Ver</a></td></tr>@empty<tr><td colspan="6">No hay tenants registrados.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
