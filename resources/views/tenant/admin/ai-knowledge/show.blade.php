@extends('tenant.admin.layout')
@section('title'){{ $source->name }}@endsection
@section('content')
<div class="z-cluster z-cluster--between"><div><div class="eyebrow">CONOCIMIENTO</div><h1>{{ $source->name }}</h1><p>{{ $source->type->value==='faq'?'Preguntas frecuentes':'Fuente manual' }}</p></div><a class="z-btn" href="{{ route('tenant.admin.ai-knowledge.revisions.create',$source) }}">Crear revisión</a></div>
@foreach($source->versions as $version)
@php($index=$version->index)
<article class="card"><h2>Versión {{ $version->version_number }}</h2><span class="chip">{{ $version->status->value==='draft'?'Borrador':($version->status->value==='approved'?'Aprobado':'Versión anterior') }}</span>
@if($source->type->value==='manual_text')<p style="white-space:pre-wrap">{{ $version->content['body'] }}</p>@else<dl>@foreach($version->content['entries'] as $entry)<dt><strong>{{ $entry['question'] }}</strong></dt><dd>{{ $entry['answer'] }}</dd>@endforeach</dl>@endif
<h3>Preparación</h3><p>@if(!$index)Sin preparar @elseif($index->status->value==='pending')Pendiente @elseif($index->status->value==='processing')Procesando @elseif($index->status->value==='ready')Conocimiento listo para pruebas @else No fue posible preparar esta versión. Puedes volver a intentarlo. @endif</p>
@if($version->status->value==='draft')<form method="POST" action="{{ route('tenant.admin.ai-knowledge.versions.approve',[$source,$version]) }}">@csrf<button class="z-btn" type="submit">Aprobar versión</button></form>@endif
@if($version->status->value==='approved' && (!$index || $index->status->value==='failed'))<form method="POST" action="{{ route('tenant.admin.ai-knowledge.versions.index',[$source,$version]) }}">@csrf<button class="z-btn" type="submit">Preparar conocimiento</button></form>@endif
</article>
@endforeach
@endsection
