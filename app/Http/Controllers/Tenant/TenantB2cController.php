<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Exceptions\TenantQuoteUnavailableException;
use App\Domain\Network\Channels\B2C\TenantB2cQuoteService;
use App\Domain\Network\Channels\B2C\CustomerCheckoutService;
use App\Domain\Network\Channels\B2C\TenantCustomerAddressService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\Models\TenantCustomerProfile;
use App\Domain\Shipping\Local\Models\LocalShippingQuoteSnapshot;
use App\Services\ZigoPostalCodeService;

final class TenantB2cController extends Controller
{
    public function create(TenantContext $context)
    {
        return view('tenant.b2c.quote', ['tenant' => $context->tenant()->load('branding'), 'result' => null, 'customerPortal' => false]);
    }

    public function store(Request $request, TenantContext $context, TenantB2cQuoteService $quotes)
    {
        $customerPortal = $request->is('app/*');
        if ($request->input('tipo_envio') === 'sobre') {
            $request->merge(['peso' => 1, 'length' => null, 'width' => null, 'height' => null]);
        }
        $rules = [
            'cp_origen' => ['required', 'regex:/^\d{5}$/'], 'cp_destino' => ['required', 'regex:/^\d{5}$/'],
            'origin_settlement' => ['required','string','max:160'], 'origin_address' => ['nullable','string','max:255'],
            'origin_city' => ['nullable','string','max:160'], 'origin_state' => ['nullable','string','max:160'],
            'destination_settlement' => ['required','string','max:160'], 'destination_address' => ['nullable','string','max:255'],
            'destination_city' => ['nullable','string','max:160'], 'destination_state' => ['nullable','string','max:160'],
            'tipo_envio' => ['required', 'in:sobre,caja'], 'peso' => ['required', 'numeric', 'min:0.1', 'max:40'],
            'length' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:60'],
            'width' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:50'],
            'height' => ['required_if:tipo_envio,caja', 'nullable', 'numeric', 'min:1', 'max:40'],
        ];
        if ($customerPortal) foreach (['sender','recipient'] as $person) $rules += [
            $person.'.name'=>['required','string','max:120'], $person.'.phone'=>['required','string','max:30'], $person.'.email'=>['nullable','email','max:160'],
            $person.'.street'=>['required','string','max:180'], $person.'.exterior'=>['required','string','max:40'], $person.'.interior'=>['nullable','string','max:40'], $person.'.references'=>['nullable','string','max:300'],
        ];
        if ($customerPortal) $rules += ['save_sender_address'=>['nullable','boolean'],'save_recipient_address'=>['nullable','boolean']];
        $data = $request->validate($rules, [
            'peso.max'=>'El peso máximo permitido es 40 kg.',
            'length.max'=>'El largo máximo permitido es 60 cm.',
            'width.max'=>'El ancho máximo permitido es 50 cm.',
            'height.max'=>'El alto máximo permitido es 40 cm.',
        ]);
        $realWeight = (float) $data['peso'];
        if ($data['tipo_envio'] === 'sobre') {
            $data['peso'] = 1;
            $data['length'] = $data['width'] = $data['height'] = null;
        } else {
            $volumetric = ((float) $data['length'] * (float) $data['width'] * (float) $data['height']) / 5000;
            $data['peso'] = (float) ceil(max((float) $data['peso'], $volumetric));
        }
        if ($customerPortal) {
            $data['origin_address'] = $this->streetAddress($data['sender']);
            $data['destination_address'] = $this->streetAddress($data['recipient']);
        }
        $tenant = $context->tenant()->load('branding');
        try {
            $result = $quotes->quote($tenant, $data);
        } catch (TenantQuoteUnavailableException) {
            return back()->withInput()->withErrors([
                'quote' => 'No fue posible obtener tarifas disponibles para este envío en este momento. Verifica los datos o intenta nuevamente.',
            ]);
        }

        $request->session()->put('tenant_customer.quote_result', [
            'tenant_id' => $tenant->id, 'operation_uuid' => $result['operation']->uuid,
            'options' => collect($result['options'])->values()->all(),
        ]);
        if ($customerPortal) {
            $operation = $result['operation'];
            $operation->update(['customer_profile_id'=>$request->attributes->get('customer_profile')->id,'created_by_user_id'=>$request->user()->id,'metadata'=>($operation->metadata??[])+['shipping_draft'=>['sender'=>$data['sender'],'recipient'=>$data['recipient'],'save_sender_address'=>(bool)($data['save_sender_address']??false),'save_recipient_address'=>(bool)($data['save_recipient_address']??false),'real_weight'=>$realWeight]]]);
            $addresses = \App\Domain\Network\Channels\B2C\Models\TenantCustomerAddress::where('tenant_id',$context->id())->where('customer_profile_id',$request->attributes->get('customer_profile')->id)->where('is_active',true)->orderBy('alias')->get();
        }
        return view($customerPortal ? 'tenant.b2c.quote' : 'tenant.home', ['tenant' => $tenant, 'result' => $result, 'customerPortal' => $customerPortal, 'preview'=>false,
            'originAddresses'=>$customerPortal?$addresses->whereIn('address_type',['origin','both'])->values():collect(), 'destinationAddresses'=>$customerPortal?$addresses->whereIn('address_type',['destination','both'])->values():collect(),
            'pickupEnabled'=>$customerPortal && app(\App\Domain\Network\Billing\EntitlementService::class)->has($tenant,'DRIVER')]);
    }

