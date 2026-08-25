@extends('tenant.admin.layout')
@section('title','Mis compras')
@section('content')
<header class="z-page-header"><div><div class="z-eyebrow">CUENTA COMERCIAL</div><h1>Mis compras</h1><p class="z-muted">Consulta tus productos y ampliaciones contratadas.</p></div><a class="z-btn z-btn--outline" href="{{ route('tenant.admin.billing.index',[],false) }}">Facturación</a></header>
<section class="z-card">
@if($orders->isEmpty())
<div class="z-empty"><div class="z-empty__icon"><x-zigo.icon name="marketplace" /></div><h2>Sin compras todavía</h2><p>Explora el Marketplace para contratar un producto o ampliar tu capacidad.</p><a class="z-btn" href="{{ route('tenant.admin.marketplace',[],false) }}">Explorar Marketplace</a></div>
@else
<div class="z-table-wrap"><table class="z-table"><thead><tr><th>Fecha</th><th>Producto</th><th>Periodicidad</th><th>Importe</th><th>Estado</th><th>Resultado</th><th></th></tr></thead><tbody>
@foreach($orders as $order)
@php($snapshot = is_array($order->purchase_snapshot) ? $order->purchase_snapshot : [])
<tr><td>{{ $order->created_at->format('d/m/Y') }}</td><td><strong>{{ data_get($snapshot,'name') ?: ($order->product?->name ?: 'Producto') }}</strong>@if(data_get($snapshot,'description'))<small class="z-muted">{{ data_get($snapshot,'description') }}</small>@endif</td><td>{{ data_get($snapshot,'billing_type') === 'ANNUAL' ? 'Anual' : (data_get($snapshot,'billing_type') === 'MONTHLY' ? 'Mensual' : 'Pago único') }}</td><td>${{ number_format((float)$order->total_amount,2) }} {{ $order->currency }}</td><td><span class="z-badge">{{ $order->status }}</span></td><td>{{ $order->payment_status }}</td><td><a href="{{ route('tenant.admin.saas.payment',$order->uuid,false) }}">Ver detalle</a></td></tr>
@endforeach
</tbody></table></div>{{ $orders->links() }}
@endif
</section>
@endsection
