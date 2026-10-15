{{-- BFCache can restore a pre-payment form without requesting fresh server state. --}}
@if($cotizacion->provider === 'tenant_provider_local')
<script>window.addEventListener('pageshow', function (event) { if (event.persisted) window.location.reload(); });</script>
@endif
