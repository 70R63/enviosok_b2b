@extends('network.layout')
@section('title','Suscripciones · '.$tenant->name)
@section('content')
<div class="eyebrow">SUBSCRIPTION + ENTITLEMENTS + USAGE</div>
<div class="title">{{ $tenant->name }}</div>
<p class="subtitle">Gestión manual foundation; no existe proveedor de pago ni suspensión automática.</p>

@if(!$current)
<section class="card">
    <h2>Crear suscripción</h2>
    <form method="POST" action="{{ route('network.tenants.subscriptions.store',$tenant) }}" class="sub-form">
        @csrf
        <label>Plan<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · {{ $plan->code }}</option>@endforeach</select></label>
        <label>Estado<select name="status">@foreach(\App\Domain\Network\Billing\Models\Subscription::STATUSES as $status)<option value="{{ $status }}">{{ strtoupper($status) }}</option>@endforeach</select></label>
        <label>Inicio<input type="datetime-local" name="started_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></label>
        <label>Inicio periodo<input type="datetime-local" name="current_period_start" value="{{ now()->startOfDay()->format('Y-m-d\TH:i') }}" required></label>
        <label>Fin periodo<input type="datetime-local" name="current_period_end" value="{{ now()->addMonth()->endOfDay()->format('Y-m-d\TH:i') }}" required></label>
        <label>Trial hasta<input type="datetime-local" name="trial_ends_at"></label>
        <label>Grace hasta<input type="datetime-local" name="grace_ends_at"></label>
        <button class="btn">CREAR Y GENERAR SNAPSHOT</button>
    </form>
</section>
@else
<section class="card subscription-card">
    <div class="subscription-heading">
        <div>
            <span class="field-caption">Plan</span>
            <h2>{{ $current->plan->name }}</h2>
        </div>
        <span class="subscription-status status-{{ $current->status }}">{{ strtoupper($current->status) }}</span>
    </div>

    <div class="subscription-facts">
        <div><span class="field-caption">Periodo</span><strong>{{ $current->current_period_start->format('d/m/Y') }} <span aria-hidden="true">→</span> {{ $current->current_period_end->format('d/m/Y') }}</strong></div>
        <div><span class="field-caption">Límite operaciones</span><strong>{{ $current->operations_limit ?? 'Sin límite' }}</strong></div>
    </div>

    <div class="entitlements-block">
        <span class="field-caption">Módulos / Entitlements</span>
        <div class="entitlement-list" aria-label="Módulos incluidos">
            @forelse($current->entitlements as $entitlement)
                <span class="module-chip" title="{{ $entitlement->code }}">
                    {{ $entitlement->module->name ?? \Illuminate\Support\Str::headline($entitlement->code) }}
                    @if($entitlement->limit_value !== null)<small>· {{ $entitlement->limit_value }}</small>@endif
                </span>
            @empty
                <span class="muted">Sin módulos incluidos.</span>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('network.tenants.subscriptions.status',[$tenant,$current]) }}" class="inline-form status-form">
        @csrf @method('PATCH')
        <label>Estado administrativo<select name="status">@foreach(\App\Domain\Network\Billing\Models\Subscription::STATUSES as $status)<option value="{{ $status }}" @selected($current->status===$status)>{{ strtoupper($status) }}</option>@endforeach</select></label>
        <button class="btn">ACTUALIZAR ESTADO</button>
    </form>
</section>

<section class="card usage-card">
    <h2>Usage manual</h2>
    <div class="usage-summary">
        <strong>{{ $summary['used'] }} / {{ $summary['limit'] ?? 'sin límite' }}</strong>
        @if($summary['remaining'] !== null)<span>{{ $summary['remaining'] }} disponibles</span><span>{{ $summary['percentage'] }}%</span>@endif
        @if($summary['over_limit'])<span class="over-limit">SOBRE LÍMITE</span>@endif
    </div>
    <div class="usage-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100,$summary['percentage'] ?? 0) }}"><i style="width:{{ min(100,$summary['percentage'] ?? 0) }}%"></i></div>
    <form method="POST" action="{{ route('network.tenants.usage.store',$tenant) }}" class="inline-form usage-form">
        @csrf
        <input type="hidden" name="metric" value="operations">
        <label>Cantidad<input type="number" name="quantity" min="1" max="10000" value="1" required></label>
        <label>Idempotency key<input name="idempotency_key" placeholder="Opcional"></label>
        <button class="btn">REGISTRAR OPERACIÓN</button>
    </form>
