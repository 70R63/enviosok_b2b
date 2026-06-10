@include('b2c.pago-estado', [
    'titulo' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Pago con saldo confirmado'
    : 'Pago confirmado',
    'mensaje' => $cotizacion->payment_status === 'saldo_prepago'
    ? 'Tu pago fue cubierto con saldo prepago. Ahora puedes generar tu guía.'
    : 'Tu pago fue registrado correctamente. Ahora puedes generar tu guía.',
    'color' => '#3ca344',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => true
])