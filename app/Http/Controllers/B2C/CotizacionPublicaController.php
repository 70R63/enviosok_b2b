<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use Illuminate\Http\Request;

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
    return 'Pago pendiente para cotización #' . $cotizacion->id . ' por $' . number_format($cotizacion->precio, 2) . ' MXN';
}

}