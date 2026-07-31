<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentVerificationException;
use App\Models\B2cCotizacion;
use App\Models\B2cMovimientoSaldo;
use App\Services\Billing\B2cCheckoutDebtService;
use Illuminate\Support\Facades\DB;

class PaymentVerificationService
{
    public function __construct(
        private MercadoPagoPaymentClient $mercadoPagoClient,
        private B2cCheckoutDebtService $checkoutDebtService
    ) {
    }

    public function verifyByPaymentId(
        B2cCotizacion $cotizacion,
        string $paymentId,
        string $source = 'RETURN'
    ): B2cCotizacion {
        $payload = $this->mercadoPagoClient->find($paymentId);

        return $this->verifyPayload(
            $cotizacion,
            $payload,
            $source
        );
    }

    public function verifyPayload(
        B2cCotizacion $cotizacion,
        array $payload,
        string $source = 'WEBHOOK'
    ): B2cCotizacion {
        return DB::transaction(function () use (
            $cotizacion,
            $payload,
            $source
        ) {
            $locked = B2cCotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentId = trim((string) ($payload['id'] ?? ''));
            $paymentStatus = strtolower(trim((string) (
                $payload['status'] ?? ''
            )));
            $externalReference = trim((string) (
                $payload['external_reference'] ?? ''
            ));
            $currency = strtoupper(trim((string) (
                $payload['currency_id'] ?? ''
            )));
            $amount = $payload['transaction_amount'] ?? null;

            $locked->forceFill([
                'payment_verification_attempted_at' => now(),
                'payment_verification_source' => strtoupper($source),
                'payment_verification_payload' => $this->sanitize(
                    $payload
                ),
            ])->save();

            if ($paymentId === '') {
                return $this->markFailed(
                    $locked,
                    'PAYMENT_ID_MISSING',
                    'Mercado Pago no devolvió el identificador del pago.'
                );
            }

            $duplicateExists = B2cCotizacion::query()
                ->where('id', '!=', $locked->id)
                ->where('payment_id', $paymentId)
                ->where(function ($query) {
                    $query
                        ->whereNotNull('payment_verified_at')
                        ->orWhere('payment_status', 'approved')
                        ->orWhereIn('estatus', [
                            'PAGADA',
                            'GUIA_GENERADA',
                            'ERROR_GENERACION_GUIA',
                        ]);
                })
                ->exists();

            if ($duplicateExists) {
                return $this->markFailed(
                    $locked,
                    'PAYMENT_ALREADY_USED',
                    'El pago ya está relacionado con otra cotización.'
                );
            }

            $expectedReference = 'B2C-' . $locked->id;
            $expectedCurrency = strtoupper((string) config(
                'services.mercadopago.currency',
                'MXN'
            ));

            if (in_array($paymentStatus, [
                'pending',
                'in_process',
                'in_mediation',
                'authorized',
            ], true)) {
                $locked->forceFill([
                    'estatus' => 'PAGO_PENDIENTE',
                    'payment_id' => $paymentId,
                    'payment_status' => $paymentStatus,
                    'payment_external_reference' => $externalReference,
                    'payment_collection_id' => $paymentId,
                    'payment_verification_status' => 'PENDING',
                    'payment_verification_error' => null,
                ])->save();

                return $locked->refresh();
            }

            if ($paymentStatus !== 'approved') {
                $locked->forceFill([
                    'estatus' => 'PAGO_RECHAZADO',
                    'payment_id' => $paymentId,
                    'payment_status' => $paymentStatus ?: 'unknown',
                    'payment_external_reference' => $externalReference,
                    'payment_collection_id' => $paymentId,
                    'payment_verification_status' => 'REJECTED',
                    'payment_verification_error' => null,
                ])->save();

                $this->checkoutDebtService
                    ->releaseReserved($locked);

                return $locked->refresh();
            }

            if ($externalReference !== $expectedReference) {
                return $this->markFailed(
                    $locked,
                    'EXTERNAL_REFERENCE_MISMATCH',
                    'La referencia del pago no coincide con la cotización.'
                );
            }

            if ($currency !== $expectedCurrency) {
                return $this->markFailed(
                    $locked,
                    'CURRENCY_MISMATCH',
                    'La moneda del pago no coincide con la cotización.'
                );
            }

            $expectedPaymentTotal =
                $this->checkoutDebtService
                    ->expectedPaymentTotal($locked);

            if (
                $this->moneyToCents($amount)
                !== $this->moneyToCents($expectedPaymentTotal)
            ) {
                return $this->markFailed(
                    $locked,
                    'AMOUNT_MISMATCH',
                    'El importe pagado no coincide con el total de la cotización.'
                );
            }

            $locked->forceFill([
                'estatus' => $locked->estatus === 'GUIA_GENERADA'
                    ? 'GUIA_GENERADA'
                    : 'PAGADA',
                'payment_id' => $paymentId,
                'payment_status' => 'approved',
                'payment_external_reference' => $externalReference,
                'payment_collection_id' => $paymentId,
                'payment_verification_status' => 'VERIFIED',
                'payment_verification_error' => null,
                'payment_verified_at' => now(),
                'payment_verified_amount' => $this->formatMoney($amount),
                'payment_verified_currency' => $currency,
                'payment_verified_external_reference' => $externalReference,
            ])->save();

            $this->checkoutDebtService->applyReserved(
                $locked,
                'mercado_pago',
                $paymentId
            );

            return $locked->refresh();
        });
    }

