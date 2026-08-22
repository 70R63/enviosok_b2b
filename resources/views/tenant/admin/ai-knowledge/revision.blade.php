@extends('tenant.admin.layout')
@section('title','Nueva revisión')
@section('content')
<h1>Nueva revisión de {{ $source->name }}</h1><form method="POST" action="{{ route('tenant.admin.ai-knowledge.revisions.store',$source) }}" class="card">@csrf<input type="hidden" name="type" value="{{ $source->type->value }}">@if($source->type->value==='manual_text')<label>Contenido<textarea name="body" rows="12" required>{{ old('body') }}</textarea></label>@else<label>Pregunta<input name="entries[0][question]" required value="{{ old('entries.0.question') }}"></label><label>Respuesta<textarea name="entries[0][answer]" required>{{ old('entries.0.answer') }}</textarea></label>@endif<button class="z-btn" type="submit">Crear revisión</button></form>
@endsection
