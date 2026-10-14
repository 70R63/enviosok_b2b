<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Envío local</title>
<link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">
<style>body{font-family:Arial,sans-serif;background:#f3f4f6;color:#111827;padding:1rem}main{max-width:850px;margin:auto;background:white;padding:1.5rem;border-radius:16px}section{border:1px solid #dbe2ea;border-radius:12px;padding:1rem;margin:1rem 0}a{display:inline-block;min-height:44px;line-height:44px}p{overflow-wrap:anywhere}</style>
</head>
<body><main>
@if($shipment)
    <h1>{{ data_get($shipment->guide_snapshot, 'branding.brand_name') }} · Envío local</h1>
    <p>Tu envío fue confirmado. La recolección será realizada en la dirección origen.</p>
    <p>Folio / guía: {{ $shipment->tracking_number }}</p>
    <p>Operado por {{ $shipment->guide_snapshot['provider'] }}</p>
    <p>Servicio: {{ $shipment->guide_snapshot['service_name'] }}</p>
    <p>Total cliente: {{ number_format((float) $cotizacion->precio, 2) }} {{ $cotizacion->providerContract->snapshot->currency }}</p>
    <p>Recolección solicitada: {{ $shipment->created_at->format('d/m/Y H:i') }}</p>
    <p>{{ ['READY_FOR_PICKUP' => 'Listo para recolectar', 'PICKED_UP' => 'Recolectado', 'IN_TRANSIT' => 'En tránsito', 'OUT_FOR_DELIVERY' => 'En reparto', 'DELIVERED' => 'Entregado', 'DELIVERY_FAILED' => 'Entrega no realizada'][$shipment->status] ?? 'En preparación' }}</p>
    @foreach(['Recolección' => $shipment->sender_snapshot, 'Destino' => $shipment->recipient_snapshot] as $title => $party)
        <section><h2>{{ $title }}</h2>
        @foreach(\App\Support\Presentation\AddressPresenter::lines($party) as $line)<p>{{ $line }}</p>@endforeach
        <p>Teléfono: {{ $party['phone'] }}</p>
        @if(data_get($party, 'address.references'))<p>Referencias: {{ data_get($party, 'address.references') }}</p>@endif
        </section>
    @endforeach
    <a href="{{ isset($recoveryToken) ? route('guide-recovery.download', $recoveryToken) : route('b2c.provider-fulfillment.guide', $cotizacion->providerContract->uuid) }}">Descargar guía</a>
    <p>El seguimiento detallado estará disponible próximamente.</p>
@else
    <h1>Envío local</h1><p>La preparación del envío está pendiente. Si tu pago fue confirmado, no necesitas volver a pagar.</p>
@endif
</main></body></html>
