<?php

namespace App\Domain\Network\Providers;

use App\Domain\ApiHub\ApiIdempotencyService;
use App\Domain\ApiHub\Models\TenantApiAuditEvent;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ProviderApiOperations
{
    public const SCOPES = ['accept' => 'provider:fulfillment:decide', 'reject' => 'provider:fulfillment:decide', 'dispatch' => 'provider:dispatch:write',
        'tracking' => 'provider:tracking:write', 'failed' => 'provider:tracking:write', 'pod' => 'provider:pod:write'];

    public function detail(ProviderApiPrincipal $p, string $uuid): array
    {
        $i = $p->authorize('provider:fulfillment:read');

        return $this->safe($p->fulfillment($uuid), $i->id);
    }

    public function safe(ProviderFulfillment $b, ?int $integration = null): array
    {
        $s = $b->shipment;

        return ['fulfillment_uuid' => $b->uuid, 'reference' => $s->tracking_number, 'external_reference' => $integration ? DB::table('network_provider_external_references')->where('integration_id', $integration)->where('provider_fulfillment_id', $b->id)->value('external_reference') : null, 'acceptance' => $b->acceptanceStatus(),
            'status' => $s->status, 'service' => ['code' => $s->service_code, 'name' => $s->guide_snapshot['service_name']],
            'pickup' => $s->sender_snapshot, 'delivery' => $s->recipient_snapshot,
            'package' => array_intersect_key($s->package_snapshot, array_flip(['type', 'weight', 'length', 'width', 'height', 'dimensions', 'content'])),
            'driver_uuid' => $s->activeDriverAssignment?->driverProfile?->uuid];
    }

    public function listing(ProviderApiPrincipal $p, int $page = 1): array
    {
        $i = $p->authorize('provider:fulfillment:read');

        return ProviderFulfillment::where('provider_tenant_id', $i->provider_tenant_id)
            ->whereIn('quote_snapshot_id', ProviderQuoteSnapshot::where('provider_connection_id', $i->provider_connection_id)->select('id'))
            ->orderBy('id')->paginate(30, ['*'], 'page', $page)->map(fn ($b) => $this->safe($b, $i->id))->all();
    }

    public function command(ProviderApiPrincipal $p, string $uuid, string $action, string $key, array $data,
        ?UploadedFile $photo = null, ?string $signature = null, string $requestId = ''): array
    {
        abort_unless(isset(self::SCOPES[$action]), 404);
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,159}$/']])->validate();
        $p->authorize(self::SCOPES[$action]);
        $b = $p->fulfillment($uuid);
        $fingerprint = ['fulfillment_uuid' => $uuid, 'payload' => $data, 'photo_sha256' => $photo ? hash_file('sha256', $photo->getRealPath()) : null, 'signature_sha256' => $signature ? hash('sha256', $signature) : null];

        return DB::transaction(function () use ($p, $uuid, $action, $key, $data, $photo, $signature, $requestId, $b, $fingerprint) {
            $i = $p->integration(true); // Serializes same-client first inserts around reused ApiIdempotencyService.

            return app(ApiIdempotencyService::class)->execute($i->provider_tenant_id, $i->api_client_id, 'provider.'.$action, $key, $fingerprint,
                function () use ($p, $uuid, $action, $data, $photo, $signature, $requestId, $b, $i) {
                    $p->authorize(self::SCOPES[$action], $b->shipment);
                    $allowed = ['external_reference'];
                    abort_unless(! $photo || in_array($action, ['pod', 'failed'], true), 422);
                    $allowed = array_merge($allowed, match ($action) {
                        'reject' => ['reason'],'dispatch' => ['driver_uuid'],'tracking' => ['status'],
                        'failed' => ['reason', 'notes', 'latitude', 'longitude', 'accuracy_meters'],
                        'pod' => ['received_by_name', 'receiver_type', 'receiver_notes', 'latitude', 'longitude', 'accuracy_meters', 'signature'],default => []
                    });
                    abort_unless(! array_diff(array_keys($data), $allowed), 422);
                    $valid = Validator::make($data, ['external_reference' => 'nullable|string|max:160|regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
                        'reason' => 'nullable|string|max:500', 'driver_uuid' => $action === 'dispatch' ? 'required|uuid' : 'sometimes|uuid', 'status' => $action === 'tracking' ? 'required|string|max:40' : 'sometimes|string|max:40'])->validate();
                    if (! empty($valid['external_reference'])) {
                        $this->correlate($i->id, $b->id, $valid['external_reference']);
                    }
                    if (in_array($action, ['accept', 'reject'], true)) {
                        app(ProviderFulfillmentDecisionService::class)->decide(Tenant::findOrFail($i->provider_tenant_id), $uuid, $p, strtoupper($action) === 'ACCEPT' ? 'ACCEPTED' : 'REJECTED', $data['reason'] ?? null);
                    } elseif ($action === 'dispatch') {
                        app(DriverAssignmentService::class)->assign(Tenant::findOrFail($i->provider_tenant_id), $b->shipment,
                            DriverProfile::where('tenant_id', $i->provider_tenant_id)->where('uuid', $data['driver_uuid'] ?? '')->firstOrFail(), $p);
                    } else {
                        $assignment = DriverAssignment::where('tenant_id', $i->provider_tenant_id)->where('local_shipment_id', $b->local_shipment_id)
                            ->whereIn('status', $action === 'pod' ? ['ACTIVE', 'COMPLETED'] : ['ACTIVE'])->latest('id')->firstOrFail();
                        $service = app(ProviderDriverExecutionService::class);
                        if ($action === 'tracking') {
                            $service->transition($assignment, $p, $data['status'] ?? '');
                        } elseif ($action === 'failed') {
                            $service->fail($assignment, $p, $data, $photo);
                        } else {
                            $service->deliver($assignment, $p, $data, $photo, $signature);
                        }
                    }
                    TenantApiAuditEvent::create(['tenant_id' => $i->provider_tenant_id, 'api_client_id' => $i->api_client_id, 'api_key_id' => $p->keyId(),
                        'actor_user_id' => null, 'channel' => 'CRM_API', 'action' => 'provider.'.$action,
                        'metadata' => ['fulfillment_uuid' => $uuid, 'request_id' => $requestId], 'created_at' => now()]);

                    return ['status' => 200, 'body' => ['data' => $this->safe($b->fresh(), $i->id)]];
                });
        });
    }

    public function correlate(int $integration, int $fulfillment, string $reference): void
    {
        $old = DB::table('network_provider_external_references')->where('integration_id', $integration)->where('provider_fulfillment_id', $fulfillment)->first();
        if ($old) {
            abort_unless($old->external_reference === $reference, 409);

            return;
        }
        abort_if(DB::table('network_provider_external_references')->where('integration_id',$integration)->where('external_reference',$reference)->exists(),409);
        DB::table('network_provider_external_references')->insert(['integration_id' => $integration, 'provider_fulfillment_id' => $fulfillment, 'external_reference' => $reference, 'created_at' => now(), 'updated_at' => now()]);
    }
}
