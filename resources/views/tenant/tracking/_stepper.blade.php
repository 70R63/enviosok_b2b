@include('tenant.tracking._styles')
@php
$trackingLabels=$trackingLabels??['CREATED'=>'Envío creado','READY_FOR_PICKUP'=>'Recolección solicitada','PICKED_UP'=>'Recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERED'=>'Entregado'];
$trackingSteps=['CREATED','READY_FOR_PICKUP','PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED'];
$trackingCurrent=array_search($trackingStatus,$trackingSteps,true);
@endphp
<section class="z-panel tracking-progress"><div class="z-eyebrow">PROGRESO</div><ol class="tracking-steps">@foreach($trackingSteps as $index=>$step)<li class="{{ $trackingCurrent!==false && $index<=$trackingCurrent ? 'is-done' : '' }}">{{ $trackingLabels[$step] }}</li>@endforeach</ol></section>
