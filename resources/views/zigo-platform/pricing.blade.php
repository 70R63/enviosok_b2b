@extends('layouts.zigo-platform')
@section('title', 'Precios | ZIGO Platform')
@section('content')
<div class="eyebrow">Precios claros</div><h1>Elige una oferta para tu operación</h1>
<p class="lead">Todos los importes provienen del catálogo comercial vigente.</p>
@if($offers->isEmpty())
    <div class="card"><strong>No hay ofertas públicas disponibles en este momento.</strong></div>
@else
<div class="grid">
@foreach($offers as $offer)
    <article class="card">
        <div class="muted">{{ $offer->billing_type === 'ANNUAL' ? 'Anual' : 'Mensual' }}</div>
        <h2>{{ $offer->name }}</h2><p>{{ $offer->description }}</p>
        <div class="price">${{ number_format((float)$offer->price, 2) }} {{ $offer->currency }}</div>
        @if($offer->plan?->included_operations)<p>{{ number_format($offer->plan->included_operations) }} operaciones incluidas</p>@endif
        <a class="btn btn-primary" href="{{ route('zigo-platform.start', ['offer'=>$offer->uuid]) }}">Comenzar</a>
    </article>
@endforeach
</div>
@endif
@endsection
