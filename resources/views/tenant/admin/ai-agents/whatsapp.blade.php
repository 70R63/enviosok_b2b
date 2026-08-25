@extends('layouts.tenant-admin')
@section('title','WhatsApp · '.$agent->name)
@section('content')
<section class="card"><h1>WhatsApp</h1><p>Conecta un número de Meta WhatsApp Cloud API para conversaciones individuales con el Agent publicado.</p>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($channel)<p>Webhook: <code>{{ url('/api/ai/whatsapp/'.$channel->webhook_key.'/webhook') }}</code></p>@endif
@if($newVerifyToken)<p>Token de verificación nuevo (se muestra una sola vez): <code>{{ $newVerifyToken }}</code></p>@endif
<form method="POST" action="{{ route('tenant.admin.ai-agents.whatsapp.update',$agent) }}">@csrf @method('PUT')
<label><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$channel?->enabled))> Habilitado</label>
<label>Phone Number ID<input name="phone_number_id" required maxlength="120" value="{{ old('phone_number_id',$channel?->phone_number_id) }}"></label>
<label>Business Account (opcional)<input name="business_account_ref" maxlength="120" value="{{ old('business_account_ref',$channel?->business_account_ref) }}"></label>
<label>Teléfono visible<input name="display_phone" maxlength="40" value="{{ old('display_phone',$channel?->display_phone) }}"></label>
<label>Access Token (dejar vacío para conservar)<input type="password" autocomplete="new-password" name="access_token"></label>
<label>App Secret (dejar vacío para conservar)<input type="password" autocomplete="new-password" name="app_secret"></label>
<label><input type="checkbox" name="rotate_verify_token" value="1"> Regenerar Verify Token</label><button type="submit">Guardar canal</button></form></section>
@endsection
