<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\Tenant;use App\Domain\Network\Operations\NetworkAdminAuditService;use App\Http\Controllers\Controller;use App\Http\Requests\Network\UpdateTenantBrandingRequest;
final class TenantBrandingController extends Controller
{
 public function edit(Tenant $tenant){return view('network.tenants.branding',compact('tenant'));}
 public function update(UpdateTenantBrandingRequest $request,Tenant $tenant,NetworkAdminAuditService$audit){$before=$tenant->branding?->toArray()??[];$data=$request->safe()->except(['logo','hero_image','favicon']);if($request->hasFile('logo'))$data['logo_path']=$request->file('logo')->store("tenant-branding/{$tenant->uuid}",'public');if($request->hasFile('hero_image'))$data['hero_image_path']=$request->file('hero_image')->store("tenant-branding/{$tenant->uuid}",'public');if($request->hasFile('favicon'))$data['favicon_path']=$request->file('favicon')->store("tenant-branding/{$tenant->uuid}",'public');$branding=$tenant->branding()->updateOrCreate(['tenant_id'=>$tenant->id],$data);$audit->record($request->user()->id,'branding.updated',$branding,$before,$branding->fresh()->toArray());return back()->with('success','Branding actualizado.');}
}
