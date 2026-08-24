<?php

namespace App\Domain\Shipping\AI\Actions;

use App\Domain\AI\Actions\Contracts\ActionHandler;
use App\Domain\AI\Actions\Data\{ActionExecutionContext, ActionResultData};
use App\Domain\Network\Channels\B2C\{TenantB2cQuoteService, TenantOperationService};
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateShipmentGuideActionHandler implements ActionHandler
{
    public function __construct(private TenantB2cQuoteService $quotes, private TenantOperationService $operations, private LocalShipmentService $shipments) {}

    public function execute(ActionExecutionContext $context, array $arguments): ActionResultData
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Logistics Actions must run outside transactions.');
        Validator::make($arguments,['quote_reference'=>'required|uuid','option_reference'=>'required|uuid','sender.name'=>'required|string|max:160','sender.phone'=>'required|string|max:30','sender.street'=>'required|string|max:180','sender.exterior'=>'required|string|max:40','sender.interior'=>'nullable|string|max:40','sender.postal_code'=>['required','regex:/^[0-9]{5}$/'],'recipient.name'=>'required|string|max:160','recipient.phone'=>'required|string|max:30','recipient.street'=>'required|string|max:180','recipient.exterior'=>'required|string|max:40','recipient.interior'=>'nullable|string|max:40','recipient.postal_code'=>['required','regex:/^[0-9]{5}$/'],'customer_reference'=>'nullable|string|max:160'])->validate();
        $tenant = Tenant::query()->findOrFail($context->actionRun->tenant_id);
        $operation = TenantOperation::query()->where('tenant_id',$tenant->id)->where('uuid',$arguments['quote_reference'])->firstOrFail();
        $allowed = $operation->metadata['quote_snapshot_uuids'] ?? [];
        if (! in_array($arguments['option_reference'], $allowed, true)) throw new \DomainException('The quote option is not authoritative.');
        $snapshot = LocalShippingQuoteSnapshot::query()->where('tenant_id',$tenant->id)->where('uuid',$arguments['option_reference'])->firstOrFail();
        if ($snapshot->expires_at->isPast()) throw new \DomainException('The quote has expired.');
        $this->assertRoute($snapshot, $arguments);
        $final = (bool) data_get($snapshot->matched_tariff, '_quote_context.preliminary', false)
            ? $this->quotes->finalize($tenant, $snapshot, $arguments['sender'], $arguments['recipient'])
            : $snapshot;
        $this->assertRoute($final, $arguments);
        $this->assertCommerciallyUnchanged($snapshot, $final);
        $service = $final->service()->where('tenant_id',$tenant->id)->where('status','active')->where('published',true)->firstOrFail();
        $metadata = $operation->metadata ?? [];
        $metadata['preliminary_quote_snapshot_uuid'] = $snapshot->uuid;
        $metadata['selected_quote_snapshot_uuid'] = $final->uuid;
        $metadata['selected_quote'] = ['snapshot_uuid'=>$final->uuid,'service_code'=>$service->code,'amount'=>(string)$final->amount,'currency'=>$final->currency,'preliminary'=>false];
        $operation->update(['provider'=>'ZIGO_LOCAL','service_code'=>$service->code,'metadata'=>$metadata]);
        $operation = $this->operations->confirm($tenant, $operation->fresh());
        $shipment = $this->shipments->create($tenant,$operation,[
            'sender'=>$this->person($arguments['sender'], $final->origin),'recipient'=>$this->person($arguments['recipient'], $final->destination),
            'package'=>['type'=>$final->package_type,'weight'=>(string)$final->weight_kg,'dimensions'=>$final->dimensions],
            'pricing'=>['final_price'=>(float)$final->amount,'currency'=>$final->currency,'quote_snapshot_uuid'=>$final->uuid],
            'reference'=>$arguments['customer_reference']??null,
        ],$context->sourceMessage->created_by_user_id);
        return new ActionResultData(['shipment_reference'=>$shipment->uuid,'guide_reference'=>$shipment->uuid,'tracking_reference'=>$shipment->tracking_number,'status'=>$shipment->status,'document_available'=>true]);
    }

    private function assertRoute(LocalShippingQuoteSnapshot $snapshot, array $arguments): void
    {
        if (! hash_equals((string) data_get($snapshot->origin, 'postal_code'), trim((string) $arguments['sender']['postal_code']))
            || ! hash_equals((string) data_get($snapshot->destination, 'postal_code'), trim((string) $arguments['recipient']['postal_code']))) {
            throw new \DomainException('The shipment route does not match the authoritative quote.');
        }
    }

    private function assertCommerciallyUnchanged(LocalShippingQuoteSnapshot $authorized, LocalShippingQuoteSnapshot $final): void
    {
        if ((int) $authorized->service_id !== (int) $final->service_id
            || bccomp((string) $authorized->amount, (string) $final->amount, 2) !== 0
            || ! hash_equals((string) $authorized->currency, (string) $final->currency)) {
            throw new \DomainException('The final quote changed and requires a new confirmation.');
        }
    }

    private function person(array $person, array $route): array
    {
        return $person + ['address'=>$route];
    }
}
