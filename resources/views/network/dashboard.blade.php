@extends('network.layout')
@section('title','Dashboard - ZIGO Network')
@section('content')
<div class="eyebrow">OPERACIÓN NETWORK</div><div class="title">Dashboard</div><div class="subtitle">Estado verificable del ecosistema. Sin métricas operativas inventadas.</div>
<div class="grid">
<div class="card metric"><span>Tenants activos</span><strong>{{ $activeTenants }}</strong></div>
<div class="card metric"><span>Planes activos</span><strong>{{ $activePlans }}</strong></div>
<div class="card metric"><span>Módulos activos</span><strong>{{ $activeModules }}</strong></div>
<div class="card metric"><span>Operaciones del mes</span><strong>{{ $monthlyOperations ?? 'N/A' }}</strong><small class="muted">Pendiente dominio Usage</small></div>
</div>
<h2 class="section-title">SUSCRIPCIONES</h2><div class="grid">@foreach(['active'=>'Activas','trial'=>'Trial','past_due'=>'Past Due','grace'=>'Grace','suspended'=>'Suspended'] as $status=>$label)<div class="card metric"><span>{{ $label }}</span><strong>{{ $subscriptionCounts[$status]??0 }}</strong></div>@endforeach</div>
<h2 class="section-title">ESTADO DEL ECOSISTEMA</h2><div class="grid">@foreach($statusCounts as $status=>$count)<div class="card metric"><x-network.status-badge :status="$status" :statuses="$statuses" /><strong>{{ $count }}</strong><small class="muted">nodos del registry</small><div class="progress"><i style="width:{{ count(config('zigo_network_map.nodes')) ? round(($count/count(config('zigo_network_map.nodes')))*100) : 0 }}%"></i></div></div>@endforeach</div>
<div class="card"><h2>Tenants</h2><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Slug</th><th>Estado</th><th>Plan</th><th>Módulos</th><th>Uso</th><th>Acciones</th></tr></thead><tbody>
@forelse($tenants as $tenant)<tr><td>{{ $tenant->name }}</td><td>{{ $tenant->slug }}</td><td><span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></td><td>{{ $tenant->currentPlan?->name??'Sin plan' }}</td><td>{{ $tenant->currentPlan?->modules->count()??0 }}</td><td>N/A</td><td><a class="btn" href="{{ route('network.tenants.show',$tenant) }}">Ver</a> <a class="btn" href="{{ route('network.tenants.edit',$tenant) }}">Editar</a></td></tr>@empty<tr><td colspan="7">No hay tenants registrados.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
