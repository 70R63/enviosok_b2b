@extends('layouts.zigo-platform')
@section('title', 'ZIGO Platform | Tu plataforma de envíos')
@section('content')
<section class="hero">
    <div class="hero-copy"><div class="eyebrow">Tu negocio, tu marca, tu plataforma</div>
        <h1>Opera tu propia plataforma de envíos con ZIGO</h1>
        <p class="lead">Cotizaciones, clientes, envíos, tracking e integraciones desde una operación centralizada y preparada para crecer.</p>
        <div class="actions"><a class="btn btn-primary" href="{{ route('zigo-platform.start') }}">Comenzar</a><a class="btn btn-secondary" href="{{ route('zigo-platform.pricing') }}">Ver precios</a></div>
    </div>
    <div class="hero-visual" aria-label="Vista conceptual de una plataforma logística"><span class="visual-bar"></span><span class="visual-bar"></span><span class="visual-bar"></span><div class="visual-grid"><span class="visual-tile"></span><span class="visual-tile"></span><span class="visual-tile"></span><span class="visual-tile"></span></div></div>
</section>
<section class="section"><div class="section-heading"><div class="eyebrow">Capacidades</div><h2>Todo lo necesario para operar bajo tu marca</h2><p class="lead">Elige las capacidades que acompañan a tu modelo operativo.</p></div>
<div class="capability-grid">@foreach(['Portal white-label'=>'Una experiencia propia para tus clientes.','Cotizaciones'=>'Tarifas y opciones en un solo flujo.','Clientes'=>'Perfiles y operación aislados por tenant.','Envíos'=>'Control del ciclo de cada envío.','Tracking'=>'Seguimiento claro para tu operación.','CRM'=>'Información comercial centralizada.','Envíos locales'=>'Capacidades para última milla.','App para conductores'=>'Operación coordinada con conductores.','Soporte'=>'Atención integrada a tus procesos.','Integraciones con tus sistemas'=>'Conecta herramientas y procesos para escalar.'] as $name=>$copy)<article class="capability"><strong>{{ $name }}</strong><span class="muted">{{ $copy }}</span></article>@endforeach</div></section>
<section class="section"><div class="section-heading"><div class="eyebrow">Tu recorrido</div><h2>De la elección al lanzamiento</h2></div><div class="journey">@foreach(['Elige tu solución','Configura tu operación','Conecta e integra','Lanza tu plataforma','Escala tu negocio'] as $step)<div class="journey-step"><strong>{{ $step }}</strong></div>@endforeach</div></section>
<section class="section"><div class="cta-band"><div><div class="eyebrow" style="color:#a9c2ff">Planes ZIGO Platform</div><h2>Encuentra la opción para tu operación</h2><p class="muted">Compara capacidades y comienza tu solicitud empresarial.</p></div><div class="actions"><a class="btn btn-primary" href="{{ route('zigo-platform.pricing') }}">Ver planes</a><a class="btn btn-secondary" href="{{ route('zigo-platform.start') }}">Comenzar</a></div></div></section>
@endsection
