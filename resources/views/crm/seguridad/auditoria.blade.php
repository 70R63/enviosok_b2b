@extends('crm.layout')
@section('content')
<div class="title">Actividad disponible</div><div class="subtitle">Timestamps existentes; no constituye una auditoría completa.</div>
@include('crm.seguridad.partials.nav')
<div class="alert alert-info">La fuente actual solo permite conocer cuándo se creó o actualizó una identidad. No registra quién realizó el cambio ni el detalle modificado.</div>
<div class="card table-wrap"><table><thead><tr><th>Identidad</th><th>Rol actual</th><th>Registro</th><th>Última actualización</th><th>Actividad inferida</th></tr></thead><tbody>
@forelse($activity as $user)<tr><td>{{ $user->name }}<div class="muted">{{ $user->email }}</div></td><td>{{ $user->roles->pluck('name')->implode(', ') ?: 'Sin rol' }}</td><td>{{ $user->created_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</td><td>{{ $user->updated_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</td><td>{{ $user->created_at && $user->updated_at && $user->created_at->ne($user->updated_at) ? 'Actualización disponible' : 'Registro disponible' }}</td></tr>
@empty<tr><td colspan="5" class="empty-state">No hay actividad disponible.</td></tr>@endforelse
</tbody></table><div class="pagination-wrap">{{ $activity->withQueryString()->links() }}</div></div>
@endsection
