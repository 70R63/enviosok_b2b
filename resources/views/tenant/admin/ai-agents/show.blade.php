@extends('tenant.admin.layout')
@section('title')
{{ $agent->name }}
@endsection
@section('content')
<div class="eyebrow">AGENT DRAFT</div><h1>{{ $agent->name }}</h1><div class="chips"><span class="chip">{{ strtoupper($agent->status->value) }}</span><span class="chip">{{ str_replace('_',' ',ucfirst($agent->type->value)) }}</span><span class="chip">Versión {{ $agent->versions->first()?->version_number??1 }}</span></div><p>{{ $agent->description }}</p>
@php($contract=$agent->contract?->versions?->first())
<article class="card"><h2>Resumen del Agent Contract</h2>@if($contract)<p>{{ $contract->job_to_be_done }}</p><h3>Objetivos</h3><ul>@foreach($contract->objectives as $objective)<li>{{ $objective }}</li>@endforeach</ul><h3>Canales previstos</h3><p>{{ implode(', ',$contract->channels) }}</p>@else<p class="muted">El contrato se está preparando.</p>@endif</article>
<article class="card"><h2>Siguientes pasos</h2><p>Tu agente se creó como borrador. INNOTECH revisará conocimiento, canales, pruebas y publicación contigo.</p><p class="muted">Todavía no está publicado ni conectado a canales.</p></article><a class="z-btn" href="{{ route('tenant.admin.ai-agents.index') }}">Volver a Mis agentes</a>
@endsection
