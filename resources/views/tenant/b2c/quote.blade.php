@extends('tenant.layout')
@section('title','Cotizar')
@section('content')
<section class="hero"><div class="eyebrow">COTIZACIÓN</div><h1>Envía con {{ $tenant->branding?->brand_name ?? $tenant->name }}</h1><p>Conoce opciones y precio final para tu envío.</p>
@if($errors->any())<p class="notice quote-error" role="alert">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('tenant.b2c.quote.store') }}" class="quote-form">@csrf
<label>CP origen<input name="cp_origen" value="{{ old('cp_origen','64000') }}" maxlength="5" required></label>
<label>CP destino<input name="cp_destino" value="{{ old('cp_destino','64000') }}" maxlength="5" required></label>
<label>Tipo de envío<select name="tipo_envio"><option value="sobre" @selected(old('tipo_envio','sobre')==='sobre')>Sobre</option><option value="caja" @selected(old('tipo_envio')==='caja')>Caja</option></select></label>
<label>Peso (kg)<input type="number" step="0.01" min="0.1" name="peso" value="{{ old('peso',1) }}" required></label>
<label>Largo (cm)<input type="number" name="length" value="{{ old('length') }}"></label><label>Ancho (cm)<input type="number" name="width" value="{{ old('width') }}"></label><label>Alto (cm)<input type="number" name="height" value="{{ old('height') }}"></label>
<button>Cotizar envío</button></form></section>
@if($result)<section class="results"><h2>Opciones disponibles</h2>@forelse($result['options'] as $option)<article class="quote-option"><div><strong>{{ $option['service'] }}</strong><small>{{ $option['carrier'] }} @if($option['delivery'])· {{ $option['delivery'] }}@endif</small></div><b>${{ number_format($option['price'],2) }} MXN</b></article>@empty<p>No hay opciones disponibles.</p>@endforelse</section>@endif
@endsection
<style>.quote-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:22px}.quote-form label{font-size:12px;font-weight:800}.quote-form input,.quote-form select{display:block;width:100%;margin-top:5px;padding:10px;border:1px solid #ccd7e3;border-radius:8px}.quote-form button{align-self:end;padding:11px;border:0;border-radius:8px;background:var(--tenant-primary);color:#fff;font-weight:900}.results{margin-top:22px}.quote-option{display:flex;justify-content:space-between;gap:16px;padding:18px;margin:10px 0;background:#fff;border:1px solid #dbe4ee;border-radius:13px}.quote-option small{display:block;color:#64748b;margin-top:5px}.quote-option b{color:var(--tenant-primary);font-size:20px}</style>
