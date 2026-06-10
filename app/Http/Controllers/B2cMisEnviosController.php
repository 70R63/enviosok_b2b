<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\B2cSaldo;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cRecarga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;
use MercadoPago\Payment;

class B2cMisEnviosController extends Controller
{
    public function index()
    {
        return view('b2c.dashboard');
    }

    public function prepago()
{
    $saldo = B2cSaldo::firstOrCreate(
        ['user_id' => auth()->id()],
        ['saldo' => 0]
    );

    $movimientos = B2cMovimientoSaldo::where('user_id', auth()->id())
        ->latest()
        ->get();

    return view('b2c.prepago', compact('saldo', 'movimientos'));
}

public function crearRecarga(Request $request)
{
    $data = $request->validate([
        'monto' => ['required', 'numeric', 'min:300', 'max:20000'],
    ]);

    $recarga = B2cRecarga::create([
        'user_id' => auth()->id(),
        'monto' => $data['monto'],
        'estatus' => 'PENDIENTE',
        'referencia' => 'RECARGA-' . time(),
    ]);

try {
    SDK::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));

    $item = new Item();
    $item->title = 'Recarga saldo ZIGO';
    $item->quantity = 1;
    $item->unit_price = (float) $recarga->monto;
    $item->currency_id = 'MXN';

    $preference = new Preference();
    $preference->items = [$item];

    $preference->external_reference = 'RECARGA-' . $recarga->id;

    $baseUrl = rtrim(env('APP_URL'), '/');

    $preference->back_urls = [
        'success' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/success',
        'failure' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/failure',
        'pending' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/pending',
    ];

    $preference->notification_url = $baseUrl . '/b2c/prepago/webhook';

    $preference->auto_return = 'approved';

    $preference->save();

    if (!$preference->id) {
        Log::error('MP RECARGA ERROR', [
            'error' => $preference->error ?? null,
            'preference' => $preference,
        ]);

        dd([
            'error' => $preference->error ?? null,
            'message' => isset($preference->error->message) ? $preference->error->message : null,
            'status' => isset($preference->error->status) ? $preference->error->status : null,
            'error_code' => isset($preference->error->error) ? $preference->error->error : null,
        ]);
    }

    $recarga->update([
        'mp_preference_id' => $preference->id,
    ]);

    $checkoutUrl = $preference->init_point
        ?? $preference->sandbox_init_point
        ?? null;

    if (!$checkoutUrl) {
        return redirect()
            ->route('b2c.prepago')
            ->with('error', 'Mercado Pago no devolvió URL de pago.');
    }

    return redirect()->away($checkoutUrl);

} catch (\Throwable $e) {
    Log::error('MP RECARGA EXCEPTION', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    dd([
        'exception' => $e->getMessage(),
    ]);
  }
}

public function recargaSuccess(Request $request, B2cRecarga $recarga)
{
    if ($recarga->user_id !== auth()->id()) {
        abort(403);
    }

    $this->aplicarRecargaSaldo(
        $recarga,
        $request->get('payment_id') ?? $request->get('collection_id'),
        $request->get('status') ?? $request->get('collection_status') ?? 'approved'
    );

    return redirect()
        ->route('b2c.prepago')
        ->with('success', 'Saldo recargado correctamente.');
}

public function recargaFailure(B2cRecarga $recarga)
{
    if ($recarga->user_id !== auth()->id()) {
        abort(403);
    }

    $recarga->update([
        'estatus' => 'RECHAZADA',
        'mp_status' => 'failure',
    ]);

    return redirect()->route('b2c.prepago')
        ->with('error', 'La recarga fue rechazada o cancelada.');
}

public function recargaPending(B2cRecarga $recarga)
{
    if ($recarga->user_id !== auth()->id()) {
        abort(403);
    }

    $recarga->update([
        'estatus' => 'PENDIENTE',
        'mp_status' => 'pending',
    ]);

    return redirect()->route('b2c.prepago')
        ->with('success', 'La recarga está pendiente de confirmación.');
}

public function recargaWebhook(Request $request)
{
    Log::info('MP RECARGA WEBHOOK', $request->all());

    $paymentId = $request->input('data.id') ?? $request->input('id');

    if (!$paymentId) {
        return response('OK', 200);
    }

    SDK::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));

    $payment = Payment::find_by_id($paymentId);

    if (!$payment || $payment->status !== 'approved') {
        return response('OK', 200);
    }

    $externalReference = $payment->external_reference ?? null;

    if (!$externalReference || !str_starts_with($externalReference, 'RECARGA-')) {
        return response('OK', 200);
    }

    $recargaId = str_replace('RECARGA-', '', $externalReference);

    $recarga = B2cRecarga::find($recargaId);

    if (!$recarga) {
        return response('OK', 200);
    }

    $this->aplicarRecargaSaldo($recarga, $payment->id, $payment->status);

    return response('OK', 200);
}

private function aplicarRecargaSaldo(B2cRecarga $recarga, $paymentId = null, $mpStatus = 'approved')
{
    if ($recarga->estatus === 'APROBADA') {
        return;
    }

    DB::transaction(function () use ($recarga, $paymentId, $mpStatus) {
        $saldo = B2cSaldo::firstOrCreate(
            ['user_id' => $recarga->user_id],
            ['saldo' => 0]
        );

        $saldoAnterior = $saldo->saldo;
        $saldoNuevo = $saldoAnterior + $recarga->monto;

        $saldo->update([
            'saldo' => $saldoNuevo,
        ]);

        $recarga->update([
            'estatus' => 'APROBADA',
            'mp_payment_id' => $paymentId,
            'mp_status' => $mpStatus,
        ]);

        B2cMovimientoSaldo::create([
            'user_id' => $recarga->user_id,
            'tipo' => 'RECARGA',
            'monto' => $recarga->monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'referencia' => 'RECARGA-' . $recarga->id,
            'estatus' => 'APLICADO',
        ]);
    });
}

}