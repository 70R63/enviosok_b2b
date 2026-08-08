<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StorePlanRequest;
use App\Http\Requests\Network\UpdatePlanRequest;
use App\Domain\Network\Catalog\Models\Module;
use Illuminate\Support\Facades\DB;
class PlanController extends Controller
{
    public function index() { return view('network.plans.index',['plans'=>Plan::withCount(['modules as included_modules_count'=>fn($q)=>$q->where('network_plan_modules.is_included',true)])->latest()->paginate(20)]); }
    public function create() { return view('network.plans.create'); }
    public function store(StorePlanRequest $request)
    {
        $plan=Plan::create($request->validated());
        return redirect()->route('network.plans.show',$plan)->with('success','Plan creado correctamente.');
    }
    public function show(Plan $plan) { $plan->load(['modules'=>fn($q)=>$q->orderBy('sort_order')]); return view('network.plans.show',compact('plan')); }
    public function edit(Plan $plan) { $plan->load('modules'); return view('network.plans.edit',['plan'=>$plan,'moduleGroups'=>$this->moduleGroups()]); }
    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $validated=$request->validated();$submitted=collect($validated['modules'])->keyBy('id');unset($validated['modules']);
        DB::transaction(function()use($plan,$validated,$submitted):void{$plan->update($validated);$sync=[];foreach(Module::all() as $module){$row=$submitted->get($module->id,[]);$sync[$module->id]=['is_included'=>(bool)($row['included']??false),'limit_value'=>$row['limit_value']??null];}$plan->modules()->sync($sync);});
        return redirect()->route('network.plans.show',$plan)->with('success','Plan y módulos actualizados correctamente.');
    }
    private function moduleGroups(): array
    {
        $labels=['B2C'=>'CANALES','B2B'=>'CANALES','SHIPPING'=>'LOGÍSTICA','TRACKING'=>'LOGÍSTICA','CRM'=>'NEGOCIO','INVOICING'=>'NEGOCIO','DRIVER'=>'OPERACIÓN','COMMERCE'=>'COMERCIAL','MARKETING'=>'COMERCIAL','GPS'=>'VISIBILIDAD','WAREHOUSE'=>'FULFILLMENT','API'=>'INTEGRACIONES'];
        return Module::orderBy('sort_order')->orderBy('name')->get()->groupBy(fn(Module $module)=>$labels[$module->code]??strtoupper($module->type))->all();
    }
}
