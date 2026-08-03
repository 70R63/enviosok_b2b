@extends('devops.layout')
@section('title', 'ZIGO DevOps')
@section('content')
<div class="title">Centro de despliegues</div>
<div class="subtitle">Registro, validación y ejecución controlada hacia Stage y PRD.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="summary-grid">
@foreach(['stage' => 'Stage', 'production' => 'PRD'] as $key => $label)
    @php($deployment = $environments->get($key))
    <div class="summary-card">
        <span>{{ $label }}</span>
        <strong style="font-size:20px;">{{ $deployment?->status ?? 'Sin despliegues' }}</strong>
        @if($deployment)<div class="muted">{{ $deployment->commit_hash ?: 'Sin commit' }} · {{ $deployment->created_at->format('d/m/Y H:i') }}</div>@endif
    </div>
@endforeach
</div>
<section class="card">
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
        <div><h2 style="margin:0 0 8px;">Último despliegue</h2><div class="muted">{{ $latest ? $latest->package_name . ' · ' . $latest->environment . ' · ' . $latest->status : 'Aún no hay paquetes registrados.' }}</div></div>
        @if($canDeploy)<a class="btn" href="{{ url('/deployments/create') }}">Nuevo paquete</a>@endif
    </div>
</section>
@endsection
