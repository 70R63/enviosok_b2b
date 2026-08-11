@extends('layouts.zigo-platform')
@section('title', 'ZIGO Platform | Tu plataforma de envíos')
@section('content')
<section class="hero">
    <div class="eyebrow">ZIGO Platform</div>
    <h1>Opera tu propia plataforma de envíos con ZIGO</h1>
    <p class="lead">Centraliza tus operaciones, capacidades y equipo en una plataforma preparada para crecer con tu empresa.</p>
    <div class="actions"><a class="btn btn-primary" href="{{ route('zigo-platform.pricing') }}">Ver precios</a><a class="btn btn-secondary" href="{{ route('zigo-platform.start') }}">Comenzar</a></div>
</section>
@endsection
