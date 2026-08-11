@php($branding=$tenant->branding)
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Activa tu cuenta · {{ $branding?->brand_name ?? $tenant->name }}</title><link rel="stylesheet" href="{{ asset('css/zigo-design-system.css') }}"><style>body{min-height:100vh;display:grid;place-items:center;background:#0b2445;padding:1rem}.panel{width:min(440px,100%);background:#fff;padding:2rem;border-radius:18px}.panel label{display:block;margin-top:1rem}.panel input{width:100%;padding:.8rem;margin-top:.35rem}.panel button{width:100%;margin-top:1.25rem}</style></head><body><main class="panel">
<h1>Activa tu cuenta</h1>
<p>Establece una contraseña segura para ingresar a {{ $branding?->brand_name ?? $tenant->name }}.</p>
@if($errors->any())<div class="z-alert z-alert--danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('tenant.admin.activation.store',['applicationToken'=>$applicationToken]) }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label>Contraseña<input type="password" name="password" required autocomplete="new-password"></label>
    <label>Confirmar contraseña<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
    <button class="btn btn-primary" type="submit">Activar mi cuenta</button>
</form>
</main></body></html>
