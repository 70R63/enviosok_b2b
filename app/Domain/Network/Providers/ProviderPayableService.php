<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderPayable;
use App\Domain\Network\Providers\Models\ProviderPayableEvent;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ProviderPayableService
{
    /** Observer entry point: never makes a committed shipment/payment fail. Recovery repairs a missed projection. */
    public function afterCommit(int $fulfillmentId): void
    {
        DB::afterCommit(function () use ($fulfillmentId): void {
            try {
                if (! Schema::hasTable('network_provider_payables')) {
                    return;
                }
                $this->sync(ProviderFulfillment::findOrFail($fulfillmentId));
            } catch (\Throwable) {
                Log::warning('provider.payable.recovery_required');
            }
        });
    }

    public function afterShipmentCommit(int $shipmentId): void
    {
        DB::afterCommit(function () use ($shipmentId): void {
            try {
                if (! Schema::hasTable('network_provider_payables')) {
                    return;
                }
                $bridge = ProviderFulfillment::where('local_shipment_id', $shipmentId)->first();
                if ($bridge) {
                    $this->sync($bridge);
                }
            } catch (\Throwable) {
                Log::warning('provider.payable.recovery_required');
            }
        });
    }

    public function sync(ProviderFulfillment $fulfillment): ProviderPayable
    {
        return DB::transaction(function () use ($fulfillment) {
            $bridge = ProviderFulfillment::whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();
            $payable = ProviderPayable::where('provider_fulfillment_id', $bridge->id)->lockForUpdate()->first();
            if (! $payable) {
                $snapshot = ProviderQuoteSnapshot::findOrFail($bridge->quote_snapshot_id);
                if ($snapshot->capability !== 'LOCAL_SHIPPING' || ! BigDecimal::of($snapshot->provider_cost)->isGreaterThan('0')) {
                    throw new \DomainException('Invalid contractual amount.');
                }
                $payable = ProviderPayable::forceCreate(['provider_fulfillment_id' => $bridge->id, 'provider_contract_id' => $bridge->provider_contract_id,
                    'quote_snapshot_id' => $bridge->quote_snapshot_id, 'consumer_tenant_id' => $bridge->consumer_tenant_id, 'provider_tenant_id' => $bridge->provider_tenant_id,
                    'provider_net_amount' => $snapshot->provider_cost, 'provider_tax_amount' => null, 'provider_gross_amount' => null,
                    'currency' => $snapshot->currency, 'created_at' => now()]);
                $this->event($payable, 'fulfillment:'.$bridge->uuid, 'CREATED', 'PENDING', null, 'SYSTEM', $bridge->commercial_confirmed_at);
            }
            $decision = $bridge->decision;
            $expected = $decision ? ($decision->status === 'ACCEPTED' ? 'IN_SERVICE' : 'REVIEW') : 'PENDING';
            if ($decision) {
                $this->event($payable, 'decision:'.$decision->uuid, $decision->status, $decision->status === 'ACCEPTED' ? 'IN_SERVICE' : 'REVIEW',
                    $decision->actor_user_id, $decision->channel, $decision->decided_at);
            }
            foreach ($bridge->shipment->events()->whereIn('status', ['DELIVERED', 'DELIVERY_FAILED'])->orderBy('id')->get() as $event) {
                $expected = $event->status === 'DELIVERED' ? 'PAYABLE' : 'REVIEW';
                $this->event($payable, 'tracking:'.$event->id, $event->status, $event->status === 'DELIVERED' ? 'PAYABLE' : 'REVIEW',
                    $event->created_by_user_id, $event->metadata['channel'] ?? ($event->created_by_user_id ? 'TENANT_UI' : 'SYSTEM'), $event->occurred_at);
            }
            if ($decision?->status === 'REJECTED') {
                $expected = 'REVIEW';
            }
            if (BigDecimal::of($payable->remainingAmount())->isZero()) {
                $expected = 'SETTLED';
            }
            if ($payable->financialStatus() !== $expected) {
                // Late recovery of an older source must not regress the latest financial projection.
                $last = $payable->events()->latest('id')->value('id');
                $this->event($payable, 'reconcile:after:'.$last, 'RECONCILED', $expected, null, 'SYSTEM', now());
            }

            return $payable;
        }, 3);
    }

    public function event(ProviderPayable $payable, string $source, string $action, string $status, ?int $actor, string $channel, $at, ?int $settlement = null): void
    {
        if (DB::transactionLevel() < 1 || ! in_array($status, ['PENDING', 'IN_SERVICE', 'PAYABLE', 'REVIEW', 'SETTLED'], true)) {
            throw new \LogicException('Financial events require a locked transaction and canonical status.');
        }
        if ($payable->events()->where('source_reference', $source)->exists()) {
            return;
        }
        $previous = $payable->events()->latest('id')->value('status');
        if ($previous === 'SETTLED') {
            $status = 'SETTLED';
        }
        ProviderPayableEvent::forceCreate(['provider_payable_id' => $payable->id, 'source_reference' => $source, 'action' => $action,
            'previous_status' => $previous, 'status' => $status, 'actor_user_id' => $actor, 'channel' => $channel,
            'occurred_at' => $at, 'created_at' => now(), 'settlement_entry_id' => $settlement]);
    }
}
