<?php

namespace App\Http\Controllers\B2C;

use App\Http\Controllers\Controller;
use App\Models\B2cAdeudo;
use App\Models\B2cSaldo;
use App\Services\Billing\B2cDebtPaymentService;

class B2cAdeudoController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $adeudos = B2cAdeudo::query()
            ->with('cotizacion:id,guia_id,tracking_number,logistico,servicio')
            ->where('user_id', $userId)
            ->orderByRaw(
                "CASE
                    WHEN estatus = 'PENDIENTE' THEN 0
                    WHEN estatus = 'PAGO_INICIADO' THEN 1
                    ELSE 2
                END"
            )
            ->latest('id')
            ->paginate(20);

        $saldo = B2cSaldo::firstOrCreate(
            ['user_id' => $userId],
            ['saldo' => 0]
        );

        $pendingTotal = B2cAdeudo::query()
            ->where('user_id', $userId)
            ->whereIn('estatus', [
                B2cAdeudo::STATUS_PENDING,
                B2cAdeudo::STATUS_PAYMENT_STARTED,
            ])
            ->sum('monto');

        return view('b2c.adeudos.index', compact(
            'adeudos',
            'saldo',
            'pendingTotal'
        ));
    }

    public function payWithBalance(
        B2cAdeudo $adeudo,
        B2cDebtPaymentService $paymentService
    ) {
        if ((int) $adeudo->user_id !== (int) auth()->id()) {
            abort(403);
        }

        try {
            $paymentService->payWithBalance(
                $adeudo,
                (int) auth()->id()
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('b2c.adeudos.index')
            ->with(
                'success',
                'Adeudo pagado correctamente con saldo prepago.'
            );
    }
}
