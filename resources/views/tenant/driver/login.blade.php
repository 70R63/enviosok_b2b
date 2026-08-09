@extends('tenant.driver.layout')
@section('title','Acceso Driver')
@section('content')<h1>Acceso Driver</h1><form class="card" method="POST" action="{{ route('tenant.driver.login.store') }}">@csrf<label>Email<input type="email" name="email" required></label><label>Contraseña<input type="password" name="password" required></label>@error('email')<p>{{ $message }}</p>@enderror<button class="btn">Entrar</button></form>@endsection
