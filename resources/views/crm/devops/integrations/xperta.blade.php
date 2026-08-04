@extends('devops.layout')
@section('title','Xperta / Estafeta - ZIGO DevOps')
@section('content')
<div class="title">Xperta / Estafeta</div>
<div class="subtitle">Configuración aislada del probador Stage. No lee configuración Xperta PRD, no captura credenciales y no persiste operaciones comerciales.</div>
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
@if(session('probe_result'))@php($r=session('probe_result'))<div class="alert {{ $r['success']?'alert-success':'alert-error' }}">{{ strtoupper($r['operation']) }}: {{ $r['success']?'OK':'ERROR' }} · HTTP {{ $r['http_status']??'—' }} · {{ $r['duration_ms'] }} ms · {{ $r['code']??'sin código' }}</div>@endif
<section class="card"><h2>Configuración redactada</h2><div class="summary-grid">
@foreach(['environment'=>'Ambiente','host'=>'Host','corporativo'=>'Corporativo','ltd'=>'LTD'] as $key=>$label)<div><span class="muted">{{ $label }}</span><strong>{{ $configuration[$key]?:'No configurado' }}</strong></div>@endforeach
<div><span class="muted">Servicios</span><strong>{{ implode(', ',$configuration['services'])?:'No configurados' }}</strong></div>
<div><span class="muted">Credenciales presentes</span><strong>Email {{ $configuration['credentials']['email']?'sí':'no' }} · Password {{ $configuration['credentials']['password']?'sí':'no' }} · API key {{ $configuration['credentials']['api_key']?'sí':'no' }}</strong></div>
@php($last=$history->first())<div><span class="muted">Último resultado</span><strong>{{ $last?->status??'Sin resultados' }} · HTTP {{ $last?->http_status??'—' }} · {{ $last?->duration_ms??'—' }} ms · {{ $last?->provider_code??'sin código' }}</strong></div>
</div>@if($configuration['environment']!=='stage')<div class="alert alert-error" style="margin-top:16px">PRD visible, pero bloqueado para ejecución.</div>@endif</section>

@if($canExecute && $configuration['enabled'] && $configuration['environment']==='stage')
<div class="summary-grid">
<form class="card" method="POST" action="{{ url('/integrations/xperta-estafeta/token') }}">@csrf<h2>Token</h2><p class="muted">Fuerza un login nuevo; nunca muestra el token.</p><button class="btn">Probar token</button></form>
<form class="card" method="POST" action="{{ url('/integrations/xperta-estafeta/frequency') }}">@csrf<h2>Frecuencia</h2><label>CP origen<input name="origin" inputmode="numeric" pattern="\d{5}" maxlength="5" required></label><label>CP destino<input name="destination" inputmode="numeric" pattern="\d{5}" maxlength="5" required></label><button class="btn">Probar frecuencia</button></form>
</div>
<form class="card" method="POST" action="{{ url('/integrations/xperta-estafeta/quote') }}">@csrf<h2>Cotización técnica</h2><div class="summary-grid">
@foreach(['origin'=>'CP origen','destination'=>'CP destino','weight'=>'Peso','length'=>'Largo','width'=>'Ancho','height'=>'Alto','declared_value'=>'Valor declarado'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" type="{{ in_array($key,['origin','destination'])?'text':'number' }}" {{ in_array($key,['origin','destination'])?'pattern=\d{5} maxlength=5':'step=0.01 min=0' }} required></label>@endforeach
<label>Servicio<select name="service"><option value="terrestre">Terrestre</option><option value="diasig">Día siguiente</option></select></label></div><button class="btn" style="margin-top:14px">Probar cotización</button></form>
@elseif(!$canExecute)<div class="alert alert-info">Modo solo lectura. Solo sysadmin puede ejecutar pruebas.</div>@else<div class="alert alert-error">El probador está deshabilitado o el ambiente no es Stage.</div>@endif

@if(session('probe_result'))<section class="card"><h2>Resultado sanitizado</h2><div class="table-wrap"><table><tbody>@foreach(session('probe_result') as $key=>$value)<tr><th>{{ $key }}</th><td>{{ is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):($value===true?'true':($value===false?'false':($value??'—'))) }}</td></tr>@endforeach</tbody></table></div></section>@endif
<section class="card"><h2>Historial</h2><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Ambiente</th><th>Operación</th><th>Estado</th><th>HTTP</th><th>Duración</th><th>Código</th><th>Metadata segura</th></tr></thead><tbody>
@forelse($history as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->environment }}</td><td>{{ $event->operation }}</td><td>{{ $event->status }}</td><td>{{ $event->http_status??'—' }}</td><td>{{ $event->duration_ms }} ms</td><td>{{ $event->provider_code??'—' }}</td><td><code>{{ json_encode($event->metadata,JSON_UNESCAPED_UNICODE) }}</code></td></tr>@empty<tr><td colspan="8">Sin pruebas registradas.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