</section>
@endif

<section class="card">
    <h2>Historial</h2>
    <div class="table-wrap">
        <table><thead><tr><th>UUID</th><th>PLAN</th><th>ESTADO</th><th>PERIODO</th></tr></thead><tbody>
        @forelse($history as $item)
            <tr><td><span class="subscription-uuid" title="{{ $item->uuid }}">{{ $item->uuid }}</span></td><td>{{ $item->plan->name }}</td><td><span class="history-status status-{{ $item->status }}">{{ strtoupper($item->status) }}</span></td><td>{{ $item->current_period_start->format('d/m/Y') }} — {{ $item->current_period_end->format('d/m/Y') }}</td></tr>
        @empty
            <tr><td colspan="4">Sin historial.</td></tr>
        @endforelse
        </tbody></table>
    </div>
</section>
@endsection

@push('styles')
<style>
.subscription-card h2,.usage-card h2{margin:3px 0 0}.subscription-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.field-caption{display:block;margin-bottom:5px;color:#64748b;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.subscription-status,.history-status{display:inline-flex;align-items:center;border-radius:999px;background:#e2e8f0;color:#475569;font-size:11px;font-weight:900;padding:6px 10px}.status-active{background:#dcfce7;color:#166534}.status-trial{background:#dbeafe;color:#1e40af}.status-past_due,.status-grace{background:#fef3c7;color:#92400e}.status-suspended,.status-canceled,.status-ended{background:#fee2e2;color:#991b1b}.subscription-facts{display:flex;flex-wrap:wrap;gap:18px 40px;margin:18px 0}.subscription-facts strong{display:block;font-size:14px}.entitlements-block{padding-top:14px;border-top:1px solid #e5e7eb}.entitlement-list{display:flex;flex-wrap:wrap;gap:7px}.module-chip{display:inline-flex;align-items:center;white-space:nowrap;border:1px solid #bfdbfe;border-radius:999px;background:#eff6ff;color:#1e40af;font-size:12px;font-weight:800;line-height:1;padding:6px 9px}.module-chip small{font-size:11px}.sub-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}.sub-form label,.inline-form label{font-size:11px;font-weight:800}.sub-form input,.sub-form select,.inline-form input,.inline-form select{width:100%;margin-top:5px;padding:9px;border:1px solid #ccd7e3;border-radius:7px}.inline-form{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-top:15px}.inline-form label{width:220px}.inline-form .btn{flex:0 0 auto}.status-form{padding-top:15px;border-top:1px solid #e5e7eb}.usage-summary{display:flex;align-items:baseline;flex-wrap:wrap;gap:6px 14px;margin:12px 0 10px;color:#64748b;font-size:13px}.usage-summary strong{color:#111827;font-size:24px}.usage-summary .over-limit{color:#991b1b;font-weight:900}.usage-progress{height:8px;background:#e5ebf2;border-radius:9px;overflow:hidden}.usage-progress i{display:block;height:100%;background:#185fa8}.usage-form label:first-of-type{width:110px}.subscription-uuid{display:inline-block;max-width:22ch;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom;font:11px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:nowrap}.history-status{font-size:10px;padding:4px 7px}
@media(max-width:600px){.subscription-heading{align-items:center}.subscription-facts{display:grid;gap:14px}.inline-form,.inline-form label,.inline-form .btn{width:100%}.entitlement-list{gap:6px}.subscription-uuid{max-width:14ch}}
</style>
@endpush
