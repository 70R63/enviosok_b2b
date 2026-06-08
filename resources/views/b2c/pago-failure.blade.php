@include('b2c.pago-estado', [
    'titulo' => 'Pago rechazado',
    'mensaje' => 'No fue posible procesar el pago.',
    'color' => '#dc2626',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => false
])