@php
$navigation=\App\Support\Presentation\DriverAddressPresenter::forShipment($shipment);
$statusLabels=['READY_FOR_PICKUP'=>'Listo para recolectar','PICKED_UP'=>'Recolectado','IN_TRANSIT'=>'En tránsito','OUT_FOR_DELIVERY'=>'En reparto','DELIVERY_FAILED'=>'Intento fallido','DELIVERED'=>'Entregado'];
$encoded=rawurlencode($navigation['full']);
@endphp
<article class="z-card driver-delivery driver-assignment-card z-stack">
<div class="driver-status-line"><div><span class="z-badge">{{ $statusLabels[$shipment->status]??str_replace('_',' ',$shipment->status) }}</span><div class="driver-delivery__tracking">{{ $shipment->tracking_number }}</div></div><strong>{{ $tenant->branding?->brand_name??$tenant->name }}</strong></div>
<div class="driver-assignment-card__address"><div class="z-eyebrow">{{ $navigation['pickup']?'RECOLECCIÓN':'DESTINO' }}</div><strong>{{ $navigation['settlement']?:'Dirección disponible en el detalle' }}</strong><div>CP {{ $navigation['postal_code']?:'—' }}</div><div class="z-muted">{{ implode(', ',array_filter([$navigation['municipality'],$navigation['state']])) }}</div></div>
<div class="driver-assignment-card__actions">@if($navigation['full']!=='')<details class="driver-navigation"><summary class="z-btn">Navegar</summary><div class="driver-navigation__menu"><a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $encoded }}">Google Maps</a><a target="_blank" rel="noopener noreferrer" href="https://waze.com/ul?q={{ $encoded }}&amp;navigate=yes">Waze</a><a target="_blank" rel="noopener noreferrer" href="https://maps.apple.com/?daddr={{ $encoded }}">Apple Maps</a></div></details>@endif<a class="z-btn z-btn--outline" href="{{ route('driver.shipments.show',$shipment->uuid) }}">Ver detalle</a></div>
</article>
