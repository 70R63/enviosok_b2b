@extends('tenant.admin.layout')
@section('title','Leads')
@section('content')
<div class="eyebrow">AGENTES IA</div><h1>Leads</h1><p>Prospectos detectados en conversaciones internas.</p>
<div class="card">@forelse($leads as $lead)<article><h2><a href="{{ route('tenant.admin.ai-leads.show',$lead) }}">{{ $lead->data['name']??$lead->data['company']??'Prospecto' }}</a></h2><p>{{ $lead->detected_at->format('d/m/Y H:i') }} · {{ $lead->agent->name }} · {{ ['detected'=>'Detectado','verified'=>'Verificado'][$lead->status->value] }}</p><p>@if(isset($lead->data['email'])){{ $lead->data['email'] }}@elseif(isset($lead->data['phone'])){{ $lead->data['phone'] }}@else Datos de contacto protegidos @endif</p><p><a href="{{ route('tenant.admin.ai-conversations.show',$lead->conversation) }}">Ver conversación</a> · {{ $lead->outcomes->contains(fn($o)=>$o->outcome_type->value==='valid_lead')?'Lead válido':'Sin outcome verificado' }}</p></article>@empty<p>Aún no hay leads.</p>@endforelse</div>{{ $leads->links() }}
@endsection
