<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\CustomerCheckoutService;
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerCheckout, TenantOperation};
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\Local\{LocalTrackingService};
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot;
use App\Http\Controllers\Controller;
use App\Services\ZigoPostalCodeService;
use App\Domain\Network\Channels\B2C\TenantB2cQuoteService;
use App\Domain\Network\Channels\B2C\TenantCustomerAddressService;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerJourneyController extends Controller
{
    public function shipping(Request $request, TenantContext $context)
    {
        $operation = $this->activeOperation($request, $context);
        if ($operation->customerCheckout) return redirect('/app/checkout/'.$operation->customerCheckout->uuid.'/resumen');
        $profile=$request->attributes->get('customer_profile');
        $addresses=TenantCustomerAddress::where('tenant_id',$context->id())->where('customer_profile_id',$profile->id)->where('is_active',true)->orderByDesc('is_default_origin')->orderByDesc('is_default_destination')->orderBy('alias')->get();
        return view('tenant.customer.journey.shipping', ['tenant' => $context->tenant()->load('branding'), 'operation' => $operation, 'package' => $operation->metadata['quoted_package'] ?? [], 'originAddresses'=>$addresses->whereIn('address_type',['origin','both'])->values(), 'destinationAddresses'=>$addresses->whereIn('address_type',['destination','both'])->values()]);
    }

    public function storeShipping(Request $request, TenantContext $context, CustomerCheckoutService $checkouts, TenantB2cQuoteService $quotes, TenantCustomerAddressService $addressBook)
    {
        $operation = $this->activeOperation($request, $context);
        $data = $request->validate([
            'sender.name' => ['required','string','max:120'], 'sender.phone' => ['required','string','max:30'], 'sender.email' => ['nullable','email','max:160'], 'sender.street' => ['required','string','max:180'], 'sender.exterior' => ['required','string','max:40'], 'sender.interior' => ['nullable','string','max:40'], 'sender.references' => ['nullable','string','max:300'],
            'recipient.name' => ['required','string','max:120'], 'recipient.phone' => ['required','string','max:30'], 'recipient.email' => ['nullable','email','max:160'], 'recipient.street' => ['required','string','max:180'], 'recipient.exterior' => ['required','string','max:40'], 'recipient.interior' => ['nullable','string','max:40'], 'recipient.references' => ['nullable','string','max:300'],
            'sender_address_uuid'=>['nullable','uuid'],'recipient_address_uuid'=>['nullable','uuid'],
            'save_sender_address'=>['nullable','boolean'],'save_recipient_address'=>['nullable','boolean'],
            'sender_address_alias'=>['nullable','string','max:100'],'recipient_address_alias'=>['nullable','string','max:100'],
            'reference' => ['nullable','string','max:100'],
        ]);
        $profile = $request->attributes->get('customer_profile');
        $checkout = DB::transaction(function () use ($operation, $data, $context, $profile, $checkouts, $quotes, $addressBook): TenantCustomerCheckout {
            $locked = TenantOperation::whereKey($operation->id)->where('status', 'quoted')->lockForUpdate()->firstOrFail();
            $existing = $locked->customerCheckout()->lockForUpdate()->first();
            if ($existing) return $existing;

            $metadata = $locked->metadata ?? [];
            $preliminary = LocalShippingQuoteSnapshot::where('tenant_id',$context->id())->where('uuid',$metadata['selected_quote_snapshot_uuid']??'')->firstOrFail();
            foreach (['sender'=>['uuid'=>'sender_address_uuid','side'=>'origin','snapshot'=>$preliminary->origin],'recipient'=>['uuid'=>'recipient_address_uuid','side'=>'destination','snapshot'=>$preliminary->destination]] as $key=>$config) {
                if (!filled($data[$config['uuid']]??null)) continue;
                $saved=$addressBook->compatible($context->tenant(),$profile,$data[$config['uuid']],$config['side'],$config['snapshot'],$config['uuid']);
                $data[$key]=array_merge($data[$key],['name'=>$saved->contact_name,'phone'=>$saved->phone,'email'=>$saved->email,'street'=>$saved->street,'exterior'=>$saved->exterior,'interior'=>$saved->interior,'references'=>$saved->references]);
            }
            $needsFinalization = (bool) data_get($preliminary->matched_tariff, '_quote_context.preliminary', false);
            $final = $needsFinalization ? $quotes->finalize($context->tenant(), $preliminary, $data['sender'], $data['recipient']) : $preliminary;
            $data['sender']['address']=$final->origin;$data['recipient']['address']=$final->destination;$data['package']=['type'=>$final->package_type,'weight'=>(string)$final->weight_kg]+($final->dimensions??[]);
            foreach (['sender'=>['side'=>'origin','save'=>'save_sender_address','uuid'=>'sender_address_uuid','alias'=>'sender_address_alias','type'=>'origin'],'recipient'=>['side'=>'destination','save'=>'save_recipient_address','uuid'=>'recipient_address_uuid','alias'=>'recipient_address_alias','type'=>'destination']] as $key=>$config) {
                if (!($data[$config['save']]??false) || filled($data[$config['uuid']]??null)) continue;
                $route=$final->{$config['side']};$person=$data[$key];
                $addressBook->save($context->tenant(),$profile,['address_type'=>$config['type'],'alias'=>$data[$config['alias']]??($key==='sender'?'Origen':'Destino').' · '.$person['name'],'contact_name'=>$person['name'],'company'=>null,'phone'=>$person['phone'],'email'=>$person['email']??null,'street'=>$person['street'],'exterior'=>$person['exterior'],'interior'=>$person['interior']??null,'postal_code'=>$route['postal_code'],'settlement'=>$route['settlement'],'references'=>$person['references']??null,'is_default_origin'=>false,'is_default_destination'=>false]);
            }
            if ($needsFinalization) $metadata['preliminary_quote_snapshot_uuid'] ??= $preliminary->uuid;
            $metadata['selected_quote_snapshot_uuid']=$final->uuid;
            $metadata['selected_quote']=array_merge($metadata['selected_quote']??[],['price'=>(string)$final->amount,'currency'=>$final->currency,'preliminary'=>false]);
            $metadata['final_price']=(string)$final->amount;
            unset($data['sender_address_uuid'],$data['recipient_address_uuid'],$data['save_sender_address'],$data['save_recipient_address'],$data['sender_address_alias'],$data['recipient_address_alias']);
            $metadata['shipping_data'] = $data;
            $locked->update(['metadata' => $metadata]);
            return $checkouts->create($context->tenant(), $profile, $locked->fresh());
        });
        return redirect('/app/checkout/'.$checkout->uuid.'/resumen');
    }

    public function evidence(Request $request, TenantContext $context)
    {
        $operation = $this->activeOperation($request, $context);
        abort_unless(isset($operation->metadata['shipping_data']), 409);
        $options = TenantDeliveryProofOption::where('tenant_id', $context->id())->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        return view('tenant.customer.journey.evidence', ['tenant' => $context->tenant()->load('branding'), 'operation' => $operation, 'options' => $options]);
    }

    public function storeEvidence(Request $request, TenantContext $context, CustomerCheckoutService $checkouts)
    {
        $operation = $this->activeOperation($request, $context);
        $data = $request->validate(['proof_option_uuid' => ['required','uuid']]);
        $proof = TenantDeliveryProofOption::where('tenant_id', $context->id())->where('uuid', $data['proof_option_uuid'])->where('is_active', true)->firstOrFail();
        $checkout = $checkouts->create($context->tenant(), $request->attributes->get('customer_profile'), $operation, $proof);
        return redirect('/app/checkout/'.$checkout->uuid.'/resumen');
    }

    public function summary(Request $request, string $checkout, TenantContext $context)
    {
        $item = $this->checkout($request, $context, $checkout);
        return view('tenant.customer.journey.summary', ['tenant' => $context->tenant()->load('branding'), 'checkout' => $item]);
    }

    public function continuePayment(Request $request, string $checkout, TenantContext $context, CustomerCheckoutService $checkouts)
    {
        $item = $checkouts->pending($this->checkout($request, $context, $checkout));
        return redirect('/app/checkout/'.$item->uuid.'/pago');
    }

    public function payment(Request $request, string $checkout, TenantContext $context)
    {
        $item = $this->checkout($request, $context, $checkout);
        if ($item->expires_at?->isPast() && $item->status !== 'PAID') { $item->update(['status' => 'EXPIRED']); $item->refresh(); }
        $hasPayments = \Illuminate\Support\Facades\Schema::hasTable('tenant_payment_connections');
        $connection = $hasPayments ? \App\Domain\Payments\Models\TenantPaymentConnection::where('tenant_id', $context->id())->where('provider', 'MERCADO_PAGO')->first() : null;
        $attempt = $hasPayments && \Illuminate\Support\Facades\Schema::hasTable('tenant_payment_attempts') ? \App\Domain\Payments\Models\TenantPaymentAttempt::where('checkout_id', $item->id)->latest('id')->first() : null;
        return view('tenant.customer.journey.payment', ['tenant' => $context->tenant()->load('branding'), 'checkout' => $item, 'connection' => $connection, 'attempt' => $attempt]);
    }

    public function pickup(Request $request, string $shipment, TenantContext $context, LocalTrackingService $tracking, \App\Domain\Shipping\LastMile\DriverDispatchService $dispatch)
    {
        $profile = $request->attributes->get('customer_profile');
        $item = LocalShipment::query()->select('local_shipments.*')->join('network_tenant_operations as owned_ops','owned_ops.id','=','local_shipments.tenant_operation_id')
            ->where('local_shipments.tenant_id',$context->id())->where('owned_ops.customer_profile_id',$profile->id)->where('local_shipments.uuid',$shipment)->firstOrFail();
        if (! in_array($item->status, ['CREATED','READY_FOR_PICKUP'], true)) throw ValidationException::withMessages(['pickup' => 'Este envío ya no permite solicitar recolección.']);
        try { $tracking->transition($item, 'READY_FOR_PICKUP', auth()->id(), 'Recolección solicitada.'); $dispatch->autoAssign($context->tenant(), $item->fresh(), auth()->id()); }
        catch (\DomainException $e) { throw ValidationException::withMessages(['pickup' => $e->getMessage()]); }
        return back()->with('success', 'Recolección solicitada.');
    }

    private function activeOperation(Request $request, TenantContext $context): TenantOperation
    {
        $profile = $request->attributes->get('customer_profile');
        $uuid = (string) $request->session()->get('tenant_customer.active_operation', '');
        return TenantOperation::where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('uuid', $uuid)->where('status', 'quoted')->firstOrFail();
    }

    private function checkout(Request $request, TenantContext $context, string $uuid): TenantCustomerCheckout
    {
        $profile = $request->attributes->get('customer_profile');
        return TenantCustomerCheckout::with('operation')->where('tenant_id', $context->id())->where('customer_profile_id', $profile->id)->where('uuid', $uuid)->firstOrFail();
    }
}
