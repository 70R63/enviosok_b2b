<?php

namespace App\Services\Billing;

use App\Models\B2cAdeudo;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cSaldo;
use Illuminate\Support\Facades\DB;

class B2cDebtPaymentService
{
    public function payWithBalance(
        B2cAdeudo $adeudo,
        int $userId
    ): B2cAdeudo {
        return DB::transaction(function () use ($adeudo, $userId) {
            $lockedDebt = B2cAdeudo::query()
                ->whereKey($adeudo->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedDebt->user_id !== $userId) {
                throw new \DomainException(
                    'El adeudo no pertenece al usuario autenticado.'
                );
            }

            if ($lockedDebt->isPaid()) {
                return $lockedDebt->refresh();
            }

            if (!$lockedDebt->isPending()) {
                throw new \DomainException(
                    'El adeudo ya no está disponible para pago.'
                );
            }

            $reference = 'ADEUDO-' . $lockedDebt->id;

            $existingMovement = B2cMovimientoSaldo::query()
                ->where('user_id', $userId)
                ->where('tipo', 'PAGO_ADEUDO')
                ->where('referencia', $reference)
                ->where('estatus', 'APLICADO')
                ->lockForUpdate()
                ->first();

            if ($existingMovement) {
                $lockedDebt->forceFill([
                    'estatus' => B2cAdeudo::STATUS_PAID_BALANCE,
                    'payment_method' => 'saldo_prepago',
                    'payment_external_reference' =>
                        'SALDO-' . $reference,
                    'paid_at' => $lockedDebt->paid_at ?: now(),
                ])->save();

                return $lockedDebt->refresh();
            }

            $balance = B2cSaldo::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = B2cSaldo::create([
                    'user_id' => $userId,
                    'saldo' => 0,
                ]);
            }

            $amount = round((float) $lockedDebt->monto, 2);
            $previousBalance = round((float) $balance->saldo, 2);

            if ($amount <= 0) {
                throw new \DomainException(
                    'El importe del adeudo no es válido.'
                );
            }

            if ($previousBalance < $amount) {
                throw new \DomainException(
                    'Saldo insuficiente para pagar el adeudo.'
                );
            }

            $newBalance = round($previousBalance - $amount, 2);

            $balance->forceFill([
                'saldo' => $newBalance,
            ])->save();

            B2cMovimientoSaldo::create([
                'user_id' => $userId,
                'tipo' => 'PAGO_ADEUDO',
                'monto' => $amount,
                'saldo_anterior' => $previousBalance,
                'saldo_nuevo' => $newBalance,
                'referencia' => $reference,
                'estatus' => 'APLICADO',
            ]);

            $lockedDebt->forceFill([
                'estatus' => B2cAdeudo::STATUS_PAID_BALANCE,
                'payment_method' => 'saldo_prepago',
                'payment_external_reference' =>
                    'SALDO-' . $reference,
                'paid_at' => now(),
            ])->save();

            return $lockedDebt->refresh();
        });
    }
}
