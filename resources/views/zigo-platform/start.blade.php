@extends('layouts.zigo-platform')
@section('title', 'Tu empresa | ZIGO Platform')
@section('content')
<div class="wizard"><div class="steps"><span class="step active"></span><span class="step"></span><span class="step"></span><span class="step"></span></div>
<div class="card"><div class="eyebrow">Paso 1 de 4</div><h1>Tu empresa</h1><p class="muted">Tu acceso administrativo se activará después de confirmar el pago.</p>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('zigo-platform.start.store') }}">@csrf
@if($offer)<input type="hidden" name="offer" value="{{ $offer->uuid }}"><p><strong>Oferta elegida:</strong> {{ $offer->name }}</p>@endif
<div class="field"><label for="contact_name">Nombre del responsable</label><input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required maxlength="191"></div>
<div class="field"><label for="contact_last_name">Apellido (opcional)</label><input id="contact_last_name" name="contact_last_name" value="{{ old('contact_last_name') }}" maxlength="191"></div>
<div class="field"><label for="contact_email">Correo empresarial</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" required maxlength="191" autocomplete="email"></div>
<div class="field"><label for="contact_phone">Teléfono</label><input id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" maxlength="40" autocomplete="tel"></div>
<div class="field"><label for="company_name">Nombre comercial</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required maxlength="191"></div>
<div class="field"><label for="company_legal_name">Razón social (opcional)</label><input id="company_legal_name" name="company_legal_name" value="{{ old('company_legal_name') }}" maxlength="191"></div>
<div class="field"><label for="tax_id">RFC / Tax ID (opcional)</label><input id="tax_id" name="tax_id" value="{{ old('tax_id') }}" maxlength="32"></div>
<button class="btn btn-primary" type="submit">Continuar</button></form></div></div>
@endsection
