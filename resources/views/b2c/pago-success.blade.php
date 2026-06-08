@include('b2c.pago-estado', [
    'titulo' => 'Pago confirmado',
    'mensaje' => 'Tu pago fue registrado correctamente. Ahora puedes generar tu guía.',
    'color' => '#3ca344',
    'cotizacion' => $cotizacion,
    'mostrarGuia' => true
])