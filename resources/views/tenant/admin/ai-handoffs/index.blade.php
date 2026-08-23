@extends('tenant.admin.layout')
@section('title','Handoffs')
@section('content')
<div class="eyebrow">AI CORE</div><h1>Handoffs</h1><p>Conversaciones internas que requieren atención humana.</p>
<div class="card">@forelse($handoffs as $handoff)<article><h2>{{ $handoff->conversation->agent->name }}</h2><p>{{ $handoff->requested_at->format('d/m/Y H:i') }} · {{ $handoff->status->value==='requested'?'Solicitud de atención':'Atención humana activa' }}</p><p>{{ $handoff->safe_reason??'Revisión humana requerida' }} · {{ $handoff->assignee?->name??'Sin asignar' }}</p>@if($handoff->status->value==='requested')<form method="POST" action="{{ route('tenant.admin.ai-handoffs.take',$handoff) }}">@csrf<button class="z-btn">Tomar conversación</button></form>@elseif((int)$handoff->assigned_user_id===auth()->id())<a class="z-btn" href="{{ route('tenant.admin.ai-conversations.show',$handoff->conversation) }}">Abrir conversación</a>@endif</article>@empty<p>No hay solicitudes activas.</p>@endforelse</div>{{ $handoffs->links() }}
@endsection
