@include('b2c.pago-estado', [
    'titulo' => $cotizacion->payment_status === 'approved' ? 'Pago confirmado' : 'Pago pendiente',
    'mensaje' => $cotizacion->payment_status === 'approved'
        ? 'Tu pago fue confirmado. La guía puede recuperarse sin realizar otro cobro.'
        : 'Estamos esperando la confirmación del pago.',
    'color' => $cotizacion->payment_status === 'approved' ? '#3ca344' : '#f59e0b',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => false
])
