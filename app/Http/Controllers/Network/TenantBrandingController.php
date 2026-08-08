<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\Tenant;use App\Http\Controllers\Controller;use App\Http\Requests\Network\UpdateTenantBrandingRequest;
final class TenantBrandingController extends Controller
{
 public function edit(Tenant $tenant){return view('network.tenants.branding',compact('tenant'));}
 public function update(UpdateTenantBrandingRequest $request,Tenant $tenant){$data=$request->safe()->except(['logo','favicon']);if($request->hasFile('logo'))$data['logo_path']=$request->file('logo')->store("tenant-branding/{$tenant->uuid}",'public');if($request->hasFile('favicon'))$data['favicon_path']=$request->file('favicon')->store("tenant-branding/{$tenant->uuid}",'public');$tenant->branding()->updateOrCreate(['tenant_id'=>$tenant->id],$data);return back()->with('success','Branding actualizado.');}
}
