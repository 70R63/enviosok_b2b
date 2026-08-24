<?php

namespace App\Domain\Shipping\AI\Actions;

use App\Domain\AI\Actions\Contracts\ActionHandler;
use App\Domain\AI\Actions\Data\{ActionExecutionContext, ActionResultData};
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\LocalTrackingService;
use Illuminate\Support\Facades\DB;

final class TrackShipmentActionHandler implements ActionHandler
{
    public function __construct(private LocalTrackingService $tracking) {}
    public function execute(ActionExecutionContext $context, array $arguments): ActionResultData
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Logistics Actions must run outside transactions.');
        $tenant = Tenant::query()->findOrFail($context->actionRun->tenant_id);
        return new ActionResultData($this->tracking->read($tenant, $arguments['shipment_reference']));
    }
}
