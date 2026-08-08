<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreTenantRequest;
use App\Http\Requests\Network\UpdateTenantRequest;
use App\Domain\Network\Catalog\Models\Plan;
class TenantController extends Controller
{
    public function index() { return view('network.tenants.index',['tenants'=>Tenant::with('currentPlan')->latest()->paginate(20)]); }
    public function create() { return view('network.tenants.create'); }
    public function store(StoreTenantRequest $request)
    {
        $tenant=Tenant::create($request->validated());
        return redirect()->route('network.tenants.show',$tenant)->with('success','Tenant creado correctamente.');
    }
    public function show(Tenant $tenant) { $tenant->load(['currentPlan.modules'=>fn($q)=>$q->wherePivot('is_included',true)->orderBy('sort_order')]); return view('network.tenants.show',compact('tenant')); }
    public function edit(Tenant $tenant) { return view('network.tenants.edit',['tenant'=>$tenant,'plans'=>Plan::orderBy('name')->get()]); }
    public function update(UpdateTenantRequest $request,Tenant $tenant)
    {
        $uuid=$tenant->uuid;$tenant->update($request->validated());
        abort_unless($tenant->uuid===$uuid,500,'Tenant UUID is immutable.');
        return redirect()->route('network.tenants.show',$tenant)->with('success','Tenant actualizado correctamente.');
    }
}
