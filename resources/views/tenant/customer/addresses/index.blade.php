@extends('tenant.layout')
@section('title','Mis direcciones')
@section('content')
<header class="z-page-header"><div><div class="z-eyebrow">MI CUENTA</div><h1>Mis direcciones</h1><p class="z-muted">Guarda domicilios frecuentes para completar tus envíos más rápido.</p></div><a class="z-btn" href="{{ route('tenant.customer.app.addresses.create',[],false) }}">Nueva dirección</a></header>
<div class="z-data-list">
@forelse($addresses as $address)
<article class="z-data-row"><div><span class="z-data-row__label">Alias</span><strong>{{ $address->alias }}</strong><div class="z-small z-muted">{{ $address->contact_name }} · {{ $address->phone }}</div></div><div><span class="z-data-row__label">Dirección</span>{{ $address->street }} {{ $address->exterior }}@if($address->interior), Int. {{ $address->interior }}@endif<div class="z-small z-muted">{{ $address->settlement }}, {{ $address->municipality }}, {{ $address->state }} · CP {{ $address->postal_code }}</div></div><div><span class="z-data-row__label">Uso</span><span class="z-badge">{{ ['origin'=>'Origen','destination'=>'Destino','both'=>'Origen y destino'][$address->address_type] }}</span><div class="z-cluster" style="margin-top:.35rem">@if($address->is_default_origin)<span class="z-badge z-badge--success">Origen predeterminado</span>@endif @if($address->is_default_destination)<span class="z-badge z-badge--success">Destino predeterminado</span>@endif</div></div><div class="z-actions"><a class="z-btn z-btn--outline" href="{{ route('tenant.customer.app.addresses.edit',$address, false) }}">Editar</a><form method="POST" action="{{ route('tenant.customer.app.addresses.destroy',$address, false) }}">@csrf @method('DELETE')<button class="z-btn z-btn--ghost" type="submit">Eliminar</button></form></div></article>
@empty
<div class="z-empty"><h3>Aún no tienes direcciones</h3><p>Agrega una dirección para usarla en tus próximos envíos.</p><a class="z-btn" href="{{ route('tenant.customer.app.addresses.create',[],false) }}">Agregar dirección</a></div>
@endforelse
</div>
@endsection
