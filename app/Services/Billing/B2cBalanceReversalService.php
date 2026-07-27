<?php

namespace App\Services\Billing;

use App\Models\B2cCotizacion;
use App\Models\B2cMovimientoSaldo;
use App\Models\B2cSaldo;
use App\Models\B2cSaldoReversal;
use App\Models\Guia;
use DomainException;
use Illuminate\Support\Facades\DB;

class B2cBalanceReversalService
{
    public function preview(
        ?B2cCotizacion $cotizacion
    ): array {
        if (!$cotizacion) {
            return [
                'eligible' => false,
                'reasons' => [
                    'La incidencia no tiene una cotización relacionada.',
                ],
                'purchase_movement' => null,
                'balance' => null,
                'existing_reversal' => null,
                'local_guide' => null,
            ];
        }

        $purchaseMovement = $this->findAppliedPurchaseMovement(
            $cotizacion
        );

        $existingReversal = $purchaseMovement
            ? B2cSaldoReversal::query()
                ->where(
                    'purchase_movement_id',
                    $purchaseMovement->id
                )
                ->latest('id')
                ->first()
            : null;

        $balance = B2cSaldo::query()
            ->where('user_id', $cotizacion->user_id)
            ->first();

        $localGuide = $this->findLocalGuideEvidence(
            $cotizacion
        );

        $reasons = $this->eligibilityReasons(
            $cotizacion,
            $purchaseMovement,
            $balance,
            $existingReversal,
            $localGuide
        );

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'purchase_movement' => $purchaseMovement,
            'balance' => $balance,
            'existing_reversal' => $existingReversal,
            'local_guide' => $localGuide,
        ];
    }

    public function reverse(
        B2cCotizacion $cotizacion,
        int $adminUserId,
        string $reason,
        bool $providerConfirmed
    ): B2cSaldoReversal {
        $reason = trim($reason);

        if (!$providerConfirmed) {
            throw new DomainException(
                'Debes confirmar que Estafeta no generó la guía.'
            );
        }

        if (mb_strlen($reason) < 20) {
            throw new DomainException(
                'El motivo debe contener al menos 20 caracteres.'
            );
        }

        return DB::transaction(function () use (
            $cotizacion,
            $adminUserId,
            $reason
        ) {
            $lockedCotizacion = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $purchaseMovement = $this->findAppliedPurchaseMovement(
                $lockedCotizacion,
                true
            );

            $balance = B2cSaldo::query()
                ->where(
                    'user_id',
                    $lockedCotizacion->user_id
                )
                ->lockForUpdate()
                ->first();

            $existingReversal = $purchaseMovement
                ? B2cSaldoReversal::query()
                    ->where(
                        'purchase_movement_id',
                        $purchaseMovement->id
                    )
                    ->lockForUpdate()
                    ->first()
                : null;

            $localGuide = $this->findLocalGuideEvidence(
                $lockedCotizacion
            );

            $reasons = $this->eligibilityReasons(
                $lockedCotizacion,
                $purchaseMovement,
                $balance,
                $existingReversal,
                $localGuide
            );

            if ($reasons !== []) {
                throw new DomainException(
                    implode(' ', $reasons)
                );
            }

            $amountCents = $this->moneyToCents(
                $purchaseMovement->monto
            );

            $previousBalanceCents = $this->moneyToCents(
                $balance->saldo
            );

            $newBalanceCents =
                $previousBalanceCents + $amountCents;

            $balance->forceFill([
                'saldo' => $this->centsToMoney(
                    $newBalanceCents
                ),
            ])->save();

            $reversalReference =
                'REVERSO-COTIZACION-'
                . $lockedCotizacion->id
                . '-MOVIMIENTO-'
                . $purchaseMovement->id;

            $reversalMovement = B2cMovimientoSaldo::create([
                'user_id' => $lockedCotizacion->user_id,
                'tipo' => 'REVERSO_COMPRA_GUIA',
                'monto' => $this->centsToMoney(
                    $amountCents
                ),
                'saldo_anterior' => $this->centsToMoney(
                    $previousBalanceCents
                ),
                'saldo_nuevo' => $this->centsToMoney(
                    $newBalanceCents
                ),
                'referencia' => $reversalReference,
                'estatus' => 'APLICADO',
            ]);

            $purchaseMovement->forceFill([
                'estatus' => 'REVERTIDO',
            ])->save();

            $reversal = B2cSaldoReversal::create([
                'cotizacion_id' => $lockedCotizacion->id,
                'user_id' => $lockedCotizacion->user_id,
                'purchase_movement_id' =>
                    $purchaseMovement->id,
                'reversal_movement_id' =>
                    $reversalMovement->id,
                'admin_user_id' => $adminUserId,
                'amount' => $this->centsToMoney(
                    $amountCents
                ),
                'reason' => $reason,
                'provider_confirmation' => true,
                'status' => 'APPLIED',
                'metadata' => [
                    'provider' => 'ESTAFETA',
                    'guide_status_before' =>
                        $lockedCotizacion->guia_estatus,
                    'shipment_status_before' =>
                        $lockedCotizacion->estatus,
                    'provider_reference' =>
                        $lockedCotizacion
                            ->guia_provider_reference,
                    'provider_request_number' =>
                        $lockedCotizacion
                            ->guia_provider_request_number,
                ],
            ]);

            $lockedCotizacion->forceFill([
                'estatus' => 'SALDO_REVERTIDO',
                'payment_status' => 'saldo_revertido',
                'payment_verification_status' => 'REVERSED',
                'payment_verification_source' =>
                    'BALANCE_REVERSAL',
                'payment_verification_error' => null,
                'payment_verification_attempted_at' => now(),
                'guia_estatus' => 'SIN_GUIA',
                'guia_generation_started_at' => null,
                'guia_last_attempt_at' => now(),
                'guia_last_error_code' =>
                    'BALANCE_REVERSED',
                'guia_last_error_message' =>
                    'El saldo fue devuelto manualmente '
                    . 'después de confirmar que no existe guía.',
                'guia_response_snapshot' => [
                    'provider' => 'ESTAFETA',
                    'success' => false,
                    'balance_reversed' => true,
                    'reversal_id' => $reversal->id,
                    'reversal_movement_id' =>
                        $reversalMovement->id,
                ],
            ])->save();

            return $reversal->refresh();
        });
    }

    private function eligibilityReasons(
        B2cCotizacion $cotizacion,
        ?B2cMovimientoSaldo $purchaseMovement,
        ?B2cSaldo $balance,
        ?B2cSaldoReversal $existingReversal,
        ?Guia $localGuide
    ): array {
        $reasons = [];

        if (!$cotizacion->user_id) {
            $reasons[] =
                'La cotización no tiene un usuario propietario.';
        }

        if (
            strtolower(
                trim((string) $cotizacion->payment_status)
            ) !== 'saldo_prepago'
        ) {
            $reasons[] =
                'La cotización no fue pagada con saldo prepago.';
        }

        if (
            strtoupper(
                trim((string) $cotizacion->guia_estatus)
            ) === 'GENERANDO'
        ) {
            $reasons[] =
                'La guía todavía está marcada como GENERANDO.';
        }

        if ($cotizacion->hasGeneratedGuide()) {
            $reasons[] =
                'La cotización ya contiene una guía o tracking.';
        }

        if ($localGuide) {
            $reasons[] =
                'Existe evidencia local de una guía relacionada. '
                . 'Debe revisarse antes de devolver saldo.';
        }

        if (!$purchaseMovement) {
            $reasons[] =
                'No existe un movimiento COMPRA_GUIA aplicado '
                . 'por el importe de la cotización.';
        }

        if (!$balance) {
            $reasons[] =
                'El usuario no tiene un registro de saldo.';
        }

        if ($existingReversal) {
            $reasons[] =
                'El movimiento de compra ya cuenta con reverso.';
        }

        $shipmentStatus = strtoupper(
            trim((string) $cotizacion->estatus)
        );

        $guideStatus = strtoupper(
            trim((string) $cotizacion->guia_estatus)
        );

        if (
            !in_array($shipmentStatus, [
                'PAGADA',
                'ERROR_GENERACION_GUIA',
            ], true)
            || !in_array($guideStatus, [
                'ERROR_PROVEEDOR',
                'ERROR_VALIDACION_PESO',
            ], true)
        ) {
            $reasons[] =
                'El estado actual no corresponde a un error '
                . 'de generación conciliable.';
        }

        return array_values(
            array_unique($reasons)
        );
    }

    private function findAppliedPurchaseMovement(
        B2cCotizacion $cotizacion,
        bool $lock = false
    ): ?B2cMovimientoSaldo {
        $query = B2cMovimientoSaldo::query()
            ->where('user_id', $cotizacion->user_id)
            ->where('tipo', 'COMPRA_GUIA')
            ->where(
                'referencia',
                'COTIZACION-' . $cotizacion->id
            )
            ->where('estatus', 'APLICADO')
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->get()
            ->first(function (
                B2cMovimientoSaldo $movement
            ) use ($cotizacion) {
                return $this->moneyToCents(
                    $movement->monto
                ) === $this->moneyToCents(
                    $cotizacion->precio
                );
            });
    }

    private function findLocalGuideEvidence(
        B2cCotizacion $cotizacion
    ): ?Guia {
        if ($cotizacion->guia_id) {
            $guide = Guia::withoutGlobalScopes()
                ->find($cotizacion->guia_id);

            if ($guide) {
                return $guide;
            }
        }

        $requestNumber = (int)
            $cotizacion->guia_provider_request_number;

        if ($requestNumber <= 0) {
            return null;
        }

        return Guia::withoutGlobalScopes()
            ->where(
                'numero_solicitud',
                $requestNumber
            )
            ->latest('id')
            ->first();
    }

    private function moneyToCents(
        mixed $amount
    ): int {
        return (int) round(
            ((float) $amount) * 100
        );
    }

    private function centsToMoney(
        int $cents
    ): string {
        return number_format(
            $cents / 100,
            2,
            '.',
            ''
        );
    }
}
