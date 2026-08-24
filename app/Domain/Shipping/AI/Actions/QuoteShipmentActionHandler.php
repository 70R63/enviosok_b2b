<?php

namespace App\Domain\Shipping\AI\Actions;

use App\Domain\AI\Actions\Contracts\ActionHandler;
use App\Domain\AI\Actions\Data\{ActionExecutionContext, ActionResultData};
use App\Domain\Network\Channels\B2C\TenantB2cQuoteService;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class QuoteShipmentActionHandler implements ActionHandler
{
    public function execute(ActionExecutionContext $context, array $arguments): ActionResultData
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Logistics Actions must run outside transactions.');
        Validator::make($arguments,['origin_postal_code'=>['required','regex:/^[0-9]{5}$/'],'destination_postal_code'=>['required','regex:/^[0-9]{5}$/'],'origin_settlement'=>'required|string|max:160','destination_settlement'=>'required|string|max:160','package.type'=>'required|in:sobre,caja','package.weight'=>'required|numeric|min:0.01|max:1000','package.length'=>'required|numeric|min:1','package.width'=>'required|numeric|min:1','package.height'=>'required|numeric|min:1'])->validate();
        $tenant = Tenant::query()->findOrFail($context->actionRun->tenant_id);
        $quoted = app(TenantB2cQuoteService::class)->quote($tenant, [
            'cp_origen'=>$arguments['origin_postal_code'], 'cp_destino'=>$arguments['destination_postal_code'],
            'origin_settlement'=>$arguments['origin_settlement'], 'destination_settlement'=>$arguments['destination_settlement'],
            'tipo_envio'=>$arguments['package']['type'], 'peso'=>$arguments['package']['weight'],
            'length'=>$arguments['package']['length']??null, 'width'=>$arguments['package']['width']??null, 'height'=>$arguments['package']['height']??null,
        ]);
        return new ActionResultData([
            'quote_reference'=>$quoted['operation']->uuid,
            'options'=>collect($quoted['options'])->map(fn(array$o)=>[
                'option_reference'=>$o['snapshot_uuid'],'service_name'=>$o['service'],'amount'=>(float)$o['price'],
                'currency'=>$o['currency'],'estimated_delivery'=>$o['sla'] ?? 'No disponible',
            ])->values()->all(),
        ]);
    }
}
