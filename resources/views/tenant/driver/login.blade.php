@extends('tenant.driver.layout')
@section('title','Acceso Driver')
@section('content')
@php($centralDriver=$centralDriver??request()->routeIs('driver.*'))
<section class="z-page-header"><div><div class="z-eyebrow">ZIGO Platform</div><h1>Acceso Driver</h1><p class="z-muted">Ingresa a tu operación de última milla.</p></div></section>
<form class="z-card z-stack" method="POST" action="{{ $centralDriver ? route('driver.login.store') : route('tenant.driver.login.store') }}">@csrf
<label class="z-field"><span>Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus aria-describedby="email-error">@error('email')<span class="z-field-error" id="email-error" role="alert">{{ $message }}</span>@enderror</label>
<label class="z-field"><span>Contraseña</span><input type="password" name="password" autocomplete="current-password" required></label>
<button class="z-btn z-btn--block">INICIAR SESIÓN</button></form>
<section class="z-card driver-support"><h2>Soporte</h2><p class="z-muted">Si tienes problemas de acceso, contacta al administrador de tu operación. Para errores de la aplicación, utiliza Soporte ZIGO.</p><span class="z-help">Privacidad y términos: disponibles próximamente.</span></section>
@endsection
