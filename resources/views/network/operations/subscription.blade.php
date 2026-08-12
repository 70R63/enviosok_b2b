@extends('network.layout')
@section('title','Suscripción '.$subscription->uuid)
@section('content')
@php($days=now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(),false))
<div class="eyebrow">CICLO DE SUSCRIPCIÓN</div><div class="title">{{ $subscription->tenant->name }}</div>
<div class="grid"><div class="card metric"><span>Plan</span><strong>{{ $subscription->plan->name }}</strong></div><div class="card metric"><span>Estado</span><strong>{{ strtoupper($subscription->status) }}</strong></div><div class="card metric"><span>Vence</span><strong>{{ $subscription->current_period_end->format('d/m/Y') }} · {{ $days }} días</strong></div><div class="card metric"><span>Consumo</span><strong>{{ $usage['used'] }} / {{ $usage['limit'] ?? '∞' }}</strong></div></div>
<div class="card"><h2>Acciones seguras</h2>
 <form style="display:inline" method="POST" action="{{ route('network.subscriptions.reminder',$subscription) }}">@csrf<button class="btn">Enviar recordatorio</button></form>
 <form style="display:inline" method="POST" action="{{ route('network.subscriptions.renewal',$subscription) }}">@csrf<button class="btn">Generar renovación</button></form>
 @if($days<0 && $subscription->status!=='suspended')<form style="display:inline" method="POST" action="{{ route('network.subscriptions.suspend-expired',$subscription) }}">@csrf<button class="btn">Suspender por vencimiento</button></form>@endif
 <p class="muted">Reactivar exige un pago APPROVED verificado; no existe aprobación manual en Network.</p>
</div>
<div class="card"><h2>Historial de renovaciones</h2><div class="table-wrap"><table><thead><tr><th>Orden</th><th>Precio informado</th><th>Orden</th><th>Pago</th><th>Fecha</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->uuid }}</td><td>${{ $order->total_amount }} {{ $order->currency }}</td><td>{{ $order->status }}</td><td>{{ $order->payment_status }}</td><td>{{ $order->created_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="5">Sin renovaciones.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><h2>Avisos</h2>@forelse($notices as $notice)<p><span class="badge">{{ $notice->event_type }}</span> {{ $notice->sent_at?->format('d/m/Y H:i') ?? $notice->status }}</p>@empty<p class="muted">Sin avisos enviados.</p>@endforelse</div>
<div class="card"><h2>Entitlements</h2>@forelse($subscription->entitlements as $entitlement)<span class="badge">{{ $entitlement->code }}</span>@empty Sin entitlements @endforelse</div>
@endsection
