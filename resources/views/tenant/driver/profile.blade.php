@extends('tenant.driver.layout')
@section('title','Perfil')
@section('content')
<header class="z-page-header"><div><div class="z-eyebrow">Cuenta Driver</div><h1>Perfil</h1><p class="z-muted">Información administrada por tu operación.</p></div></header>
<section class="z-card"><dl class="driver-detail"><div><dt>Nombre</dt><dd>{{ auth()->user()->name }}</dd></div><div><dt>Código</dt><dd>{{ $profile->code }}</dd></div><div><dt>Operando para</dt><dd>{{ $tenant->branding?->brand_name??$tenant->name }}</dd></div><div><dt>Vehículo</dt><dd>{{ $profile->vehicle_label?:'Sin especificar' }}</dd></div><div><dt>Disponibilidad</dt><dd>{{ ['AVAILABLE'=>'Disponible','BUSY'=>'Ocupado','UNAVAILABLE'=>'No disponible'][$profile->availability_status]??$profile->availability_status }}</dd></div><div><dt>Estado</dt><dd>{{ $profile->status==='ACTIVE'?'Activo':'Inactivo' }}</dd></div></dl></section>
<div class="driver-actions"><a class="z-btn z-btn--outline" href="{{ route('driver.support') }}">Ayuda y soporte</a><button class="z-btn z-btn--outline" type="button" onclick="document.getElementById('install-driver')?.click()">Instalar App</button><form method="POST" action="{{ route('driver.logout') }}">@csrf<button class="z-btn z-btn--danger z-btn--block">Cerrar sesión</button></form></div>
@endsection
