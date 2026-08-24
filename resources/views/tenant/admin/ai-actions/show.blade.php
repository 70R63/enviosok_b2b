@extends('tenant.admin.layout')
@section('title','Acción solicitada')
@section('content')
<div class="eyebrow">ACCIÓN DEL AGENTE</div><h1>{{ app(\App\Domain\AI\Actions\ActionRegistry::class)->find($actionRun->action_key)?->displayName ?? 'Acción no disponible' }}</h1><p>Agente: {{ $actionRun->conversation->agent->name }}</p><p>Estado: {{ ['requested'=>'Solicitada','awaiting_confirmation'=>'Requiere confirmación','executing'=>'En ejecución','succeeded'=>'Completada','failed'=>'No completada'][$actionRun->status->value] }}</p><p>Solicitada: {{ $actionRun->requested_at->format('Y-m-d H:i') }}</p>
@if($actionRun->status->value==='awaiting_confirmation')<form method="POST" action="{{ route('tenant.admin.ai-actions.confirm',$actionRun) }}">@csrf<button class="z-btn" type="submit">Confirmar acción</button></form>@endif
<a class="z-btn z-btn--ghost" href="{{ route('tenant.admin.ai-conversations.show',$actionRun->conversation) }}">Volver a la conversación</a>
@endsection
