@extends('tenant.admin.layout')
@section('title','Mis compras')
@section('content')
<header class="z-page-header"><div><div class="z-eyebrow">CUENTA COMERCIAL</div><h1>Mis compras</h1><p class="z-muted">Consulta planes, módulos y capacidad adicional contratada con ZIGO.</p></div><a class="z-btn z-btn--outline" href="{{ route('tenant.admin.billing.index',[],false) }}">Facturación</a></header>
<section class="z-card">
@if($orders->isEmpty())
<div class="z-empty"><div class="z-empty__icon"><x-zigo.icon name="marketplace" /></div><h2>Sin compras todavía</h2><p>Explora Servicios ZIGO para contratar un plan, módulo o pack operativo.</p><a class="z-btn" href="{{ route('tenant.admin.marketplace',[],false) }}">Explorar servicios</a></div>
@else
<div class="z-table-wrap"><table class="z-table"><thead><tr><th>Fecha</th><th>Producto</th><th>Importe</th><th>Estado</th><th></th></tr></thead><tbody>
@foreach($orders as $order)
@php($snapshot = is_array($order->purchase_snapshot) ? $order->purchase_snapshot : [])
<tr><td>{{ $order->created_at->format('d/m/Y') }}</td><td><strong>{{ data_get($snapshot,'name') ?: ($order->product?->name ?: 'Producto ZIGO') }}</strong>@if(data_get($snapshot,'description'))<small class="z-muted">{{ data_get($snapshot,'description') }}</small>@endif</td><td>${{ number_format((float)$order->total_amount,2) }} {{ $order->currency }}</td><td><span class="z-badge">{{ $order->status }}</span></td><td><a href="{{ route('tenant.admin.saas.payment',$order->uuid,false) }}">Ver detalle</a></td></tr>
@endforeach
</tbody></table></div>{{ $orders->links() }}
@endif
</section>
@endsection
