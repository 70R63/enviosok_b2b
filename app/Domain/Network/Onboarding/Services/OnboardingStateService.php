<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OnboardingStateService
{
    private const TRANSITIONS = [
        SaasOnboardingApplication::DRAFT => [
            SaasOnboardingApplication::PENDING_PAYMENT,
            SaasOnboardingApplication::TRIAL_READY,
            SaasOnboardingApplication::CANCELLED,
        ],
        SaasOnboardingApplication::PENDING_PAYMENT => [
            SaasOnboardingApplication::PAID,
            SaasOnboardingApplication::CANCELLED,
            SaasOnboardingApplication::EXPIRED,
        ],
        SaasOnboardingApplication::EXPIRED => [SaasOnboardingApplication::PAID],
        SaasOnboardingApplication::PAID => [SaasOnboardingApplication::PROVISIONING],
        SaasOnboardingApplication::TRIAL_READY => [SaasOnboardingApplication::PROVISIONING],
        SaasOnboardingApplication::PROVISIONING => [
            SaasOnboardingApplication::ACTIVE,
            SaasOnboardingApplication::FAILED,
        ],
        SaasOnboardingApplication::FAILED => [SaasOnboardingApplication::PROVISIONING],
        SaasOnboardingApplication::ACTIVE => [],
        SaasOnboardingApplication::CANCELLED => [],
    ];

    public function __construct(private OnboardingMetadataSanitizer $sanitizer) {}

    public function transition(
        SaasOnboardingApplication $application,
        string $toStatus,
        string $event,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $correlationKey = null,
        ?int $paymentEventId = null,
        array $metadata = [],
    ): SaasOnboardingApplication {
        return DB::transaction(function () use (
            $application, $toStatus, $event, $actorType, $actorId,
            $correlationKey, $paymentEventId, $metadata
        ): SaasOnboardingApplication {
            $locked = SaasOnboardingApplication::query()
                ->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $toStatus) {
                return $locked;
            }

            if (
                $toStatus === SaasOnboardingApplication::PAID &&
                ($actorType !== 'verified_payment' || blank($correlationKey))
            ) {
                throw ValidationException::withMessages([
                    'status' => 'PAID requiere evidencia correlacionada de un pago verificado.',
                ]);
            }

            if (!in_array($toStatus, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Transición inválida: {$locked->status} -> {$toStatus}.",
                ]);
            }

            $fromStatus = $locked->status;
            $updates = [
                'status' => $toStatus,
                'lock_version' => $locked->lock_version + 1,
            ];

            $timestamp = match ($toStatus) {
                SaasOnboardingApplication::PAID => 'paid_at',
                SaasOnboardingApplication::PROVISIONING => 'provisioning_started_at',
                SaasOnboardingApplication::ACTIVE => 'activated_at',
                SaasOnboardingApplication::FAILED => 'failed_at',
                SaasOnboardingApplication::CANCELLED => 'cancelled_at',
                SaasOnboardingApplication::EXPIRED => 'expired_at',
                default => null,
            };
            if ($timestamp) {
                $updates[$timestamp] = now();
            }

            if ($toStatus === SaasOnboardingApplication::PROVISIONING) {
                $updates['failure_code'] = null;
                $updates['failure_context_json'] = null;
            }
            if ($toStatus === SaasOnboardingApplication::FAILED) {
                $failureCode = strtoupper((string) ($metadata['failure_code'] ?? 'PROVISIONING_FAILED'));
                $updates['failure_code'] = preg_match('/^[A-Z0-9_:-]{1,80}$/', $failureCode)
                    ? $failureCode
                    : 'PROVISIONING_FAILED';
                $updates['failure_context_json'] = $this->sanitizer->sanitize(
                    (array) ($metadata['failure_context'] ?? [])
                );
            }

            $locked->update($updates);
            $locked->events()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'event' => $event,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'correlation_key' => $correlationKey,
                'payment_event_id' => $paymentEventId,
                'metadata_json' => $metadata ? $this->sanitizer->sanitize($metadata) : null,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
