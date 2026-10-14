<?php

namespace App\Domain\Network\Providers;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProviderDispatchEligibility
{
    public function allows(LocalShipment $shipment): bool
    {
        $operation = $shipment->operation;
        if ($operation?->source_type !== 'provider_contract') {
            return true;
        }
        if (! Schema::hasTable('network_provider_fulfillment_decisions')) {
            return false;
        }

        return DB::table('network_provider_fulfillments as fulfillment')
            ->join('network_provider_fulfillment_decisions as decision', 'decision.provider_fulfillment_id', '=', 'fulfillment.id')
            ->where('fulfillment.local_shipment_id', $shipment->id)
            ->where('fulfillment.tenant_operation_id', $operation->id)
            ->where('fulfillment.provider_tenant_id', $shipment->tenant_id)->where('decision.status', 'ACCEPTED')->exists();
    }

    public function scope(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereHas('operation', fn (Builder $op) => $op->whereNull('source_type')->orWhere('source_type', '!=', 'provider_contract'));
            if (Schema::hasTable('network_provider_fulfillment_decisions')) {
                $q->orWhereExists(function ($sub): void {
                    $sub->selectRaw('1')->from('network_provider_fulfillments as fulfillment')
                        ->join('network_provider_fulfillment_decisions as decision', 'decision.provider_fulfillment_id', '=', 'fulfillment.id')
                        ->whereColumn('fulfillment.local_shipment_id', 'local_shipments.id')
                        ->whereColumn('fulfillment.tenant_operation_id', 'local_shipments.tenant_operation_id')
                        ->whereColumn('fulfillment.provider_tenant_id', 'local_shipments.tenant_id')->where('decision.status', 'ACCEPTED');
                });
            }
        });
    }
}
