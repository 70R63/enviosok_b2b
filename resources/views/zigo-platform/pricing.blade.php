@extends('layouts.zigo-platform')
@section('title', 'Precios | ZIGO Platform')
@section('content')
<div class="eyebrow">Planes ZIGO Platform</div><h1>Elige una oferta para tu operación</h1>
<p class="lead">Compara capacidades. Todos los importes provienen del catálogo comercial vigente.</p>
@if($offers->isEmpty())
    <div class="card empty-state"><h2>Estamos preparando nuestras opciones comerciales</h2><p class="muted">Podemos ayudarte a identificar la solución adecuada para tu operación.</p><a class="btn btn-primary" href="{{ route('landing.empresas') }}">Hablar con un asesor</a></div>
@else
<div class="billing-toggle" role="group" aria-label="Periodicidad"><button type="button" class="active" data-billing="MONTHLY">Mensual</button><button type="button" data-billing="ANNUAL">Anual</button></div>
<div class="pricing-grid">
@foreach($offers as $offer)
    <article class="card pricing-card" data-offer-period="{{ $offer->billing_type }}" @if($offer->billing_type==='ANNUAL') hidden @endif>
        <div class="muted">{{ $offer->billing_type === 'ANNUAL' ? 'Anual' : 'Mensual' }}</div>
        <h2>{{ $offer->name }}</h2><p>{{ $offer->description }}</p>
        <div class="price">${{ number_format((float)$offer->price, 2) }} {{ $offer->currency }}</div>
        @if($offer->plan?->included_operations)<p><strong>{{ $offer->metadata['allowance_label'] ?? ('Hasta '.number_format($offer->plan->included_operations).' envíos incluidos por periodo') }}</strong></p>@endif
        @if($offer->plan?->modules?->isNotEmpty())<ul class="feature-list">@foreach(\App\Support\Network\ModulePresentation::labels($offer->plan->modules->where('pivot.is_included', true)) as $label)<li>{{ $label }}</li>@endforeach</ul>@endif
        <a class="btn btn-primary" href="{{ route('zigo-platform.start', ['offer'=>$offer->uuid]) }}">Comenzar</a>
    </article>
@endforeach
</div>
<script>document.querySelectorAll('[data-billing]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-billing]').forEach(item=>item.classList.toggle('active',item===button));document.querySelectorAll('[data-offer-period]').forEach(card=>card.hidden=card.dataset.offerPeriod!==button.dataset.billing);}));</script>
@endif
@endsection
