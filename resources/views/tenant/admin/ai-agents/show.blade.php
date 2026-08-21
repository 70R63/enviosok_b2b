@extends('tenant.admin.layout')
@section('title')
{{ $agent->name }}
@endsection
@section('content')
<div class="eyebrow">BORRADOR DE AGENTE</div><h1>{{ $agent->name }}</h1><div class="chips"><span class="chip">Borrador</span><span class="chip">{{ \App\Support\Presentation\AiAgentPresentation::agentType($agent->type) }}</span><span class="chip">Versión {{ $agent->versions->first()?->version_number??1 }}</span></div><p>{{ $agent->description }}</p>
@php($contract=$agent->contract?->versions?->first())
<article class="card"><h2>Resumen del contrato del agente</h2>@if($contract)<p>{{ $contract->job_to_be_done }}</p><h3>Objetivos</h3><ul>@foreach($contract->objectives as $objective)<li>{{ \App\Support\Presentation\AiAgentPresentation::outcome($objective) }}</li>@endforeach</ul><h3>Canales previstos</h3><p>@foreach($contract->channels as $channel){{ !$loop->first?', ':'' }}{{ \App\Support\Presentation\AiAgentPresentation::channel($channel) }}@endforeach</p>@else<p class="muted">El contrato se está preparando.</p>@endif</article>
<article class="card"><h2>Siguientes pasos</h2><p><strong>El borrador fue creado.</strong> Todavía no conversa con clientes y todavía no está publicado.</p><ul><li>Conocimiento: pendiente.</li><li>Canal Chat web: pendiente de configuración.</li><li>Pruebas: pendientes.</li><li>Contrato: pendiente de revisión y aprobación.</li><li>Publicación: pendiente.</li></ul><p>El equipo ZIGO continuará con la configuración y validación.</p></article><a class="z-btn" href="{{ route('tenant.admin.ai-agents.index') }}">Volver a Mis agentes</a>
@endsection
