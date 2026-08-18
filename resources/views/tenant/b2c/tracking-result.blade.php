@extends('tenant.layout')
@php
$labels=['CREATED'=>'Envío creado','READY_FOR_PICKUP'=>'Recolección solicitada','PICKED_UP'=>'Recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERED'=>'Entregado','DELIVERY_FAILED'=>'No entregado','CANCELED'=>'Cancelado'];
$steps=['CREATED','READY_FOR_PICKUP','PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED'];
$current=array_search($trackingResult['status'],$steps,true);
$events=collect($trackingResult['events'])->sortByDesc('occurred_at');
$last=$events->first();
$exception=in_array($trackingResult['status'],['DELIVERY_FAILED','CANCELED'],true);
@endphp
@section('title','Rastreo '.$trackingResult['tracking_number'])
@push('head')<style>
.tracking-shell{max-width:960px;margin:.75rem auto}.tracking-head{display:flex;justify-content:space-between;align-items:end;gap:1rem;margin:1rem 0}.tracking-head h1{margin:.15rem 0 .35rem}.tracking-meta{display:flex;align-items:center;flex-wrap:wrap;gap:.6rem}.tracking-progress{padding:1rem;margin-bottom:1rem;overflow:hidden}.tracking-steps{display:grid;grid-template-columns:repeat(6,1fr);list-style:none;padding:0;margin:.75rem 0 0}.tracking-steps li{position:relative;padding:1.15rem .35rem 0 0;color:var(--z-muted);font-size:.74rem;font-weight:700}.tracking-steps li:before{content:'';position:absolute;z-index:1;top:0;left:0;width:10px;height:10px;border:3px solid #fff;border-radius:50%;background:var(--z-border);box-sizing:content-box}.tracking-steps li:after{content:'';position:absolute;top:6px;left:10px;right:0;height:2px;background:var(--z-border)}.tracking-steps li:last-child:after{display:none}.tracking-steps li.is-done:before,.tracking-steps li.is-done:after{background:var(--z-primary)}.tracking-history{padding:1.1rem}.tracking-history h2{margin-top:0}.tracking-actions{margin-top:1rem}@media(max-width:767px){.tracking-head{display:grid;align-items:start}.tracking-steps{display:flex;flex-direction:column;margin-left:.2rem}.tracking-steps li{padding:0 0 1rem 1.5rem}.tracking-steps li:after{top:10px;bottom:-2px;left:7px;width:2px;height:auto}.tracking-shell,.tracking-shell>*{min-width:0}}
</style>@endpush
@section('content')
<div class="tracking-shell"><header class="tracking-head"><div><div class="z-eyebrow">RASTREO DE ENVÍO</div><h1>{{ $trackingResult['tracking_number'] }}</h1><div class="tracking-meta"><span class="z-badge @if($trackingResult['status']==='DELIVERED') z-badge--success @elseif($exception) z-badge--danger @endif">{{ $labels[$trackingResult['status']] ?? 'Actualización de envío' }}</span><span class="z-muted">Última actualización: {{ optional($last['occurred_at'] ?? null)->format('d/m/Y H:i') ?: 'Sin movimientos' }}</span></div></div></header>
@if($exception)<div class="z-alert z-alert--danger"><strong>{{ $trackingResult['status']==='CANCELED'?'Envío cancelado':'No fue posible completar la entrega' }}</strong></div>@endif
<section class="z-panel tracking-progress"><div class="z-eyebrow">PROGRESO</div><ol class="tracking-steps">@foreach($steps as $index=>$status)<li class="{{ $current!==false && $index<=$current ? 'is-done' : '' }}">{{ $labels[$status] }}</li>@endforeach</ol></section>
<section class="z-panel tracking-history"><h2>Historial</h2>@if($events->isEmpty())<div class="z-empty"><p>Aún no hay movimientos disponibles.</p></div>@else<ol class="z-timeline">@foreach($events as $event)<li><strong>{{ $labels[$event['status']] ?? 'Actualización de envío' }}</strong>@if($event['status']==='DELIVERY_FAILED')<div>Intento de entrega no completado</div>@endif<div class="z-muted">{{ optional($event['occurred_at'])->format('d/m/Y H:i') }}</div></li>@endforeach</ol>@endif</section>
<div class="tracking-actions"><a class="z-btn z-btn--outline" href="/rastreo"><x-zigo.icon name="search" />Consultar otra guía</a></div></div>
@endsection
