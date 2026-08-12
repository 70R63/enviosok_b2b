@extends('layouts.zigo-platform')
@section('title', 'Tu solución | ZIGO Platform')
@section('content')
<div class="wizard"><div class="steps"><span class="step active"></span><span class="step active"></span><span class="step"></span><span class="step"></span></div>
<div class="card"><div class="eyebrow">Paso 2 de 4</div><h1>Tu plan</h1>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
@if(!$offer)
<div class="empty-state"><h2>Estamos preparando nuestras opciones comerciales</h2><p class="muted">Aún no hay planes disponibles para continuar esta solicitud.</p><a class="btn btn-secondary" href="{{ route('zigo-platform.pricing') }}">Volver a precios</a></div>
@else
<form method="post" action="{{ route('zigo-platform.onboarding.solution.store',$application->public_token) }}">@csrf @method('PATCH')
<article class="choice" style="margin:24px 0"><div class="muted">{{ $offer->billing_type==='ANNUAL'?'Plan anual':'Plan mensual' }}</div><h2>{{ $offer->name }}</h2><p>{{ $offer->description }}</p><div class="price">${{ number_format((float)$offer->price,2) }} {{ $offer->currency }}</div>
@if($offer->plan->included_operations)<p><strong>{{ $offer->metadata['allowance_label'] ?? ('Hasta '.number_format($offer->plan->included_operations).' envíos incluidos por periodo') }}</strong></p>@endif
@if($offer->plan->modules->isNotEmpty())<ul class="feature-list">@foreach($offer->plan->modules->where('pivot.is_included',true) as $module)<li>{{ $module->code==='API'?'Integraciones con tus sistemas':($module->code==='DRIVER'?'App para conductores':$module->name) }}</li>@endforeach</ul>@endif
</article>
<div class="actions"><a class="btn btn-secondary" href="{{ route('zigo-platform.pricing') }}">Cambiar plan</a><button class="btn btn-primary" type="submit">Continuar</button></div></form>
@endif
</div></div>
@endsection
