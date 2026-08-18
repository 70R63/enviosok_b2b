@include('tenant.tracking._styles')
@php
$trackingLabels=\App\Support\Presentation\ShipmentStatusPresenter::LABELS;
$trackingSteps=['CREATED','READY_FOR_PICKUP','PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED'];
$trackingCurrent=array_search($trackingStatus,$trackingSteps,true);
@endphp
<section class="z-panel tracking-progress"><div class="z-eyebrow">PROGRESO</div><ol class="tracking-steps">@foreach($trackingSteps as $index=>$step)<li class="{{ $trackingCurrent!==false && $index<=$trackingCurrent ? 'is-done' : '' }}">{{ $trackingLabels[$step] }}</li>@endforeach</ol></section>
