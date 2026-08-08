@extends('network.layout')
@section('title',$tenant->name.' - ZIGO Network')
@section('content')
<header class="tenant-head"><div><div class="eyebrow">TENANT</div><div class="title">{{ $tenant->name }}</div><div class="subtitle">{{ $tenant->slug }} · {{ $tenant->uuid }}</div></div><a class="btn" href="{{ route('network.tenants.edit',$tenant) }}">EDITAR TENANT Y PLAN</a></header>
<section class="card tenant-summary"><div><small>ESTADO</small><strong>{{ ucfirst($tenant->status) }}</strong></div><div><small>PLAN</small><strong>{{ $tenant->currentPlan?->name ?? 'Sin plan' }}</strong></div><div><small>MÓDULOS INCLUIDOS</small><strong>{{ $tenant->currentPlan?->modules->count() ?? 0 }}</strong></div><div><small>OPERACIONES</small><strong>{{ $tenant->currentPlan?->included_operations ?? 'N/A' }}</strong></div></section>
<section class="card plan-services"><h2>Servicios del plan</h2>
@if($tenant->currentPlan)
<p><strong>{{ $tenant->currentPlan->name }}</strong> · {{ $tenant->currentPlan->code }}</p>
@forelse($tenant->currentPlan->modules as $module)
<span class="module-chip">{{ $module->name }}</span>
@empty
<p class="muted">El plan no incluye módulos.</p>
@endforelse
@else
<p class="empty-plan">Sin plan comercial asignado</p>
@endif
<p class="muted context-note">Plan configuration · Entitlements pendientes ZN-04.</p></section>
<section class="card"><h2>Dominios</h2>@forelse($tenant->domains as $domain)<span class="module-chip">{{ $domain->domain }} · {{ strtoupper($domain->environment) }} · {{ $domain->is_primary?'PRIMARY · ':'' }}{{ strtoupper($domain->status) }}</span>@empty<p class="muted">Sin dominios registrados.</p>@endforelse<p><a class="btn" href="{{ route('network.tenants.domains.index',$tenant) }}">ADMINISTRAR DOMINIOS</a></p></section>
<section class="card"><h2>Branding</h2><p><strong>{{ $tenant->branding?->brand_name??'Fallback ZIGO neutro' }}</strong></p><p class="muted">Primario {{ $tenant->branding?->primary_color??'#185FA8' }} · Secundario {{ $tenant->branding?->secondary_color??'#0B2948' }} · Accent {{ $tenant->branding?->accent_color??'#39A0ED' }}</p><a class="btn" href="{{ route('network.tenants.branding.edit',$tenant) }}">CONFIGURAR BRANDING</a> <a class="btn" href="{{ route('network.tenants.preview',$tenant) }}">PREVIEW WHITE LABEL</a></section>
<section class="tenant-pending-grid">@foreach(['PRODUCCIÓN'=>$tenant->primaryDomain?->domain??'Pendiente','SANDBOX'=>'No contratado','USAGE'=>'Pendiente','BILLING'=>'Pendiente Billing SaaS'] as $heading=>$pending)<article class="card pending-tile"><small>{{ $heading }}</small><strong>{{ $pending }}</strong></article>@endforeach</section>
@endsection
@push('styles')<style>.tenant-head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:18px}.tenant-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;padding:0;overflow:hidden}.tenant-summary>div{padding:16px;border-right:1px solid #e2e8f0}.tenant-summary>div:last-child{border:0}.tenant-summary small,.pending-tile small{display:block;color:#64748b;font-size:10px;font-weight:900;letter-spacing:.08em;margin-bottom:6px}.tenant-summary strong{font-size:18px}.plan-services{padding:17px}.plan-services h2{margin:0 0 10px}.module-chip{display:inline-block;margin:3px;padding:6px 9px;border-radius:8px;background:#e7f0ff;color:#174f91;font-weight:800;font-size:11px}.context-note{margin-bottom:0;font-size:11px}.empty-plan{padding:11px;border-radius:9px;background:#f1f5f9;font-weight:800}.tenant-pending-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px}.pending-tile{padding:14px;margin:0;min-height:82px}.pending-tile strong{display:block;font-size:13px;line-height:1.35}@media(max-width:750px){.tenant-head{align-items:start;flex-direction:column}.tenant-summary{grid-template-columns:repeat(2,1fr)}.tenant-summary>div:nth-child(2){border-right:0}}@media(max-width:420px){.tenant-summary{grid-template-columns:1fr}.tenant-summary>div{border-right:0;border-bottom:1px solid #e2e8f0}}</style>@endpush
