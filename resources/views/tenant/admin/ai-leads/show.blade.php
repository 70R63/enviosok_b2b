@extends('tenant.admin.layout')
@section('title','Detalle del lead')
@section('content')
<div class="eyebrow">LEAD</div><h1>{{ $lead->data['name']??$lead->data['company']??'Prospecto' }}</h1><p>{{ $lead->agent->name }} · Versión {{ $lead->agentVersion->version_number }} · {{ $lead->detected_at->format('d/m/Y H:i') }}</p>
<div class="card"><h2>Datos capturados</h2><dl>@foreach($lead->data as $key=>$value)<dt>{{ ['name'=>'Nombre','email'=>'Correo','phone'=>'Teléfono','company'=>'Empresa','interest'=>'Interés'][$key]??'Dato' }}</dt><dd>{{ $value }}</dd>@endforeach</dl></div>
<div class="card"><h2>Outcome y evidencia</h2>@forelse($lead->outcomes as $outcome)<p><strong>{{ $outcome->outcome_type->value==='valid_lead'?'Lead válido':'Consulta resuelta' }}</strong><br>{{ ['detected'=>'Detectado','verified'=>'Verificado'][$outcome->status->value] }} · {{ $outcome->detected_at->format('d/m/Y H:i') }}<br>{{ ($outcome->evidence['required_fields_satisfied']??false)?'Datos requeridos completos':'Evidencia detectada pendiente de verificación' }}</p>@empty<p>Sin outcomes relacionados.</p>@endforelse</div>
<p><a class="z-btn z-btn--ghost" href="{{ route('tenant.admin.ai-conversations.show',$lead->conversation) }}">Ver transcript</a></p>
@endsection
