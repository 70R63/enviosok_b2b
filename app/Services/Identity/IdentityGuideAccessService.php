<?php

namespace App\Services\Identity;

use App\Exceptions\Identity\IdentityVerificationRequiredException;
use App\Models\B2cCotizacion;
use App\Models\B2cIdentityVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class IdentityGuideAccessService
{
    public function evaluate(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        return $this->evaluateForPayment(
            $userId,
            $excludeCotizacionId,
            $lockUser
        );
    }

    public function evaluateForPayment(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        if ($lockUser) {
            $this->lockUser($userId);
        }

        $status = $this->identityStatus($userId);
        $limit = $this->limit();

        $generated = $this->countGeneratedGuides(
            $userId,
            $excludeCotizacionId
        );

        $reserved = $this->countPaymentReservations(
            $userId,
            $excludeCotizacionId
        );

        $committed = $generated + $reserved;

        $approved =
            $status
            === B2cIdentityVerification::STATUS_APPROVED;

        $rejected =
            $status
            === B2cIdentityVerification::STATUS_REJECTED;

        $allowed =
            $approved
            || (
                !$rejected
                && $committed < $limit
            );

        return $this->assessment(
            $status,
            $limit,
            $generated,
            $reserved,
            $allowed,
            'payment'
        );
    }

    public function evaluateForGeneration(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        if ($lockUser) {
            $this->lockUser($userId);
        }

        $status = $this->identityStatus($userId);
        $limit = $this->limit();

        $generated = $this->countGeneratedGuides(
            $userId,
            $excludeCotizacionId
        );

        $approved =
            $status
            === B2cIdentityVerification::STATUS_APPROVED;

        $rejected =
            $status
            === B2cIdentityVerification::STATUS_REJECTED;

        $allowed =
            $approved
            || (
                !$rejected
                && $generated < $limit
            );

        return $this->assessment(
            $status,
            $limit,
            $generated,
            0,
            $allowed,
            'generation'
        );
    }

    public function assertCanProceed(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        return $this->assertCanStartPayment(
            $userId,
            $excludeCotizacionId,
            $lockUser
        );
    }

    public function assertCanStartPayment(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        $assessment = $this->evaluateForPayment(
            $userId,
            $excludeCotizacionId,
            $lockUser
        );

        $this->throwWhenBlocked($assessment);

        return $assessment;
    }

    public function assertCanGenerate(
        int $userId,
        ?int $excludeCotizacionId = null,
        bool $lockUser = false
    ): array {
        $assessment = $this->evaluateForGeneration(
            $userId,
            $excludeCotizacionId,
            $lockUser
        );

        $this->throwWhenBlocked($assessment);

        return $assessment;
    }

    public function countGeneratedGuides(
        int $userId,
        ?int $excludeCotizacionId = null
    ): int {
        return $this->baseCotizacionQuery(
            $userId,
            $excludeCotizacionId
        )
            ->where(
                function (Builder $query) {
                    $query
                        ->whereNotNull('guia_id')
                        ->orWhereNotNull(
                            'tracking_number'
                        )
                        ->orWhere(
                            'guia_estatus',
                            'GENERADA'
                        )
                        ->orWhere(
                            'estatus',
                            'GUIA_GENERADA'
                        );
                }
            )
            ->count();
    }

    public function countPaymentReservations(
        int $userId,
        ?int $excludeCotizacionId = null
    ): int {
        $reservationCutoff = now()->subHours(
            max(
                1,
                (int) config(
                    'b2c_identity.payment_reservation_hours',
                    24
                )
            )
        );

        return $this->baseCotizacionQuery(
            $userId,
            $excludeCotizacionId
        )
            ->where(
                function (Builder $query) {
                    $query
                        ->whereNull('guia_id')
                        ->whereNull('tracking_number')
                        ->where(
                            function (Builder $guideQuery) {
                                $guideQuery
                                    ->whereNull(
                                        'guia_estatus'
                                    )
                                    ->orWhere(
                                        'guia_estatus',
                                        '!=',
                                        'GENERADA'
                                    );
                            }
                        )
                        ->where(
                            function (Builder $guideQuery) {
                                $guideQuery
                                    ->whereNull('estatus')
                                    ->orWhere(
                                        'estatus',
                                        '!=',
                                        'GUIA_GENERADA'
                                    );
                            }
                        );
                }
            )
            ->where(
                function (Builder $query) use (
                    $reservationCutoff
                ) {
                    $query
                        ->whereIn(
                            'estatus',
                            [
                                'PAGADA',
                                'ERROR_GENERACION_GUIA',
                            ]
                        )
                        ->orWhereIn(
                            'payment_status',
                            [
                                'approved',
                                'authorized',
                                'saldo_prepago',
                            ]
                        )
                        ->orWhereNotNull(
                            'payment_verified_at'
                        )
                        ->orWhere(
                            function (
                                Builder $temporaryQuery
                            ) use (
                                $reservationCutoff
                            ) {
                                $temporaryQuery
                                    ->whereIn(
                                        'estatus',
                                        [
                                            'PAGO_INICIADO',
                                            'PAGO_EN_VERIFICACION',
                                            'PAGO_PENDIENTE',
                                        ]
                                    )
                                    ->where(
                                        'updated_at',
                                        '>=',
                                        $reservationCutoff
                                    );
                            }
                        )
                        ->orWhere(
                            function (
                                Builder $temporaryQuery
                            ) use (
                                $reservationCutoff
                            ) {
                                $temporaryQuery
                                    ->whereIn(
                                        'payment_status',
                                        [
                                            'pending',
                                            'in_process',
                                        ]
                                    )
                                    ->where(
                                        'updated_at',
                                        '>=',
                                        $reservationCutoff
                                    );
                            }
                        );
                }
            )
            ->count();
    }

    private function baseCotizacionQuery(
        int $userId,
        ?int $excludeCotizacionId
    ): Builder {
        return B2cCotizacion::query()
            ->where('user_id', $userId)
            ->when(
                $excludeCotizacionId !== null,
                function (
                    Builder $query
                ) use (
                    $excludeCotizacionId
                ) {
                    $query->where(
                        'id',
                        '!=',
                        $excludeCotizacionId
                    );
                }
            );
    }

    private function assessment(
        string $status,
        int $limit,
        int $generated,
        int $reserved,
        bool $allowed,
        string $operation
    ): array {
        $approved =
            $status
            === B2cIdentityVerification::STATUS_APPROVED;

        $committed = $generated + $reserved;

        $remainingGuides = $approved
            ? null
            : max(0, $limit - $generated);

        $remainingPaymentSlots = $approved
            ? null
            : max(0, $limit - $committed);

        return [
            'allowed' => $allowed,
            'blocked' => !$allowed,
            'approved' => $approved,
            'identity_status' => $status,
            'limit' => $limit,

            // Compatibilidad con la vista existente:
            // used_slots representa solo guías reales.
            'used_slots' => $generated,
            'remaining_slots' =>
                $remainingPaymentSlots,

            'generated_guides' => $generated,
            'reserved_payments' => $reserved,
            'committed_slots' => $committed,
            'remaining_guides' => $remainingGuides,
            'remaining_payment_slots' =>
                $remainingPaymentSlots,
            'operation' => $operation,
            'message' => $allowed
                ? $this->noticeMessage(
                    $status,
                    $generated,
                    $reserved,
                    $limit,
                    $operation
                )
                : $this->blockedMessage(
                    $status,
                    $limit
                ),
        ];
    }

    private function noticeMessage(
        string $status,
        int $generated,
        int $reserved,
        int $limit,
        string $operation
    ): ?string {
        if (
            $status
            === B2cIdentityVerification::STATUS_APPROVED
        ) {
            return null;
        }

        if (
            $operation === 'payment'
            && ($generated + $reserved) === ($limit - 1)
        ) {
            return 'Esta es tu última guía disponible '
                . 'antes de completar la verificación '
                . 'de identidad.';
        }

        if (
            $status
            === B2cIdentityVerification::STATUS_PENDING
        ) {
            return 'Tus documentos están en revisión. '
                . 'Puedes continuar mientras tengas '
                . 'un lugar disponible.';
        }

        return 'Puedes generar hasta '
            . $limit
            . ' guías antes de que la verificación '
            . 'de identidad sea obligatoria.';
    }

    private function blockedMessage(
        string $status,
        int $limit
    ): string {
        if (
            $status
            === B2cIdentityVerification::STATUS_REJECTED
        ) {
            return 'Tu identidad fue rechazada. '
                . 'Contacta a soporte para revisar '
                . 'tu expediente antes de continuar.';
        }

        if (
            $status
            === B2cIdentityVerification::STATUS_PENDING
        ) {
            return 'Ya alcanzaste el límite de '
                . $limit
                . ' guías sin identidad aprobada. '
                . 'Tus documentos están en revisión.';
        }

        if (
            $status
            === B2cIdentityVerification::STATUS_CORRECTION
        ) {
            return 'Ya alcanzaste el límite de '
                . $limit
                . ' guías sin identidad aprobada. '
                . 'Actualiza los documentos observados '
                . 'para continuar.';
        }

        return 'Ya alcanzaste el límite de '
            . $limit
            . ' guías sin verificar tu identidad. '
            . 'Completa el proceso para continuar.';
    }

    private function identityStatus(
        int $userId
    ): string {
        $identity =
            B2cIdentityVerification::query()
                ->where('user_id', $userId)
                ->first();

        $status = strtoupper(
            trim(
                (string) (
                    $identity?->status
                    ?: B2cIdentityVerification::STATUS_UNVERIFIED
                )
            )
        );

        return $status === 'EN_REVISION'
            ? B2cIdentityVerification::STATUS_PENDING
            : $status;
    }

    private function limit(): int
    {
        return max(
            1,
            (int) config(
                'b2c_identity.unverified_guide_limit',
                2
            )
        );
    }

    private function lockUser(
        int $userId
    ): void {
        User::query()
            ->whereKey($userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function throwWhenBlocked(
        array $assessment
    ): void {
        if (!$assessment['allowed']) {
            throw new IdentityVerificationRequiredException(
                $assessment
            );
        }
    }
}
