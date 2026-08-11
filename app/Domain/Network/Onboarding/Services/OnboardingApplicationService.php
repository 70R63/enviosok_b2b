<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class OnboardingApplicationService
{
    public function __construct(
        private OnboardingCommercialSnapshotService $snapshots,
        private OnboardingMetadataSanitizer $sanitizer,
    ) {}

    public function createOrRecover(array $data, string $purchaseKey): SaasOnboardingApplication
    {
        $purchaseKey = trim($purchaseKey);
        if ($purchaseKey === '' || mb_strlen($purchaseKey) > 100) {
            throw ValidationException::withMessages(['purchase_key' => 'Purchase key inválida.']);
        }

        try {
            return DB::transaction(function () use ($data, $purchaseKey): SaasOnboardingApplication {
                $existing = SaasOnboardingApplication::where('purchase_key', $purchaseKey)->first();
                if ($existing) {
                    return $existing;
                }

                $attributes = Arr::only($data, [
                    'contact_name', 'contact_last_name', 'contact_email', 'contact_phone',
                    'company_name', 'company_legal_name', 'tax_id', 'selected_plan_id',
                    'billing_period', 'selected_modules_json', 'requested_operations',
                ]);
                $attributes['purchase_key'] = $purchaseKey;
                $attributes['status'] = SaasOnboardingApplication::DRAFT;
                $attributes['billing_period'] = $attributes['billing_period'] ?? 'monthly';

                return SaasOnboardingApplication::create($attributes);
            });
        } catch (QueryException $exception) {
            $winner = SaasOnboardingApplication::where('purchase_key', $purchaseKey)->first();
            if ($winner) {
                return $winner;
            }
            throw $exception;
        }
    }

    public function freezeCommercialSnapshot(
        SaasOnboardingApplication $application,
        array $selectedModuleIds = [],
        ?int $requestedOperations = null,
        string $taxRate = '0.00',
        ?string $planOfferUuid = null,
        ?string $operationOfferUuid = null,
    ): SaasOnboardingApplication {
        return DB::transaction(function () use (
            $application, $selectedModuleIds, $requestedOperations, $taxRate,
            $planOfferUuid, $operationOfferUuid
        ): SaasOnboardingApplication {
            $locked = SaasOnboardingApplication::query()
                ->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->commercial_snapshot_json !== null) {
                return $locked;
            }
            if ($locked->status !== SaasOnboardingApplication::DRAFT || !$locked->selected_plan_id) {
                throw ValidationException::withMessages([
                    'commercial_snapshot' => 'El onboarding no puede congelar su oferta comercial.',
                ]);
            }

            $snapshot = $this->snapshots->build(
                $locked->plan()->firstOrFail(),
                $locked->billing_period,
                $selectedModuleIds,
                $requestedOperations,
                $taxRate,
                $planOfferUuid,
                $operationOfferUuid,
            );
            $locked->update([
                'selected_modules_json' => $snapshot['modules'],
                'requested_operations' => $requestedOperations,
                'commercial_snapshot_json' => $snapshot,
                'subtotal' => $snapshot['subtotal'],
                'tax_amount' => $snapshot['tax_amount'],
                'total' => $snapshot['total'],
                'currency' => $snapshot['currency'],
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->events()->create([
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'event' => 'COMMERCIAL_SNAPSHOT_FROZEN',
                'actor_type' => 'system',
                'metadata_json' => $this->sanitizer->sanitize([
                    'snapshot_version' => $snapshot['version'],
                    'commercial_codes' => $snapshot['commercial_codes'],
                    'total' => $snapshot['total'],
                    'currency' => $snapshot['currency'],
                ]),
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function updateDraft(
        SaasOnboardingApplication $application,
        array $data,
        string $event,
        array $metadata = [],
    ): SaasOnboardingApplication {
        return DB::transaction(function () use ($application, $data, $event, $metadata): SaasOnboardingApplication {
            $locked = SaasOnboardingApplication::query()
                ->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== SaasOnboardingApplication::DRAFT || $locked->commercial_snapshot_json !== null) {
                throw ValidationException::withMessages(['onboarding' => 'La solicitud ya no admite cambios.']);
            }

            $updates = Arr::only($data, [
                'selected_plan_id', 'billing_period', 'selected_modules_json', 'requested_operations',
            ]);
            $updates['lock_version'] = $locked->lock_version + 1;
            $locked->update($updates);
            $locked->events()->create([
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'event' => $event,
                'actor_type' => 'public_session',
                'metadata_json' => $metadata ? $this->sanitizer->sanitize($metadata) : null,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
