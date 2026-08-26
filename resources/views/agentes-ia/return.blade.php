@extends('layouts.ai-public')
@section('title','Estado de la solicitud · Agentes IA')
@section('content')<section class="card"><h1>Estado de tu solicitud</h1><p>Estado: <strong>{{ $application->status }}</strong></p><p class="muted">La activación ocurre después de confirmar el pago verificado.</p><a class="btn secondary" href="{{ route('agentes-ia.onboarding.summary',$application->public_token) }}">Ver resumen</a></section>@endsection
