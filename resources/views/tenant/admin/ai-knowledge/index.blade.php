@extends('tenant.admin.layout')
@section('title','Conocimiento')
@section('content')
<div class="z-cluster z-cluster--between"><div><div class="eyebrow">AGENTES IA</div><h1>Conocimiento</h1><p>Administra información aprobada para tus agentes.</p></div><a class="z-btn" href="{{ route('tenant.admin.ai-knowledge.create') }}">Crear fuente</a></div>
<div class="grid">@forelse($sources as $source)<article class="card"><h2>{{ $source->name }}</h2><p>{{ $source->type->value==='faq'?'Preguntas frecuentes':'Fuente manual' }}</p><p>{{ $source->versions->count() }} versiones</p><a href="{{ route('tenant.admin.ai-knowledge.show',$source) }}">Revisar</a></article>@empty<article class="card"><p>Aún no tienes fuentes de conocimiento.</p></article>@endforelse</div>{{ $sources->links() }}
@endsection