    public function select(Request $request, TenantContext $context, CustomerCheckoutService $checkouts, TenantCustomerAddressService $addresses)
    {
        $data = $request->validate(['operation_uuid' => ['required', 'uuid'], 'option' => ['required', 'integer', 'min:0']]);
        $quote = $request->session()->get('tenant_customer.quote_result');
        abort_unless(is_array($quote) && (int) ($quote['tenant_id'] ?? 0) === $context->id()
            && hash_equals((string) ($quote['operation_uuid'] ?? ''), $data['operation_uuid']), 404);
        $option = $quote['options'][$data['option']] ?? null;
        abort_unless(is_array($option), 404);
        $operation = TenantOperation::where('tenant_id', $context->id())->where('uuid', $data['operation_uuid'])->where('status', 'quoted')->firstOrFail();
        $snapshot=LocalShippingQuoteSnapshot::where('tenant_id',$context->id())->where('uuid',$option['snapshot_uuid'])->where('expires_at','>',now())->firstOrFail();
        $preliminary = (bool) data_get($snapshot->matched_tariff, '_quote_context.preliminary', false);
        $commercial = data_get($snapshot->matched_tariff, '_commercial', ['subtotal'=>(string)$snapshot->amount,'tax'=>'0.00','total'=>(string)$snapshot->amount]);
        $metadata=$operation->metadata??[];$metadata['selected_quote_snapshot_uuid']=$snapshot->uuid;$metadata['preliminary_quote_snapshot_uuid']=$preliminary?$snapshot->uuid:null;$metadata['selected_quote']=['service'=>$option['service'],'provider'=>$option['provider']??'ZIGO Local','subtotal'=>$commercial['subtotal'],'tax'=>$commercial['tax'],'price'=>$commercial['total'],'currency'=>$snapshot->currency,'preliminary'=>$preliminary];$metadata['final_price']=$preliminary?null:(string)$snapshot->amount;$metadata['origin_postal_code']=$snapshot->origin['postal_code'];$metadata['destination_postal_code']=$snapshot->destination['postal_code'];$metadata['quoted_package']=['type'=>$snapshot->package_type,'weight'=>(string)$snapshot->weight_kg]+($snapshot->dimensions??[]);
        $operation->update(['provider'=>'ZIGO_LOCAL','service_code'=>$option['service_code'],'metadata'=>$metadata]);
        if (auth()->check()) {
            $profile = TenantCustomerProfile::where('tenant_id', $context->id())->where('user_id', auth()->id())->where('status', 'active')->first();
            if ($profile) {
                $metadata = $operation->fresh()->metadata ?? [];
                $draft = $metadata['shipping_draft'] ?? null;
                if (is_array($draft)) {
                    $draft['sender']['address'] = $snapshot->origin;
                    $draft['recipient']['address'] = $snapshot->destination;
                    $draft['package'] = ['type'=>$snapshot->package_type,'weight'=>(string)$snapshot->weight_kg,'real_weight'=>(string)($draft['real_weight']??$snapshot->weight_kg)]+($snapshot->dimensions??[]);
                    $metadata['shipping_data'] = ['sender'=>$draft['sender'],'recipient'=>$draft['recipient'],'package'=>$draft['package']];
                    $metadata['selected_quote']['provider'] = $option['provider'] ?? 'ZIGO Local';
                    $metadata['selected_quote']['origin'] = $snapshot->origin;
                    $metadata['selected_quote']['destination'] = $snapshot->destination;
                    foreach (['sender' => 'origin', 'recipient' => 'destination'] as $person => $addressType) {
                        if (! ($draft['save_'.$person.'_address'] ?? false)) continue;
                        $route = $draft[$person]['address'];
                        $addresses->save($context->tenant(), $profile, [
                            'address_type' => $addressType,
                            'alias' => ucfirst($addressType).' · '.$draft[$person]['name'],
                            'contact_name' => $draft[$person]['name'],
                            'phone' => $draft[$person]['phone'],
                            'email' => $draft[$person]['email'] ?? null,
                            'street' => $draft[$person]['street'],
                            'exterior' => $draft[$person]['exterior'],
                            'interior' => $draft[$person]['interior'] ?? null,
                            'postal_code' => $route['postal_code'],
                            'settlement' => $route['settlement'],
                            'references' => $draft[$person]['references'] ?? null,
                            'is_default_origin' => false,
                            'is_default_destination' => false,
                        ]);
                    }
                }
                $operation->update(['customer_profile_id' => $profile->id, 'created_by_user_id' => auth()->id(), 'metadata'=>$metadata]);
                $request->session()->put('tenant_customer.active_operation', $operation->uuid);
                if (is_array($draft)) {
                    $checkout = $checkouts->create($context->tenant(), $profile, $operation->fresh());
                    return redirect('/app/checkout/'.$checkout->uuid.'/resumen')->with('success', 'Servicio seleccionado. Revisa los datos antes de pagar.');
                }
                return redirect('/app/envio/nuevo')->with('success', 'Servicio seleccionado. Completa los datos del envío.');
            }
        }
        $request->session()->put('tenant_customer.pending_quote', ['tenant_id' => $context->id(), 'operation_uuid' => $operation->uuid]);
        $request->session()->put('url.intended', '/app/envio/nuevo');
        return redirect('/ingresar')->with('status', 'Tu cotización está guardada. Inicia sesión o crea tu cuenta para continuar.');
    }

    public function postal(string $postalCode, ZigoPostalCodeService $postal){$result=$postal->lookup($postalCode);return response()->json($result,$result['status']??200);}

    private function streetAddress(array $person): string
    {
        return trim($person['street'].' '.$person['exterior'].(filled($person['interior'] ?? null) ? ' Int. '.$person['interior'] : ''));
    }

    public function tracking(TenantContext $context)
    {
        return view('tenant.b2c.tracking', ['tenant' => $context->tenant()->load('branding')]);
    }

    public function track(string $tracking, TenantContext $context)
    {
        $shipment = LocalShipment::with('events')->where('tenant_id', $context->tenant()->id)->where('tracking_number', $tracking)->firstOrFail();

        return view('tenant.b2c.tracking-result', [
            'tenant' => $context->tenant()->load('branding'),
            'trackingResult' => [
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'events' => $shipment->events->map(fn ($event) => [
                    'status' => $event->status,
                    'occurred_at' => $event->occurred_at,
                ]),
            ],
        ]);
    }
}
