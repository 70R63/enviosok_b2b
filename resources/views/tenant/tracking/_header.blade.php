@include('tenant.tracking._styles')
@php
$trackingLabels=['CREATED'=>'Envío creado','READY_FOR_PICKUP'=>'Recolección solicitada','PICKED_UP'=>'Recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERED'=>'Entregado','DELIVERY_FAILED'=>'No entregado','CANCELED'=>'Cancelado'];
$trackingLatest=collect($trackingEvents)->sortByDesc('occurred_at')->first();
$trackingException=in_array($trackingStatus,['DELIVERY_FAILED','CANCELED'],true);
@endphp
<header class="tracking-head"><div><div class="z-eyebrow">{{ $trackingEyebrow }}</div><h1>{{ $trackingNumber }}</h1><div class="tracking-meta"><span class="z-badge @if($trackingStatus==='DELIVERED') z-badge--success @elseif($trackingException) z-badge--danger @endif">{{ $trackingLabels[$trackingStatus] ?? 'Actualización de envío' }}</span><span class="z-muted">Última actualización: {{ optional(data_get($trackingLatest,'occurred_at'))->format('d/m/Y H:i') ?: optional($trackingUpdatedAt ?? null)->format('d/m/Y H:i') ?: 'Sin movimientos' }}</span></div></div>@isset($trackingActions)<div class="tracking-actions">{!! $trackingActions !!}</div>@endisset</header>
@if($trackingException)<div class="z-alert z-alert--danger"><strong>{{ $trackingStatus==='CANCELED'?'Envío cancelado':'No fue posible completar la entrega' }}</strong></div>@endif
