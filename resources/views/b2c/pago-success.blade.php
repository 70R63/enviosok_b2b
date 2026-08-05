@include('b2c.pago-estado', [
    'titulo' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Pago con saldo confirmado'
    : 'Pago confirmado',
    'mensaje' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Tu pago fue cubierto con saldo prepago. Tu guía está siendo generada.'
    : 'Tu pago fue registrado correctamente. Tu guía está siendo generada.',
    'color' => '#3ca344',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => true
])
