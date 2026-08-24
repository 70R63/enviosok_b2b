@extends('tenant.admin.layout')
@section('title','Webchat · '.$agent->name)
@section('content')
<header class="page-head"><div><p class="eyebrow">Agent · Channels</p><h1>Webchat</h1><p>Configura el chat público del Agent publicado.</p></div><a class="z-btn" href="{{ route('tenant.admin.ai-agents.show',$agent) }}">Volver al Agent</a></header>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<article class="card"><form method="POST" action="{{ route('tenant.admin.ai-agents.webchat.update',$agent) }}">@csrf @method('PUT')
<label>Nombre visible<input name="display_name" maxlength="120" required value="{{ old('display_name',$channel?->display_name ?? $agent->name) }}"></label>
<label>Mensaje de bienvenida<textarea name="welcome_message" maxlength="500">{{ old('welcome_message',$channel?->welcome_message) }}</textarea></label>
<label>Color principal<input name="primary_color" pattern="#[0-9A-Fa-f]{6}" required value="{{ old('primary_color',$channel?->primary_color ?? '#2563EB') }}"></label>
<label>Texto del botón<input name="launcher_label" maxlength="80" required value="{{ old('launcher_label',$channel?->launcher_label ?? 'Chat') }}"></label>
<label>Dominios autorizados (uno por línea)<textarea name="allowed_origins" required placeholder="https://cliente.com">{{ old('allowed_origins',implode("\n",$channel?->allowed_origins ?? [])) }}</textarea></label>
<input type="hidden" name="enabled" value="0"><label><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$channel?->enabled))> Webchat habilitado</label>
<button class="z-btn z-btn--primary" type="submit">Guardar Webchat</button></form></article>
@if($channel)<article class="card"><h2>Instalación</h2><p>Clave pública: <code>{{ $channel->public_key }}</code></p><label>Snippet embebible<textarea readonly rows="4">&lt;script src="{{ route('ai.webchat.widget') }}" data-channel="{{ $channel->public_key }}" defer&gt;&lt;/script&gt;</textarea></label><a class="z-btn" target="_blank" rel="noopener" href="{{ route('ai.webchat.hosted',$channel->public_key) }}">Abrir vista previa</a><form method="POST" action="{{ route('tenant.admin.ai-agents.webchat.rotate',$agent) }}">@csrf<button class="z-btn" type="submit">Regenerar clave pública</button></form></article>@endif
@endsection
