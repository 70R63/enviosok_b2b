<?php

namespace App\Console\Commands;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderPayableService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ProviderPayableReconcile extends Command
{
    protected $signature = 'network:provider-payables:reconcile {--limit=100}';

    protected $description = 'Recover missing contractual payables and financial projections; never moves money';

    public function handle(ProviderPayableService $service): int
    {
        if (! Schema::hasTable('network_provider_payables')) {
            return self::SUCCESS;
        }
        $decisionSource = DB::connection()->getDriverName() === 'sqlite' ? "('decision:' || d.uuid)" : "CONCAT('decision:', d.uuid)";
        $trackingSource = DB::connection()->getDriverName() === 'sqlite' ? "('tracking:' || t.id)" : "CONCAT('tracking:', t.id)";
        // Only unsynchronized sources; already-settled early rows cannot starve later work.
        $bridges = ProviderFulfillment::where(function ($q) use ($decisionSource, $trackingSource): void {
            $q->whereNotExists(fn ($s) => $s->selectRaw('1')->from('network_provider_payables')->whereColumn('provider_fulfillment_id', 'network_provider_fulfillments.id'))
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('network_provider_fulfillment_decisions as d')
                    ->whereColumn('d.provider_fulfillment_id', 'network_provider_fulfillments.id')
                    ->whereNotExists(fn ($e) => $e->selectRaw('1')->from('network_provider_payable_events as e')->join('network_provider_payables as p', 'p.id', '=', 'e.provider_payable_id')
                        ->whereColumn('p.provider_fulfillment_id', 'network_provider_fulfillments.id')->whereRaw('e.source_reference = '.$decisionSource)))
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('local_tracking_events as t')->whereColumn('t.local_shipment_id', 'network_provider_fulfillments.local_shipment_id')
                    ->whereIn('t.status', ['DELIVERED', 'DELIVERY_FAILED'])
                    ->whereNotExists(fn ($e) => $e->selectRaw('1')->from('network_provider_payable_events as e')->join('network_provider_payables as p', 'p.id', '=', 'e.provider_payable_id')
                        ->whereColumn('p.provider_fulfillment_id', 'network_provider_fulfillments.id')->whereRaw('e.source_reference = '.$trackingSource)));
        })->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get();
        $failed = false;
        foreach ($bridges as $bridge) {
            try {
                $service->sync($bridge);
            } catch (\Throwable) {
                $failed = true;
                Log::warning('provider.payable.recovery_required');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
