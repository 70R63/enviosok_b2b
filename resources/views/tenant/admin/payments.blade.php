@extends('tenant.admin.layout')
@section('title','Pagos')
@section('content')
<nav class="z-breadcrumbs" aria-label="Ruta"><a href="{{ route('tenant.admin.configuration.edit') }}">Configuración</a><span>Pagos</span></nav>
<header class="z-page-header"><div><div class="z-eyebrow">Tenant Admin</div><h1>Pagos</h1><p class="z-muted">El dinero de las guías se acredita en la cuenta seller del tenant.</p></div></header>
@if(session('success'))<div class="z-alert z-alert--success" role="status">{{ session('success') }}</div>@endif
<section class="z-card z-stack"><div><span class="z-badge z-badge--info">{{ strtoupper($environment) }}</span><h2>Mercado Pago</h2></div>
@if($connection?->isConnected())<span class="z-badge z-badge--success">Conectado ✓</span>@if($connectionRequiresReconnect)<div class="z-alert" role="alert"><strong>Debes reconectar Mercado Pago.</strong> La conexión actual usa credenciales de prueba incompatibles con la estrategia configurada.</div>@endif<dl><dt>Cuenta</dt><dd>{{ $connection->provider_account_id ?: 'Identificador protegido' }}</dd><dt>Conectado</dt><dd>{{ $connection->connected_at?->format('d/m/Y H:i') }}</dd></dl>
@if($canManage)<div class="z-form-actions"><a class="z-btn z-btn--outline" href="{{ route('tenant.admin.payments.mercado-pago.connect') }}">Reconectar</a><form method="POST" action="{{ route('tenant.admin.payments.mercado-pago.disconnect') }}">@csrf @method('DELETE')<button class="z-btn z-btn--danger">Desconectar</button></form></div>@endif
@else<span class="z-badge z-badge--muted">No conectado</span><p>Autoriza a ZIGO para crear cobros en tu propia cuenta Mercado Pago.</p>@if($canManage)<a class="z-btn" href="{{ route('tenant.admin.payments.mercado-pago.connect') }}">Conectar Mercado Pago</a>@endif
@endif<p class="z-help">Nunca mostramos ni solicitamos Access Token, Refresh Token o Client Secret.</p></section>
@endsection
