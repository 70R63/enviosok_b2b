<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use Illuminate\Http\Request;
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;

class CotizacionPublicaController extends Controller
{
    public function cotizar(Request $request)
{
    $data = $request->validate([
        'cp_origen' => ['required', 'string', 'max:120'],
        'colonia_origen' => ['nullable', 'string', 'max:255'],
        'cp_destino' => ['required', 'string', 'max:120'],
        'colonia_destino' => ['nullable', 'string', 'max:255'],
        'tipo_envio' => ['required', 'in:caja,sobre'],
        'peso' => ['required', 'numeric', 'min:0.1'],
        'medidas' => ['nullable', 'string', 'max:50'],
    ]);

    $cotizacion = B2cCotizacion::create([
        'cp_origen' => $data['cp_origen'],
        'colonia_origen' => $data['colonia_origen'] ?? null,
        'cp_destino' => $data['cp_destino'],
        'colonia_destino' => $data['colonia_destino'] ?? null,
        'tipo_envio' => $data['tipo_envio'],
        'peso' => $data['peso'],
        'medidas' => $data['medidas'] ?? null,
        'estatus' => 'COTIZADA',
    ]);

    $opciones = [
        [
            'logistico' => 'FedEx',
            'logo' => 'img/fedex.png',
            'servicio' => 'Día siguiente',
            'entrega' => '1 a 2 días hábiles',
            'precio' => 419.50,
        ],
        [
            'logistico' => 'Estafeta',
            'logo' => 'img/estafeta.png',
            'servicio' => 'Terrestre',
            'entrega' => '2 a 5 días hábiles',
            'precio' => 395.00,
        ],
        [
            'logistico' => 'DHL',
            'logo' => 'img/dhl.png',
            'servicio' => 'Express',
            'entrega' => '1 a 3 días hábiles',
            'precio' => 445.00,
        ],
    ];

    return view('b2c.resultados', [
        'cotizacion' => $cotizacion,
        'opciones' => $opciones,
    ]);
}

public function seleccionar(Request $request, B2cCotizacion $cotizacion)
{
    $data = $request->validate([
        'logistico' => ['required', 'string', 'max:100'],
        'servicio' => ['required', 'string', 'max:100'],
        'precio' => ['required', 'numeric', 'min:1'],
    ]);

    $cotizacion->update([
        'logistico' => $data['logistico'],
        'servicio' => $data['servicio'],
        'precio' => $data['precio'],
        'estatus' => 'SELECCIONADA',
    ]);

    return redirect()
        ->route('b2c.checkout', $cotizacion->id);
}

public function checkout(B2cCotizacion $cotizacion)
{
    return view('b2c.checkout', compact('cotizacion'));
}

public function procesarCheckout(Request $request, B2cCotizacion $cotizacion)
{
    $data = $request->validate([
        'remitente_nombre' => ['required', 'string', 'max:255'],
        'remitente_telefono' => ['required', 'string', 'max:30'],
        'remitente_email' => ['required', 'email', 'max:255'],
        'remitente_direccion' => ['required', 'string', 'max:255'],

        'destinatario_nombre' => ['required', 'string', 'max:255'],
        'destinatario_telefono' => ['required', 'string', 'max:30'],
        'destinatario_email' => ['nullable', 'email', 'max:255'],
        'destinatario_direccion' => ['required', 'string', 'max:255'],

        'contenido' => ['required', 'string', 'max:255'],
        'valor_declarado' => ['nullable', 'numeric', 'min:0'],
        'referencia' => ['nullable', 'string', 'max:255'],
    ]);

    $cotizacion->update([
        ...$data,
        'valor_declarado' => $data['valor_declarado'] ?? 0,
        'estatus' => 'CHECKOUT_COMPLETO',
    ]);

    return redirect()
        ->route('b2c.pago', $cotizacion->id);
}

public function pago(B2cCotizacion $cotizacion)
{
    $accessToken = env('MERCADOPAGO_ACCESS_TOKEN');

    if (empty($accessToken)) {
        abort(500, 'Falta configurar MERCADOPAGO_ACCESS_TOKEN en .env');
    }

    SDK::setAccessToken($accessToken);

    $item = new Item();
    $item->title = 'Guía de envío ' . $cotizacion->logistico . ' - ' . $cotizacion->servicio;
    $item->quantity = 1;
    $item->unit_price = (float) $cotizacion->precio;
    $item->currency_id = 'MXN';

    $preference = new Preference();
    $preference->items = [$item];
    $preference->external_reference = 'B2C-' . $cotizacion->id;

    $preference->back_urls = [
    'success' => 'https://veto-emphatic-ahead.ngrok-free.dev/b2c/pago/'.$cotizacion->id.'/success',
    'failure' => 'https://veto-emphatic-ahead.ngrok-free.dev/b2c/pago/'.$cotizacion->id.'/failure',
    'pending' => 'https://veto-emphatic-ahead.ngrok-free.dev/b2c/pago/'.$cotizacion->id.'/pending',
];

    //$preference->auto_return = 'approved';
    $preference->save();

    if (!$preference->id || !$preference->init_point) {
    dd([
        'error' => $preference->error,
        'preference' => $preference,
    ]);
}

    $cotizacion->update([
        'estatus' => 'PAGO_INICIADO',
    ]);

    return redirect($preference->init_point);
}

public function pagoSuccess(B2cCotizacion $cotizacion)
{
    $cotizacion->update([
        'estatus' => 'PAGADA',
    ]);

    return view('b2c.pago-success', compact('cotizacion'));
}

public function pagoFailure(B2cCotizacion $cotizacion)
{
    $cotizacion->update([
        'estatus' => 'PAGO_RECHAZADO',
    ]);

    return 'Pago rechazado para cotización #' . $cotizacion->id;
}

public function pagoPending(B2cCotizacion $cotizacion)
{
    $cotizacion->update([
        'estatus' => 'PAGO_PENDIENTE',
    ]);

    return 'Pago pendiente para cotización #' . $cotizacion->id;
}

public function generarGuia(B2cCotizacion $cotizacion)
{
    if ($cotizacion->estatus !== 'PAGADA') {
        abort(403, 'La cotización aún no está pagada.');
    }

    if ($cotizacion->logistico === 'Estafeta') {
        return 'Aquí se generará guía Estafeta para cotización #' . $cotizacion->id;
    }

    if ($cotizacion->logistico === 'FedEx') {
        return 'Aquí se generará guía FedEx para cotización #' . $cotizacion->id;
    }

    if ($cotizacion->logistico === 'DHL') {
        return 'Aquí se generará guía DHL para cotización #' . $cotizacion->id;
    }

    abort(400, 'Logístico no soportado.');
}

}