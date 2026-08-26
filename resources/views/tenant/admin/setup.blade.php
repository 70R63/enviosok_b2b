@extends('tenant.admin.layout')
@section('title','Configuración inicial')
@section('content')
<div class="eyebrow">PRIMEROS PASOS</div><h1>{{ $isAiWorkspace ? 'Configura Agentes IA' : 'Configura tu plataforma' }}</h1>
<p>{{ $isAiWorkspace ? 'Personaliza tu cuenta y prepara tu primer Agente IA.' : 'Revisa tu marca, los servicios contratados y cómo publicar tu operación.' }}</p>
<form method="POST" action="{{ route('tenant.admin.setup.store') }}" enctype="multipart/form-data">
@csrf
<div class="card"><h2>1. Empresa y marca</h2>
<label>Nombre comercial<input name="brand_name" maxlength="160" required value="{{ old('brand_name',$tenant->branding?->brand_name ?? $tenant->name) }}"></label>
<div class="grid"><label>Color principal<input type="color" name="primary_color" value="{{ old('primary_color',$tenant->branding?->primary_color ?? '#1769E0') }}"></label><label>Color secundario<input type="color" name="secondary_color" value="{{ old('secondary_color',$tenant->branding?->secondary_color ?? '#0B2445') }}"></label><label>Color de acento<input type="color" name="accent_color" value="{{ old('accent_color',$tenant->branding?->accent_color ?? '#168CFF') }}"></label></div>
<label>Logo opcional<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label></div>
@if($isAiWorkspace)
<div class="card"><h2>2. Tu plan Agentes IA</h2><p>Plan: <strong>{{ $tenant->currentPlan?->name }}</strong></p><div class="chips"><span class="badge">Agentes: {{ $aiCapacity['MAX_AGENTS']->limit }}</span><span class="badge">Webchat: {{ $aiCapacity['MAX_WEBCHAT_CHANNELS']->limit }}</span><span class="badge">WhatsApp: {{ $aiCapacity['MAX_WHATSAPP_CHANNELS']->limit }}</span><span class="badge">Conversaciones mensuales: {{ $aiCapacity['MONTHLY_CONVERSATIONS']->limit }}</span></div></div>
<div class="card"><h2>3. Tu primer Agente IA</h2><p>Después de finalizar podrás crear tu primer Agente IA desde el Launchpad.</p><a class="btn" href="{{ route('tenant.admin.ai-agents.launchpad.create') }}">Crear mi primer agente</a></div>
<div class="card"><h2>4. Finalizar configuración</h2><p>Tu marca quedará lista para comenzar.</p><button class="btn btn-primary" type="submit" name="finish" value="1">Finalizar configuración</button> <button class="btn" type="submit">Guardar y continuar después</button></div>
@else
<div class="card"><h2>2. Operación contratada</h2><p>Plan: <strong>{{ $tenant->currentPlan?->name }}</strong></p><div class="chips">@forelse($subscription?->entitlements ?? [] as $entitlement) @if($entitlement->is_enabled)<span class="badge">{{ $entitlement->module?->name ?? $entitlement->code }}</span>@endif @empty <span>Sin módulos habilitados.</span>@endforelse</div><p class="muted">Los módulos se derivan de tu compra y no pueden habilitarse desde este setup.</p></div>
<div class="card"><h2>3. Pagos</h2>@if($connection?->status === 'CONNECTED')<p>Mercado Pago está conectado.</p>@else<p>Conecta Mercado Pago cuando quieras cobrar a tus clientes.</p><a class="btn" href="{{ route('tenant.admin.payments.index') }}">Configurar pagos</a>@endif</div>
<div class="card"><h2>4. Publicación</h2><p>Dominio actual: <strong>{{ $tenant->domains->firstWhere('is_primary',true)?->domain }}</strong></p><p>Los datos opcionales pueden completarse después.</p><button class="btn btn-primary" type="submit" name="finish" value="1">Finalizar configuración</button> <button class="btn" type="submit">Guardar y continuar después</button></div>
@endif
</form>
@endsection
