<nav aria-label="Navegación de envíos">
    @if(auth()->check() && !isset($recoveryToken))
        <a href="{{ route('b2c.dashboard') }}">Inicio</a>
        <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
        <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
    @endif
    <a href="{{ route('b2c.rastreo', $shipment ? ['tracking_number' => $shipment->tracking_number] : []) }}">Rastrear</a>
    @if($shipment)
        <a href="{{ isset($recoveryToken) ? route('guide-recovery.download', $recoveryToken) : route('b2c.provider-fulfillment.guide', $cotizacion->providerContract->uuid) }}">Descargar guía</a>
    @endif
</nav>
