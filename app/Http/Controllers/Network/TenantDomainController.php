<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\{Tenant,TenantDomain};use App\Http\Controllers\Controller;use App\Http\Requests\Network\{StoreTenantDomainRequest,UpdateTenantDomainRequest};use Illuminate\Support\Facades\DB;
final class TenantDomainController extends Controller
{
 public function index(Tenant $tenant){return view('network.tenants.domains',['tenant'=>$tenant,'domains'=>$tenant->domains()->orderByDesc('is_primary')->orderBy('environment')->get()]);}
 public function store(StoreTenantDomainRequest $request,Tenant $tenant){$data=$request->validated();$data['is_primary']=$request->boolean('is_primary');$data['verified_at']=$data['status']==='verified'?now():null;DB::transaction(function()use($tenant,$data){if($data['is_primary'])$tenant->domains()->where('environment',$data['environment'])->update(['is_primary'=>false]);$tenant->domains()->create($data);});return back()->with('success','Dominio registrado.');}
 public function update(UpdateTenantDomainRequest $request,Tenant $tenant,TenantDomain $domain){abort_unless($domain->tenant_id===$tenant->id,404);$data=$request->validated();$data['is_primary']=$request->boolean('is_primary');$data['verified_at']=$data['status']==='verified'?($domain->verified_at??now()):null;DB::transaction(function()use($tenant,$domain,$data){if($data['is_primary'])$tenant->domains()->where('environment',$data['environment'])->where('id','!=',$domain->id)->update(['is_primary'=>false]);$domain->update($data);});return back()->with('success','Dominio actualizado.');}
 public function disable(Tenant $tenant,TenantDomain $domain){abort_unless($domain->tenant_id===$tenant->id,404);$domain->update(['status'=>'disabled','is_primary'=>false]);return back()->with('success','Dominio deshabilitado.');}
}
