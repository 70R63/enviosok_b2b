@extends('tenant.admin.layout')
@section('title','Configuración inicial')
@section('content')
<div class="eyebrow">PRIMEROS PASOS</div><h1>Configura tu plataforma</h1>
<p>Revisa tu marca, los servicios contratados y cómo publicar tu operación.</p>
<form method="POST" action="{{ route('tenant.admin.setup.store') }}" enctype="multipart/form-data">
@csrf
<div class="card"><h2>1. Empresa y marca</h2>
<label>Nombre comercial<input name="brand_name" maxlength="160" required value="{{ old('brand_name',$tenant->branding?->brand_name ?? $tenant->name) }}"></label>
<div class="grid"><label>Color principal<input type="color" name="primary_color" value="{{ old('primary_color',$tenant->branding?->primary_color ?? '#1769E0') }}"></label><label>Color secundario<input type="color" name="secondary_color" value="{{ old('secondary_color',$tenant->branding?->secondary_color ?? '#0B2445') }}"></label><label>Color de acento<input type="color" name="accent_color" value="{{ old('accent_color',$tenant->branding?->accent_color ?? '#168CFF') }}"></label></div>
<label>Logo opcional<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label></div>
<div class="card"><h2>2. Operación contratada</h2><p>Plan: <strong>{{ $tenant->currentPlan?->name }}</strong></p><div class="chips">@forelse($subscription?->entitlements ?? [] as $entitlement) @if($entitlement->is_enabled)<span class="badge">{{ $entitlement->module?->name ?? $entitlement->code }}</span>@endif @empty <span>Sin módulos habilitados.</span>@endforelse</div><p class="muted">Los módulos se derivan de tu compra y no pueden habilitarse desde este setup.</p></div>
<div class="card"><h2>3. Pagos</h2>@if($connection?->status === 'CONNECTED')<p>Mercado Pago está conectado.</p>@else<p>Conecta Mercado Pago cuando quieras cobrar a tus clientes.</p><a class="btn" href="{{ route('tenant.admin.payments.index') }}">Configurar pagos</a>@endif</div>
<div class="card"><h2>4. Publicación</h2><p>Dominio actual: <strong>{{ $tenant->domains->firstWhere('is_primary',true)?->domain }}</strong></p><p>Los datos opcionales pueden completarse después.</p><button class="btn btn-primary" type="submit" name="finish" value="1">Finalizar configuración</button> <button class="btn" type="submit">Guardar y continuar después</button></div>
</form>
@endsection
