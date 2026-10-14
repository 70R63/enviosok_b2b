@if($cotizacion->provider === 'tenant_provider_local')
    @include('b2c.provider-fulfillment', ['shipment' => \App\Domain\Network\Providers\Models\ProviderFulfillment::where('provider_contract_id', $cotizacion->providerContract?->id)->first()?->shipment])
@else
@include('b2c.pago-estado', [
    'titulo' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Pago con saldo confirmado'
    : 'Pago confirmado',
    'mensaje' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Tu pago fue cubierto con saldo prepago. La generación de tu guía será procesada.'
    : 'Tu pago fue registrado correctamente. La generación de tu guía será procesada.',
    'color' => '#3ca344',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => true
])
@endif
