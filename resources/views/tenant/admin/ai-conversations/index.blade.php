@extends('tenant.admin.layout')
@section('title','Conversaciones')
@section('content')
<div class="eyebrow">ZIGO AI</div><h1>Conversaciones</h1><p>Conversaciones internas de prueba. Los agentes todavía no están publicados.</p>
<div class="card">@forelse($conversations as $conversation)<article><h2><a href="{{ route('tenant.admin.ai-conversations.show',$conversation) }}">{{ $conversation->agent->name }}</a></h2><p>Versión {{ $conversation->agentVersion->version_number }} · {{ ['open'=>'Abierta','handoff_requested'=>'Intervención solicitada','closed'=>'Cerrada'][$conversation->status->value] }}</p></article>@empty<p>Aún no hay conversaciones de prueba.</p>@endforelse</div>{{ $conversations->links() }}
@endsection
