@extends('layouts.ai-public')
@section('title','Comenzar · Agentes IA')
@section('content')
<section class="card">
<h1>Comienza con Agentes IA</h1><p class="muted">Crea tu cuenta y elige el subdominio de acceso. No necesitas contratar una plataforma logística.</p>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('agentes-ia.start.store') }}">
@csrf
@if($product)<input type="hidden" name="offer" value="{{ $product->uuid }}"><p><strong>{{ $product->name }}</strong> · ${{ number_format((float)$product->price,2) }} {{ $product->currency }}</p>@endif
<div class="field"><label for="contact_name">Nombre</label><input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required maxlength="191"></div><div class="field"><label for="contact_last_name">Apellido</label><input id="contact_last_name" name="contact_last_name" value="{{ old('contact_last_name') }}" maxlength="191"></div><div class="field"><label for="contact_email">Correo</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" required maxlength="191"></div><div class="field"><label for="contact_phone">Teléfono</label><input id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" maxlength="40"></div><div class="field"><label for="company_name">Nombre de la empresa</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required maxlength="191"></div><div class="field"><label for="requested_subdomain">Subdominio de acceso</label><input id="requested_subdomain" name="requested_subdomain" value="{{ old('requested_subdomain') }}" placeholder="miempresa" required maxlength="63"></div><button class="btn" type="submit">Continuar al pago</button></form></section>
@endsection
