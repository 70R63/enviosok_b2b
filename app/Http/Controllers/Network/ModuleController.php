<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreModuleRequest;
use App\Http\Requests\Network\UpdateModuleRequest;
use App\Domain\Network\Operations\NetworkAdminAuditService;
class ModuleController extends Controller
{
    public function index() { return view('network.modules.index',['modules'=>Module::orderBy('sort_order')->orderBy('name')->paginate(30)]); }
    public function create() { return view('network.modules.create'); }
    public function store(StoreModuleRequest $request,NetworkAdminAuditService$audit)
    {
        $data=$request->validated(); $data['is_active']=$request->boolean('is_active'); $module=Module::create($data);$audit->record($request->user()->id,'module.created',$module,[],$module->toArray());
        return redirect()->route('network.modules.index')->with('success','Módulo creado correctamente.');
    }
    public function edit(Module $module) { return view('network.modules.edit',compact('module')); }
    public function update(UpdateModuleRequest $request,Module $module,NetworkAdminAuditService$audit)
    {
        $data=$request->validated();$data['is_active']=$request->boolean('is_active');$before=$module->toArray();$module->update($data);$audit->record($request->user()->id,'module.updated',$module,$before,$module->fresh()->toArray());
        return redirect()->route('network.modules.index')->with('success','Módulo actualizado correctamente.');
    }
}
