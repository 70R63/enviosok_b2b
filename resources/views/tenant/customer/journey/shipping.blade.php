@extends('tenant.layout')
@section('title','Datos del envío')
@php($snapshot=\App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot::where('tenant_id',$operation->tenant_id)->where('uuid',$operation->metadata['selected_quote_snapshot_uuid']??'')->first())
@section('content')
@include('tenant.customer.journey.steps',['currentStep'=>3])
<header class="z-page-header"><div><div class="z-eyebrow">DATOS DEL ENVÍO</div><h1>¿Quién envía y quién recibe?</h1><p class="z-muted">La ruta, el paquete, el servicio y el precio permanecen como fueron cotizados.</p></div></header>
@if($errors->any())<div class="z-alert z-alert--danger">{{ $errors->first() }}</div>@endif
<section class="z-card" style="margin-bottom:1rem"><div class="z-form-grid"><div><div class="z-eyebrow">ORIGEN</div><strong>{{ $snapshot?->origin['address'] }}</strong></div><div><div class="z-eyebrow">DESTINO</div><strong>{{ $snapshot?->destination['address'] }}</strong></div></div><div style="margin-top:1rem"><a href="/app/cotizar">Cambiar dirección y volver a cotizar</a></div></section>
<form method="POST" action="/app/envio/nuevo" class="z-stack">@csrf<div class="journey-people">
@foreach(['sender'=>['REMITENTE','origen'],'recipient'=>['DESTINATARIO','destino']] as $key=>$section)<section class="z-card z-form-section"><div class="z-form-section__head"><div class="z-eyebrow">{{ $section[0] }}</div><h2>Datos de {{ $section[1] }}</h2></div><div class="z-form-grid"><label class="z-field">Nombre<input name="{{ $key }}[name]" value="{{ old($key.'.name') }}" required></label><label class="z-field">Teléfono<input type="tel" name="{{ $key }}[phone]" value="{{ old($key.'.phone') }}" required></label></div><label class="z-field">Interior <span class="z-muted">(opcional)</span><input name="{{ $key }}[interior]" value="{{ old($key.'.interior') }}"></label><label class="z-field">Referencias<textarea name="{{ $key }}[references]">{{ old($key.'.references') }}</textarea></label></section>@endforeach</div>
<section class="z-card"><label class="z-field">Referencia de tu envío<input name="reference" value="{{ old('reference') }}" maxlength="100"></label></section><div class="z-form-actions"><button class="z-btn">Continuar al resumen</button></div></form>
@endsection
