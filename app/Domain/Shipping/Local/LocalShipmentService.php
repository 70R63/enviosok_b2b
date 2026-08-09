<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class LocalShipmentService
{
    public function __construct(private DeliveryRequirementService $requirements) {}

    public function create(Tenant $tenant, TenantOperation $operation, array $data, ?int $userId = null): LocalShipment
    {
        abort_unless((int) $operation->tenant_id === (int) $tenant->id, 404);
        abort_unless($operation->status === 'confirmed' && strtoupper((string) $operation->provider) === 'ZIGO_LOCAL', 422);

        return DB::transaction(function () use ($tenant, $operation, $data, $userId) {
            $operation = TenantOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $operation->tenant_id === (int) $tenant->id, 404);
            abort_unless($operation->status === 'confirmed' && strtoupper((string) $operation->provider) === 'ZIGO_LOCAL', 422);
            $existing = LocalShipment::where('tenant_operation_id', $operation->id)->lockForUpdate()->first();
            if ($existing) {
                if (Schema::hasTable('local_shipment_delivery_requirements')) $this->requirements->forShipment($existing);
                return $existing;
            }
            $proofOption = Schema::hasTable('tenant_delivery_proof_options') ? $this->requirements->option($tenant, $data['delivery_proof_option_uuid'] ?? null) : null;
            $branding = $tenant->loadMissing('branding')->branding;
            $tracking = $this->trackingNumber();
            $guide = [
                'tracking_number' => $tracking, 'issued_at' => now()->toIso8601String(),
                'service_code' => $operation->service_code, 'provider' => config('zigo_local.provider_name', 'ZIGO Local'),
                'sender' => $data['sender'], 'recipient' => $data['recipient'], 'package' => $data['package'],
                'reference' => $data['reference'] ?? null,
                'branding' => ['brand_name' => $branding?->brand_name ?? $tenant->name, 'logo_path' => $branding?->logo_path, 'primary_color' => $branding?->primary_color],
            ];
            $shipment = LocalShipment::create([
                'tenant_id' => $tenant->id, 'tenant_operation_id' => $operation->id, 'tracking_number' => $tracking,
                'service_code' => (string) $operation->service_code, 'status' => 'CREATED',
                'sender_snapshot' => $data['sender'], 'recipient_snapshot' => $data['recipient'], 'package_snapshot' => $data['package'],
                'pricing_snapshot' => $data['pricing'], 'guide_snapshot' => $guide, 'created_by_user_id' => $userId,
            ]);
            $shipment->events()->create(['status' => 'CREATED', 'event_code' => 'SHIPMENT_CREATED', 'occurred_at' => now(), 'created_by_user_id' => $userId]);
            if ($proofOption) $this->requirements->snapshot($shipment, $proofOption);

            return $proofOption ? $shipment->load('deliveryRequirement') : $shipment;
        });
    }

    private function trackingNumber(): string
    {
        do {
            $tracking = 'ZL'.now()->format('ymd').Str::upper(Str::random(10));
        } while (LocalShipment::where('tracking_number', $tracking)->exists());

        return $tracking;
    }
}
