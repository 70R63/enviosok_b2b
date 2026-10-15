<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalTrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProviderPickupConfirmationService
{
    public function confirm(Tenant $provider, ProviderFulfillment $fulfillment, User $actor, array $confirmation): LocalTrackingEvent
    {
        return DB::transaction(function () use ($provider, $fulfillment, $actor, $confirmation) {
            $provider = Tenant::whereKey($provider->id)->lockForUpdate()->firstOrFail();
            // Reuses the membership lock and roles used for provider dispatch.
            abort_unless($provider->status === 'active', 403);
            app(ProviderDispatchEligibility::class)->authorizeActor($provider->id, $actor->id);
            $bridge = ProviderFulfillment::whereKey($fulfillment->id)->where('provider_tenant_id', $provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($bridge->acceptanceStatus() === 'ACCEPTED', 422, 'Acepta la solicitud antes de confirmar la recolección.');
            $shipment = LocalShipment::whereKey($bridge->local_shipment_id)->where('tenant_id', $provider->id)
                ->where('tenant_operation_id', $bridge->tenant_operation_id)->lockForUpdate()->firstOrFail();
            $operation = TenantOperation::whereKey($bridge->tenant_operation_id)->where('tenant_id', $provider->id)->lockForUpdate()->firstOrFail();
            $preference = app(ProviderPickupPreference::class);
            $data = $preference->normalize($confirmation, 'confirmed_date', false);
            $existing = $shipment->events()->where('event_code', 'PICKUP_CONFIRMED')->first();
            if ($existing) {
                // MySQL JSON can reorder object keys; retries compare canonical values.
                $stored = $existing->metadata ?? [];
                $expected = $data;
                ksort($stored);
                ksort($expected);
                if ($stored !== $expected) {
                    throw ValidationException::withMessages(['pickup_confirmation' => 'La recolección ya tiene una confirmación.']);
                }
                return $existing;
            }
            abort_unless($shipment->status === 'READY_FOR_PICKUP', 422, 'La recolección ya avanzó.');
            $data = $preference->normalize($confirmation, 'confirmed_date');
            $event = $shipment->events()->create(['status' => $shipment->status, 'event_code' => 'PICKUP_CONFIRMED',
                'occurred_at' => now(), 'created_by_user_id' => $actor->id, 'metadata' => $data]);
            $operation->update(['metadata' => array_replace($operation->metadata ?? [], ['pickup_confirmation' => $data])]);

            return $event;
        }, 5);
    }
}
