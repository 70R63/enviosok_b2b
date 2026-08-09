<?php

namespace App\Domain\Shipping\LastMile;

use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\LastMile\Models\LocalShipmentDeliveryRequirement;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DeliveryRequirementService
{
    public const POLICY_TYPES = [
        'RECIPIENT_ONLY' => ['RECIPIENT'],
        'AUTHORIZED_PERSON' => ['RECIPIENT', 'FAMILY', 'AUTHORIZED_OTHER'],
        'ANY_PERSON_AT_ADDRESS' => LocalDeliveryProof::RECEIVER_TYPES,
        'RECEPTION_OR_SECURITY' => ['RECIPIENT', 'RECEPTION', 'SECURITY'],
    ];

    public function defaultOption(Tenant $tenant): TenantDeliveryProofOption
    {
        return DB::transaction(function () use ($tenant): TenantDeliveryProofOption {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $option = TenantDeliveryProofOption::where('tenant_id', $tenant->id)->where('is_active', true)->where('is_default', true)->first();
            if ($option) return $option;
            $option = TenantDeliveryProofOption::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
            if ($option) {
                $option->update(['is_default' => true]);
                return $option;
            }
            $fallback = TenantDeliveryProofOption::firstOrNew(['tenant_id' => $tenant->id, 'code' => 'DEFAULT']);
            $fallback->fill([
                'tenant_id' => $tenant->id, 'code' => 'DEFAULT', 'name' => 'Evidencia completa',
                'description' => 'Compatibilidad: receptor, fotografía, firma y GPS.',
                'require_receiver_name' => true, 'require_receiver_type' => true, 'require_signature' => true,
                'require_photo' => true, 'require_gps' => true, 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS',
                'max_delivery_attempts' => 2, 'surcharge_amount' => 0, 'currency' => 'MXN',
                'is_default' => true, 'is_active' => true, 'sort_order' => 0,
            ]); $fallback->save(); return $fallback;
        });
    }

    public function option(Tenant $tenant, ?string $uuid): TenantDeliveryProofOption
    {
        if (! $uuid) return $this->defaultOption($tenant);
        return TenantDeliveryProofOption::where('tenant_id', $tenant->id)->where('uuid', $uuid)->where('is_active', true)->firstOrFail();
    }

    public function snapshot(LocalShipment $shipment, TenantDeliveryProofOption $option): LocalShipmentDeliveryRequirement
    {
        abort_unless((int) $shipment->tenant_id === (int) $option->tenant_id, 404);
        return LocalShipmentDeliveryRequirement::firstOrCreate(['local_shipment_id' => $shipment->id], [
            'tenant_id' => $shipment->tenant_id, 'proof_option_code_snapshot' => $option->code,
            'proof_option_name_snapshot' => $option->name, 'require_receiver_name' => $option->require_receiver_name,
            'require_receiver_type' => $option->require_receiver_type, 'require_signature' => $option->require_signature,
            'require_photo' => $option->require_photo, 'require_gps' => $option->require_gps,
            'receiver_policy' => $option->receiver_policy, 'max_delivery_attempts' => $option->max_delivery_attempts,
            'surcharge_amount_snapshot' => $option->surcharge_amount, 'currency_snapshot' => $option->currency,
            'created_at' => now(),
        ]);
    }

    public function snapshotFromContract(LocalShipment $shipment, array $snapshot): LocalShipmentDeliveryRequirement
    {
        return LocalShipmentDeliveryRequirement::firstOrCreate(['local_shipment_id' => $shipment->id], [
            'tenant_id' => $shipment->tenant_id, 'proof_option_code_snapshot' => (string) ($snapshot['code'] ?? 'CHECKOUT'),
            'proof_option_name_snapshot' => (string) ($snapshot['name'] ?? 'Modalidad contratada'),
            'require_receiver_name' => (bool) ($snapshot['require_receiver_name'] ?? false), 'require_receiver_type' => (bool) ($snapshot['require_receiver_type'] ?? false),
            'require_signature' => (bool) ($snapshot['require_signature'] ?? false), 'require_photo' => (bool) ($snapshot['require_photo'] ?? false),
            'require_gps' => (bool) ($snapshot['require_gps'] ?? false), 'receiver_policy' => (string) ($snapshot['receiver_policy'] ?? 'ANY_PERSON_AT_ADDRESS'),
            'max_delivery_attempts' => (int) ($snapshot['max_delivery_attempts'] ?? 1), 'surcharge_amount_snapshot' => (float) ($snapshot['surcharge_amount'] ?? 0),
            'currency_snapshot' => (string) ($snapshot['currency'] ?? 'MXN'), 'created_at' => now(),
        ]);
    }

    public function forShipment(LocalShipment $shipment): LocalShipmentDeliveryRequirement
    {
        $existing = LocalShipmentDeliveryRequirement::where('local_shipment_id', $shipment->id)->first();
        if ($existing) return $existing;
        return $this->snapshot($shipment, $this->defaultOption($shipment->tenant));
    }

    public function proofRules(LocalShipmentDeliveryRequirement $requirement): array
    {
        $required = fn (bool $value) => $value ? 'required' : 'nullable';
        return [
            'received_by_name' => [$required($requirement->require_receiver_name), 'string', 'max:120'],
            'receiver_type' => [$required($requirement->require_receiver_type), Rule::in(self::POLICY_TYPES[$requirement->receiver_policy] ?? [])],
            'receiver_notes' => ['nullable', 'string', 'max:300'],
            'photo' => [$required($requirement->require_photo), 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signature' => [$required($requirement->require_signature), 'string', 'max:750000'],
            'latitude' => [$required($requirement->require_gps), 'numeric', 'between:-90,90'],
            'longitude' => [$required($requirement->require_gps), 'numeric', 'between:-180,180'],
            'accuracy_meters' => [$required($requirement->require_gps), 'numeric', 'min:0', 'max:10000'],
        ];
    }

    public function assertEvidence(LocalShipmentDeliveryRequirement $requirement, array $data, mixed $photo, ?string $signature): void
    {
        $missing = [];
        if ($requirement->require_receiver_name && empty($data['received_by_name'])) $missing['received_by_name'] = 'Escribe el nombre de quien recibe.';
        if ($requirement->require_receiver_type && empty($data['receiver_type'])) $missing['receiver_type'] = 'Selecciona el tipo de receptor.';
        if ($requirement->require_photo && ! $photo) $missing['photo'] = 'Selecciona una fotografía de evidencia.';
        if ($requirement->require_signature && ! $signature) $missing['signature'] = 'Captura la firma de quien recibe.';
        if ($requirement->require_gps && (! isset($data['latitude'], $data['longitude'], $data['accuracy_meters']))) $missing['latitude'] = 'Captura la ubicación GPS para finalizar.';
        if ($missing) throw ValidationException::withMessages($missing);
    }

    public function attemptsRemaining(LocalShipment $shipment): int
    {
        $max = $this->forShipment($shipment)->max_delivery_attempts;
        return max(0, $max - $shipment->failedDeliveryAttempts()->count());
    }
}
