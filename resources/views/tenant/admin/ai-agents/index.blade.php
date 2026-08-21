@extends('tenant.admin.layout')
@section('title','Mis agentes')
@section('content')
<div class="eyebrow">IA PARA TU NEGOCIO</div><div class="z-cluster" style="justify-content:space-between"><div><h1>Mis agentes</h1><p class="muted">Administra los borradores preparados para tu negocio.</p></div><a class="z-btn z-btn--primary" href="{{ route('tenant.admin.ai-agents.launchpad.create') }}">Crear agente</a></div>
<section class="grid">@forelse($agents as $agent)<a class="card" href="{{ route('tenant.admin.ai-agents.show',$agent) }}"><strong>{{ $agent->name }}</strong><p class="muted">{{ str_replace('_',' ',ucfirst($agent->type->value)) }}</p><span class="chip">{{ strtoupper($agent->status->value) }}</span></a>@empty<article class="card"><h2>Aún no tienes agentes</h2><p class="muted">Cuéntanos qué necesitas resolver y prepararemos una propuesta inicial configurable.</p><a class="z-btn z-btn--primary" href="{{ route('tenant.admin.ai-agents.launchpad.create') }}">Crear mi primer agente</a></article>@endforelse</section>
{{ $agents->links() }}
@endsection
