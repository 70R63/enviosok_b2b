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

    $accessToken = (string) config('services.mercadopago.access_token');
    $correlation = (string) $recarga->referencia;

    if ($accessToken === '') {
        $recarga->update(['estatus' => 'ERROR']);
        Log::error('No se pudo iniciar la recarga: configuración incompleta.', [
            'user_id' => auth()->id(), 'recarga_id' => $recarga->id,
            'monto' => (float) $recarga->monto, 'correlation' => $correlation,
        ]);
        return redirect()->route('b2c.prepago')
            ->with('error', 'No fue posible iniciar la recarga. Intenta nuevamente.');
    }

try {
    SDK::setAccessToken($accessToken);

    $item = new Item();
    $item->title = 'Recarga saldo ZIGO';
    $item->quantity = 1;
    $item->unit_price = (float) $recarga->monto;
    $item->currency_id = 'MXN';

    $preference = new Preference();
    $preference->items = [$item];

    $preference->external_reference = 'RECARGA-' . $recarga->id;

    $baseUrl = rtrim((string) config('app.url'), '/');

    $preference->back_urls = [
        'success' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/success',
        'failure' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/failure',
        'pending' => $baseUrl . '/b2c/prepago/' . $recarga->id . '/pending',
    ];

    $preference->notification_url = $baseUrl . '/b2c/prepago/webhook';

    $preference->auto_return = 'approved';

    $preference->save();

    if (!$preference->id) {
        $recarga->update(['estatus' => 'ERROR']);
        Log::error('MP RECARGA ERROR', [
            'user_id' => auth()->id(), 'recarga_id' => $recarga->id,
            'monto' => (float) $recarga->monto,
            'exception_class' => 'MercadoPagoPreferenceError',
            'message' => 'Mercado Pago no devolvió un identificador de preferencia.',
            'correlation' => $correlation,
        ]);
        return redirect()->route('b2c.prepago')
            ->with('error', 'No fue posible iniciar la recarga. Intenta nuevamente.');
    }

    $recarga->update([
        'mp_preference_id' => $preference->id,
    ]);

    $checkoutUrl = $preference->init_point
        ?? $preference->sandbox_init_point
        ?? null;

    if (!$checkoutUrl) {
        $recarga->update(['estatus' => 'ERROR']);
        return redirect()
            ->route('b2c.prepago')
            ->with('error', 'No fue posible iniciar la recarga. Intenta nuevamente.');
    }

    return redirect()->away($checkoutUrl);

} catch (\Throwable $e) {
    $recarga->update(['estatus' => 'ERROR']);
    Log::error('MP RECARGA EXCEPTION', [
        'user_id' => auth()->id(), 'recarga_id' => $recarga->id,
        'monto' => (float) $recarga->monto,
        'exception_class' => get_class($e),
        'message' => mb_substr(preg_replace('/[\r\n]+/', ' ', $e->getMessage()), 0, 300),
        'correlation' => $correlation,
    ]);
    return redirect()->route('b2c.prepago')
        ->with('error', 'No fue posible iniciar la recarga. Intenta nuevamente.');
  }
}

public function recargaSuccess(Request $request, B2cRecarga $recarga)
{
    if ($recarga->user_id !== auth()->id()) {
        abort(403);
    }

    $recarga->refresh();
    $approved = $recarga->estatus === 'APROBADA';
    return redirect()
        ->to($approved ? $this->consumeRechargeReturnUrl($recarga) : route('b2c.prepago'))
        ->with(
            $approved ? 'success' : 'error',
            $approved
                ? 'Saldo recargado correctamente.'
                : 'La recarga está pendiente de confirmación.'
        );
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
    $paymentId = $request->input('data.id') ?? $request->input('id');

    if (!$paymentId) {
        return response('OK', 200);
    }

    $accessToken = (string) config('services.mercadopago.access_token');
    if ($accessToken === '') {
        Log::error('No se pudo procesar webhook de recarga: configuración incompleta.', [
            'correlation' => (string) $paymentId,
        ]);
        return response('OK', 200);
    }
    SDK::setAccessToken($accessToken);

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
    DB::transaction(function () use ($recarga, $paymentId, $mpStatus) {
        $recarga = B2cRecarga::query()->whereKey($recarga->id)->lockForUpdate()->firstOrFail();
        if ($recarga->estatus === 'APROBADA') return;
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

private function consumeRechargeReturnUrl(B2cRecarga $recarga): string
{
    $pending = (array) session('b2c_pending_recharge_checkout', []);
    session()->forget('b2c_pending_recharge_checkout');
    if ((int) ($pending['user_id'] ?? 0) !== (int) $recarga->user_id) {
        return route('b2c.prepago');
    }
    $cotizacion = \App\Models\B2cCotizacion::query()
        ->whereKey((int) ($pending['cotizacion_id'] ?? 0))
        ->where('user_id', $recarga->user_id)->first();
    return $cotizacion ? route('b2c.checkout', $cotizacion->id) : route('b2c.prepago');
}

}
