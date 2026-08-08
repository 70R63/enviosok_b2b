<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreTenantRequest;
class TenantController extends Controller
{
    public function index() { return view('network.tenants.index',['tenants'=>Tenant::latest()->paginate(20)]); }
    public function create() { return view('network.tenants.create'); }
    public function store(StoreTenantRequest $request)
    {
        $tenant=Tenant::create($request->validated());
        return redirect()->route('network.tenants.show',$tenant)->with('success','Tenant creado correctamente.');
    }
    public function show(Tenant $tenant) { return view('network.tenants.show',compact('tenant')); }
}
