@extends('network.layout')
@section('title','Suscripciones')
@section('content')
<div class="title">Ciclo de suscripciones</div>
<form class="card filters" method="GET">
 <select name="lifecycle"><option value="">Todas las vigencias</option><option value="upcoming" @selected(request('lifecycle')==='upcoming')>Próximas a vencer</option><option value="expired" @selected(request('lifecycle')==='expired')>Vencidas</option></select>
 <select name="status"><option value="">Todos los estados</option>@foreach(\App\Domain\Network\Billing\Models\Subscription::STATUSES as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ strtoupper($status) }}</option>@endforeach</select>
 <select name="billing"><option value="">Mensual y anual</option><option value="MONTHLY" @selected(request('billing')==='MONTHLY')>Mensual</option><option value="ANNUAL" @selected(request('billing')==='ANNUAL')>Anual</option></select>
 <input name="tenant_id" value="{{ request('tenant_id') }}" placeholder="Tenant ID"><input name="plan_id" value="{{ request('plan_id') }}" placeholder="Plan ID"><button class="btn">Filtrar</button>
</form>
<div class="card table-wrap"><table><thead><tr><th>Empresa</th><th>Plan</th><th>Periodicidad</th><th>Vencimiento</th><th>Días restantes</th><th>Estado</th><th>Próxima acción</th></tr></thead><tbody>
@forelse($subscriptions as $subscription)@php($days=now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(),false))
@php($renewal=\App\Domain\Network\Commerce\Models\TenantSaasOrder::where('tenant_id',$subscription->tenant_id)->where('purchase_key','renewal:'.$subscription->uuid.':'.$subscription->current_period_end->toDateString())->first())
<tr><td>{{ $subscription->tenant->name }}</td><td>{{ $subscription->plan->name }}</td><td>{{ $subscription->current_period_start->diffInMonths($subscription->current_period_end)>=10?'ANUAL':'MENSUAL' }}</td><td>{{ $subscription->current_period_end?->format('d/m/Y') }}</td><td>{{ $days }}</td><td><span class="badge badge-{{ $subscription->status }}">{{ strtoupper($subscription->status) }}</span></td><td>@if($renewal)<strong>{{ $days<0?'Saldo pendiente':'Renovación' }}: ${{ number_format((float)$renewal->total_amount,2) }} {{ $renewal->currency }}</strong><br><small>Orden {{ $renewal->status }} · Pago {{ $renewal->payment_status }}</small><br>@endif<a class="btn" href="{{ route('network.subscriptions.show',$subscription) }}">{{ $days<0?'Gestionar vencimiento':'Ver ciclo' }}</a></td></tr>
@empty<tr><td colspan="7">No hay suscripciones para estos filtros.</td></tr>@endforelse</tbody></table>{{ $subscriptions->links() }}</div>
@endsection
