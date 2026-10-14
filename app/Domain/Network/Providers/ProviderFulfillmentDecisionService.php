<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderFulfillmentDecision;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Trusted tenant/actor supplied by an authenticated adapter, never request IDs. */
final class ProviderFulfillmentDecisionService
{
    public function authorize(Tenant $provider, User $actor): void
    {
        abort_unless($provider->status === 'active' && TenantMembership::query()
            ->where('tenant_id', $provider->id)->where('user_id', $actor->id)
            ->where('status', 'active')->whereIn('role', ['owner', 'admin', 'operator'])->exists(), 403);
    }

    public function decide(Tenant $provider, string $fulfillmentUuid, User $actor, string $status,
        ?string $reason = null, string $channel = 'TENANT_UI'): ProviderFulfillmentDecision
    {
        if (! in_array($status, ['ACCEPTED', 'REJECTED'], true) || ! in_array($channel, ['TENANT_UI', 'INTERNAL'], true)) {
            throw ValidationException::withMessages(['decision' => 'Decisión no válida.']);
        }
        $reason = $reason === null ? null : trim($reason);
        if ($reason === '') {
            $reason = null;
        }
        if (($status === 'ACCEPTED' && $reason !== null) || ($reason !== null
            && (mb_strlen($reason) > 500 || preg_match('/[\x00-\x1F\x7F]/u', $reason)))) {
            throw ValidationException::withMessages(['reason' => 'Usa un motivo de una sola línea, de hasta 500 caracteres.']);
        }

        return DB::transaction(function () use ($provider, $fulfillmentUuid, $actor, $status, $reason, $channel) {
            // Current reads revalidate authorization under concurrency; no TenantContext mutation.
            $tenant = Tenant::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            $membership = TenantMembership::where('tenant_id', $tenant->id)->where('user_id', $actor->id)
                ->lockForUpdate()->first();
            abort_unless($tenant->status === 'active' && $membership && $membership->status === 'active'
                && in_array($membership->role, ['owner', 'admin', 'operator'], true), 403);
            $fulfillment = ProviderFulfillment::where('provider_tenant_id', $tenant->id)
                ->where('uuid', $fulfillmentUuid)->lockForUpdate()->firstOrFail();
            $existing = ProviderFulfillmentDecision::where('provider_fulfillment_id', $fulfillment->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status !== $status) {
                    throw ValidationException::withMessages(['decision' => 'La solicitud ya tiene una decisión definitiva.']);
                }

                return $existing;
            }
            $decision = new ProviderFulfillmentDecision;
            $decision->forceFill(['provider_fulfillment_id' => $fulfillment->id, 'status' => $status,
                'actor_user_id' => $actor->id, 'channel' => $channel, 'rejection_reason' => $reason, 'decided_at' => now()])->save();

            return $decision;
        }, 5);
    }
}
