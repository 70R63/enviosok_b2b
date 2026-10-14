<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Crm\ProviderCrmAdapterResolver;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ProviderCrmOutbox
{
    public function publish(ProviderFulfillment $b): void
    {
        if (! Schema::hasTable('network_provider_integrations')) {
            return;
        }
        if (DB::transactionLevel() > 0) {
            throw new \LogicException('Publish requires business commit.');
        }
        $connection = ProviderQuoteSnapshot::findOrFail($b->quote_snapshot_id)->provider_connection_id;
        foreach (ProviderIntegration::where('provider_connection_id', $connection)->where('status', 'active')->get() as $i) {
            DB::transaction(function () use ($b, $i): void {
                $i = ProviderIntegration::whereKey($i->id)->lockForUpdate()->firstOrFail();
                if ($i->status !== 'active' || DB::table('tenant_api_webhook_deliveries')->where('provider_integration_id', $i->id)->where('resource_uuid', $b->uuid)->where('event_type', 'provider.fulfillment.created')->exists()) {
                    return;
                }
                $event = (string) Str::uuid();
                $payload = ['event_id' => $event, 'type' => 'provider.fulfillment.created', 'data' => app(ProviderApiOperations::class)->safe($b)];
                DB::table('tenant_api_webhook_deliveries')->insert(['event_id' => $event, 'tenant_id' => $i->provider_tenant_id, 'api_client_id' => $i->api_client_id,
                    'webhook_endpoint_id' => null, 'provider_integration_id' => $i->id, 'provider_fulfillment_id' => $b->id,
                    'event_type' => 'provider.fulfillment.created', 'resource_type' => 'provider_fulfillment', 'resource_uuid' => $b->uuid,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'attempt' => 0, 'status' => 'PR_PENDING', 'next_retry_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            });
        }
    }

    public function deliver(int $id): void
    {
        if (DB::transactionLevel() > 0) {
            throw new \LogicException('External delivery cannot run inside a business transaction.');
        }
        $lease = (string) Str::uuid();
        $row = DB::transaction(function () use ($id, $lease) {
            $d = DB::table('tenant_api_webhook_deliveries')->where('id', $id)->lockForUpdate()->first();
            if (! $d || ! $d->provider_integration_id || ! in_array($d->status, ['PR_PENDING', 'PR_RETRY', 'PR_SENDING'], true)
                || ($d->next_retry_at && $d->next_retry_at > now()->toDateTimeString()) || ($d->lease_expires_at && $d->lease_expires_at > now()->toDateTimeString())) {
                return null;
            }
            $i = ProviderIntegration::findOrFail($d->provider_integration_id);
            $adapter = app(ProviderCrmAdapterResolver::class)->resolve($i);
            if (! $i->operational() || ! $adapter->enabled()) {
                DB::table('tenant_api_webhook_deliveries')->where('id', $id)->update(['status' => 'PR_DISABLED', 'next_retry_at' => null]);

                return null;
            }
            if ($d->attempt >= config('provider_crm.max_attempts', 6)) {
                DB::table('tenant_api_webhook_deliveries')->where('id', $id)->update(['status' => 'PR_ATTENTION']);

                return null;
            }
            DB::table('tenant_api_webhook_deliveries')->where('id', $id)->update(['status' => 'PR_SENDING', 'attempt' => $d->attempt + 1, 'lease_token' => $lease, 'lease_expires_at' => now()->addSeconds(config('provider_crm.lease_seconds', 120))]);

            return $d;
        });
        if (! $row) {
            return;
        }
        try {
            $i = ProviderIntegration::findOrFail($row->provider_integration_id);
            $reference = app(ProviderCrmAdapterResolver::class)->resolve($i)->deliver(json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR), $i);
            DB::transaction(function () use ($id, $lease, $row, $i, $reference) {
                $locked = DB::table('tenant_api_webhook_deliveries')->where('id', $id)->where('lease_token', $lease)->lockForUpdate()->first();
                if (! $locked) {
                    return;
                }
                if ($reference !== null) {
                    Validator::make(['reference' => $reference], ['reference' => 'required|string|max:160|regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'])->validate();
                    app(ProviderApiOperations::class)->correlate($i->id, $row->provider_fulfillment_id, $reference);
                }
                DB::table('tenant_api_webhook_deliveries')->where('id', $id)->where('lease_token', $lease)->update(['status' => 'PR_DELIVERED', 'delivered_at' => now(), 'next_retry_at' => null, 'lease_token' => null, 'lease_expires_at' => null, 'last_error_safe' => null]);
            });
        } catch (\Throwable) {
            $attempt = $row->attempt + 1;
            $terminal = $attempt >= config('provider_crm.max_attempts', 6);
            DB::table('tenant_api_webhook_deliveries')->where('id', $id)->where('lease_token', $lease)->update(['status' => $terminal ? 'PR_ATTENTION' : 'PR_RETRY',
                'next_retry_at' => $terminal ? null : now()->addMinutes(2 ** min($attempt,6)), 'last_error_safe' => 'Provider delivery unavailable', 'lease_token' => null, 'lease_expires_at' => null]);
        }
    }
}
