@extends('layouts.zigo-platform')
@section('title', 'Tu solución | ZIGO Platform')
@section('content')
<div class="wizard"><div class="steps"><span class="step active"></span><span class="step active"></span><span class="step"></span><span class="step"></span></div>
<div class="card"><div class="eyebrow">Paso 2 de 4</div><h1>Tu solución</h1>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('zigo-platform.onboarding.solution.store',$application->public_token) }}">@csrf @method('PATCH')
<div class="label">Plan y periodicidad</div>
@forelse($offers as $offer)<label class="choice"><input type="radio" name="plan_offer" value="{{ $offer->uuid }}" data-period="{{ strtolower($offer->billing_type) }}" {{ old('plan_offer',$initialOffer)===$offer->uuid?'checked':'' }} required><strong>{{ $offer->name }}</strong> — {{ $offer->billing_type==='ANNUAL'?'Anual':'Mensual' }} — ${{ number_format((float)$offer->price,2) }} {{ $offer->currency }}</label>@empty<p>No hay planes disponibles.</p>@endforelse
<div class="field"><label for="billing_period">Periodicidad</label><select id="billing_period" name="billing_period"><option value="monthly">Mensual</option><option value="annual">Anual</option></select></div>
@if($moduleOffers->isNotEmpty())<div class="label">Módulos opcionales</div>@foreach($moduleOffers as $offer)<label class="choice"><input type="checkbox" name="module_offers[]" value="{{ $offer->uuid }}">{{ $offer->module->name }} — {{ $offer->billing_type==='ANNUAL'?'Anual':'Mensual' }} — ${{ number_format((float)$offer->price,2) }}</label>@endforeach @endif
@if($operationOffers->isNotEmpty())<div class="field"><label for="operations_offer">Volumen adicional</label><select id="operations_offer" name="operations_offer"><option value="">Sin paquete adicional</option>@foreach($operationOffers as $offer)<option value="{{ $offer->uuid }}">{{ number_format($offer->included_operations) }} operaciones — ${{ number_format((float)$offer->price,2) }}</option>@endforeach</select></div>@endif
<button class="btn btn-primary" type="submit">Continuar</button></form></div></div>
@endsection