    public function markBalancePaymentVerified(
        B2cCotizacion $cotizacion,
        ?float $paymentTotal = null
    ): B2cCotizacion {
        $verifiedTotal = $paymentTotal
            ?? $this->checkoutDebtService
                ->expectedPaymentTotal($cotizacion);

        $cotizacion->forceFill([
            'payment_verification_status' => 'VERIFIED',
            'payment_verification_source' => 'BALANCE',
            'payment_verification_error' => null,
            'payment_verification_attempted_at' => now(),
            'payment_verified_at' => now(),
            'payment_verified_amount' => $verifiedTotal,
            'payment_verified_currency' => 'MXN',
            'payment_verified_external_reference' =>
                'SALDO-' . $cotizacion->id,
            'payment_verification_payload' => [
                'source' => 'SALDO_PREPAGO',
                'cotizacion_id' => $cotizacion->id,
            ],
        ])->save();

        return $cotizacion->refresh();
    }

    public function isEligibleForGuide(
        B2cCotizacion $cotizacion
    ): bool {
        $expectedPaymentTotal =
            $this->checkoutDebtService
                ->expectedPaymentTotal($cotizacion);

        if ($cotizacion->payment_status === 'saldo_prepago') {
            $movement = B2cMovimientoSaldo::query()
                ->where('user_id', $cotizacion->user_id)
                ->where('tipo', 'COMPRA_GUIA')
                ->where(
                    'referencia',
                    'COTIZACION-' . $cotizacion->id
                )
                ->where('estatus', 'APLICADO')
                ->latest('id')
                ->first();

            return $movement
                && $this->moneyToCents($movement->monto)
                    === $this->moneyToCents($expectedPaymentTotal);
        }

        return $cotizacion->payment_status === 'approved'
            && $cotizacion->payment_verification_status === 'VERIFIED'
            && $cotizacion->payment_verified_at !== null
            && strtoupper((string) $cotizacion->payment_verified_currency)
                === 'MXN'
            && $this->moneyToCents(
                $cotizacion->payment_verified_amount
            ) === $this->moneyToCents($expectedPaymentTotal)
            && $cotizacion->payment_verified_external_reference
                === 'B2C-' . $cotizacion->id;
    }

    private function markFailed(
        B2cCotizacion $cotizacion,
        string $code,
        string $message
    ): B2cCotizacion {
        $cotizacion->forceFill([
            'estatus' => 'PAGO_VERIFICACION_FALLIDA',
            'payment_verification_status' => 'FAILED',
            'payment_verification_error' => $code . ': ' . $message,
        ])->save();

        return $cotizacion->refresh();
    }

    private function sanitize(array $payload): array
    {
        return [
            'id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'status_detail' => $payload['status_detail'] ?? null,
            'external_reference' =>
                $payload['external_reference'] ?? null,
            'transaction_amount' =>
                $payload['transaction_amount'] ?? null,
            'currency_id' => $payload['currency_id'] ?? null,
            'payment_method_id' =>
                $payload['payment_method_id'] ?? null,
            'payment_type_id' =>
                $payload['payment_type_id'] ?? null,
            'date_approved' => $payload['date_approved'] ?? null,
            'live_mode' => $payload['live_mode'] ?? null,
        ];
    }

    private function moneyToCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
