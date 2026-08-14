<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerAddress;
use App\Domain\Network\Channels\B2C\TenantCustomerAddressService;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CustomerAddressController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $profile=$request->attributes->get('customer_profile');
        $addresses=TenantCustomerAddress::where('tenant_id',$context->id())->where('customer_profile_id',$profile->id)->where('is_active',true)->orderByDesc('is_default_origin')->orderByDesc('is_default_destination')->orderBy('alias')->get();
        return view('tenant.customer.addresses.index',['tenant'=>$context->tenant()->load('branding'),'addresses'=>$addresses]);
    }

    public function create(TenantContext $context) { return view('tenant.customer.addresses.form',['tenant'=>$context->tenant()->load('branding'),'address'=>null]); }

    public function store(Request $request, TenantContext $context, TenantCustomerAddressService $service)
    {
        $service->save($context->tenant(),$request->attributes->get('customer_profile'),$this->validated($request));
        return redirect()->route('tenant.customer.app.addresses.index')->with('success','Dirección guardada.');
    }

    public function edit(Request $request, TenantContext $context, TenantCustomerAddress $address, TenantCustomerAddressService $service)
    {
        $service->assertOwned($address,$context->tenant(),$request->attributes->get('customer_profile'));
        return view('tenant.customer.addresses.form',['tenant'=>$context->tenant()->load('branding'),'address'=>$address]);
    }

    public function update(Request $request, TenantContext $context, TenantCustomerAddress $address, TenantCustomerAddressService $service)
    {
        $service->save($context->tenant(),$request->attributes->get('customer_profile'),$this->validated($request),$address);
        return redirect()->route('tenant.customer.app.addresses.index')->with('success','Dirección actualizada.');
    }

    public function destroy(Request $request, TenantContext $context, TenantCustomerAddress $address, TenantCustomerAddressService $service)
    {
        $service->deactivate($address,$context->tenant(),$request->attributes->get('customer_profile'));
        return back()->with('success','Dirección eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'address_type'=>['required',Rule::in(TenantCustomerAddress::TYPES)],'alias'=>['required','string','max:100'],'contact_name'=>['required','string','max:120'],
            'company'=>['nullable','string','max:160'],'phone'=>['required','string','max:30'],'email'=>['nullable','email','max:160'],
            'street'=>['required','string','max:180'],'exterior'=>['required','string','max:40'],'interior'=>['nullable','string','max:40'],
            'postal_code'=>['required','regex:/^\d{5}$/'],'settlement'=>['required','string','max:160'],'references'=>['nullable','string','max:300'],
            'is_default_origin'=>['nullable','boolean'],'is_default_destination'=>['nullable','boolean'],
        ]);
    }
}
