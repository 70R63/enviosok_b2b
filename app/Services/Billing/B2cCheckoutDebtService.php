<?php

namespace App\Services\Billing;

use App\Models\B2cAdeudo;
use App\Models\B2cCheckoutDebtAllocation;
use App\Models\B2cCotizacion;
use Illuminate\Support\Facades\DB;

class B2cCheckoutDebtService
{
    public function pendingTotalForUser(int $userId): float
    {
        return round(
            (float) B2cAdeudo::query()
                ->where('user_id', $userId)
                ->whereIn('estatus', [
                    B2cAdeudo::STATUS_PENDING,
                    B2cAdeudo::STATUS_PAYMENT_STARTED,
                ])
                ->sum('monto'),
            2
        );
    }

    public function previewForCotizacion(
        B2cCotizacion $cotizacion
    ): array {
        $shipmentTotal = round(
            (float) $cotizacion->precio,
            2
        );

        if (empty($cotizacion->user_id)) {
            return $this->summary(
                $shipmentTotal,
                0,
                0
            );
        }

        $reservedForCurrent =
            B2cCheckoutDebtAllocation::query()
                ->where(
                    'checkout_cotizacion_id',
                    $cotizacion->id
                )
                ->where(
                    'estatus',
                    B2cCheckoutDebtAllocation::STATUS_RESERVED
                )
                ->get(['adeudo_id', 'monto']);

        $reservedCurrentIds = $reservedForCurrent
            ->pluck('adeudo_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $reservedByAnyQuoteIds =
            B2cCheckoutDebtAllocation::query()
                ->where(
                    'estatus',
                    B2cCheckoutDebtAllocation::STATUS_RESERVED
                )
                ->pluck('adeudo_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        $availableDebts = B2cAdeudo::query()
            ->where('user_id', $cotizacion->user_id)
            ->whereIn('estatus', [
                B2cAdeudo::STATUS_PENDING,
                B2cAdeudo::STATUS_PAYMENT_STARTED,
            ])
            ->when(
                $reservedByAnyQuoteIds !== [],
                function ($query) use (
                    $reservedByAnyQuoteIds,
                    $reservedCurrentIds
                ) {
                    $blockedIds = array_values(
                        array_diff(
                            $reservedByAnyQuoteIds,
                            $reservedCurrentIds
                        )
                    );

                    if ($blockedIds !== []) {
                        $query->whereNotIn('id', $blockedIds);
                    }
                }
            )
            ->get(['id', 'monto']);

        $debtTotal = round(
            (float) $availableDebts->sum('monto'),
            2
        );

        return $this->summary(
            $shipmentTotal,
            $debtTotal,
            $availableDebts->count()
        );
    }

    public function reserveForCotizacion(
        B2cCotizacion $cotizacion
    ): array {
        return DB::transaction(function () use ($cotizacion) {
            $lockedQuote = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shipmentTotal = round(
                (float) $lockedQuote->precio,
                2
            );

            if (empty($lockedQuote->user_id)) {
                return $this->summary(
                    $shipmentTotal,
                    0,
                    0
                );
            }

            $currentAllocations =
                B2cCheckoutDebtAllocation::query()
                    ->where(
                        'checkout_cotizacion_id',
                        $lockedQuote->id
                    )
                    ->where(
                        'estatus',
                        B2cCheckoutDebtAllocation::STATUS_RESERVED
                    )
                    ->lockForUpdate()
                    ->get();

            foreach ($currentAllocations as $allocation) {
                $debt = B2cAdeudo::query()
                    ->whereKey($allocation->adeudo_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$debt
                    || (int) $debt->user_id
                        !== (int) $lockedQuote->user_id
                    || !$debt->isPending()
                ) {
                    $allocation->forceFill([
                        'estatus' =>
                            B2cCheckoutDebtAllocation::STATUS_RELEASED,
                        'released_at' => now(),
                    ])->save();
                }
            }

            $reservedByOtherQuoteIds =
                B2cCheckoutDebtAllocation::query()
                    ->where(
                        'estatus',
                        B2cCheckoutDebtAllocation::STATUS_RESERVED
                    )
                    ->where(
                        'checkout_cotizacion_id',
                        '!=',
                        $lockedQuote->id
                    )
                    ->pluck('adeudo_id')
                    ->all();

            $pendingDebts = B2cAdeudo::query()
                ->where('user_id', $lockedQuote->user_id)
                ->whereIn('estatus', [
                    B2cAdeudo::STATUS_PENDING,
                    B2cAdeudo::STATUS_PAYMENT_STARTED,
                ])
                ->when(
                    $reservedByOtherQuoteIds !== [],
                    static function ($query) use (
                        $reservedByOtherQuoteIds
                    ) {
                        $query->whereNotIn(
                            'id',
                            $reservedByOtherQuoteIds
                        );
                    }
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pendingDebts as $debt) {
                $allocation =
                    B2cCheckoutDebtAllocation::query()
                        ->where('adeudo_id', $debt->id)
                        ->lockForUpdate()
                        ->first();

                if (
                    $allocation
                    && $allocation->estatus
                        === B2cCheckoutDebtAllocation::STATUS_APPLIED
                ) {
                    continue;
                }

                $values = [
                    'checkout_cotizacion_id' => $lockedQuote->id,
                    'user_id' => $lockedQuote->user_id,
                    'monto' => round((float) $debt->monto, 2),
                    'estatus' =>
                        B2cCheckoutDebtAllocation::STATUS_RESERVED,
                    'payment_method' => null,
                    'payment_reference' => null,
                    'reserved_at' => now(),
                    'paid_at' => null,
                    'released_at' => null,
                ];

                if ($allocation) {
                    $allocation->forceFill($values)->save();
                } else {
                    B2cCheckoutDebtAllocation::create(
                        array_merge(
                            ['adeudo_id' => $debt->id],
                            $values
                        )
                    );
                }

                if (
                    $debt->estatus === B2cAdeudo::STATUS_PENDING
                ) {
                    $debt->forceFill([
                        'estatus' =>
                            B2cAdeudo::STATUS_PAYMENT_STARTED,
                    ])->save();
                }
            }

            return $this->reservedSummary($lockedQuote);
        });
    }

    public function reservedSummary(
        B2cCotizacion $cotizacion
    ): array {
        $shipmentTotal = round(
            (float) $cotizacion->precio,
            2
        );

        $allocations =
            B2cCheckoutDebtAllocation::query()
                ->where(
                    'checkout_cotizacion_id',
                    $cotizacion->id
                )
                ->whereIn('estatus', [
                    B2cCheckoutDebtAllocation::STATUS_RESERVED,
                    B2cCheckoutDebtAllocation::STATUS_APPLIED,
                ])
                ->get(['monto']);

        return $this->summary(
            $shipmentTotal,
            round((float) $allocations->sum('monto'), 2),
            $allocations->count()
        );
    }

    public function expectedPaymentTotal(
        B2cCotizacion $cotizacion
    ): float {
        return (float) $this
            ->reservedSummary($cotizacion)['payment_total'];
    }

    public function applyReserved(
        B2cCotizacion $cotizacion,
        string $paymentMethod,
        string $paymentReference
    ): void {
        DB::transaction(function () use (
            $cotizacion,
            $paymentMethod,
            $paymentReference
        ): void {
            $allocations =
                B2cCheckoutDebtAllocation::query()
                    ->where(
                        'checkout_cotizacion_id',
                        $cotizacion->id
                    )
                    ->where(
                        'estatus',
                        B2cCheckoutDebtAllocation::STATUS_RESERVED
                    )
                    ->lockForUpdate()
                    ->get();

            $paidStatus = $paymentMethod === 'saldo_prepago'
                ? B2cAdeudo::STATUS_PAID_BALANCE
                : B2cAdeudo::STATUS_PAID_MERCADOPAGO;

            foreach ($allocations as $allocation) {
                $debt = B2cAdeudo::query()
                    ->whereKey($allocation->adeudo_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($debt->isPaid()) {
                    throw new \DomainException(
                        'Uno de los adeudos incluidos ya fue pagado.'
                    );
                }

                $debt->forceFill([
                    'estatus' => $paidStatus,
                    'payment_method' => $paymentMethod,
                    'payment_external_reference' =>
                        $paymentReference,
                    'paid_at' => now(),
                ])->save();

                $allocation->forceFill([
                    'estatus' =>
                        B2cCheckoutDebtAllocation::STATUS_APPLIED,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                    'paid_at' => now(),
                ])->save();
            }
        });
    }

    public function releaseReserved(
        B2cCotizacion $cotizacion
    ): void {
        DB::transaction(function () use ($cotizacion): void {
            $allocations =
                B2cCheckoutDebtAllocation::query()
                    ->where(
                        'checkout_cotizacion_id',
                        $cotizacion->id
                    )
                    ->where(
                        'estatus',
                        B2cCheckoutDebtAllocation::STATUS_RESERVED
                    )
                    ->lockForUpdate()
                    ->get();

            foreach ($allocations as $allocation) {
                $debt = B2cAdeudo::query()
                    ->whereKey($allocation->adeudo_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $debt
                    && $debt->estatus
                        === B2cAdeudo::STATUS_PAYMENT_STARTED
                ) {
                    $debt->forceFill([
                        'estatus' => B2cAdeudo::STATUS_PENDING,
                    ])->save();
                }

                $allocation->forceFill([
                    'estatus' =>
                        B2cCheckoutDebtAllocation::STATUS_RELEASED,
                    'released_at' => now(),
                ])->save();
            }
        });
    }

    public function debtHasActiveReservation(
        B2cAdeudo $adeudo
    ): bool {
        return B2cCheckoutDebtAllocation::query()
            ->where('adeudo_id', $adeudo->id)
            ->where(
                'estatus',
                B2cCheckoutDebtAllocation::STATUS_RESERVED
            )
            ->exists();
    }

    private function summary(
        float $shipmentTotal,
        float $debtTotal,
        int $debtCount
    ): array {
        return [
            'shipment_total' => round($shipmentTotal, 2),
            'debt_total' => round($debtTotal, 2),
            'payment_total' => round(
                $shipmentTotal + $debtTotal,
                2
            ),
            'debt_count' => $debtCount,
        ];
    }
}
