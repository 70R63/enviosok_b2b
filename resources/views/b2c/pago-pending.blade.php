@include('b2c.pago-estado', [
    'titulo' => 'Pago pendiente',
    'mensaje' => 'Estamos esperando la confirmación del pago.',
    'color' => '#f59e0b',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => false
])