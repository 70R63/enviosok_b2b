<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Catalog\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StorePlanRequest;
use App\Http\Requests\Network\UpdatePlanRequest;
use App\Domain\Network\Catalog\Models\Module;
use Illuminate\Support\Facades\DB;
use App\Domain\Network\Commerce\Models\{NetworkCommercialProduct,TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Operations\NetworkAdminAuditService;
class PlanController extends Controller
{
    public function index() { return view('network.plans.index',['plans'=>Plan::with(['modules','commercialOffers'])->withCount(['modules as included_modules_count'=>fn($q)=>$q->where('network_plan_modules.is_included',true)])->latest()->paginate(20)]); }
    public function create() { return view('network.plans.create'); }
    public function store(StorePlanRequest $request,NetworkAdminAuditService$audit)
    {
        $plan=Plan::create($request->validated());
        $audit->record($request->user()->id,'plan.created',$plan,[],$plan->toArray());
        return redirect()->route('network.plans.show',$plan)->with('success','Plan creado correctamente.');
    }
    public function show(Plan $plan) { $plan->load(['modules'=>fn($q)=>$q->orderBy('sort_order')]); return view('network.plans.show',compact('plan')); }
    public function edit(Plan $plan) { $plan->load('modules'); return view('network.plans.edit',['plan'=>$plan,'moduleGroups'=>$this->moduleGroups()]); }
    public function update(UpdatePlanRequest $request, Plan $plan,NetworkAdminAuditService$audit)
    {
        $validated=$request->validated();$submitted=collect($validated['modules'])->keyBy('id');unset($validated['modules']);
        $before=$plan->toArray();DB::transaction(function()use($plan,$validated,$submitted):void{$plan->update($validated);$sync=[];foreach(Module::all() as $module){$row=$submitted->get($module->id,[]);$sync[$module->id]=['is_included'=>(bool)($row['included']??false),'limit_value'=>$row['limit_value']??null];}$plan->modules()->sync($sync);});$audit->record($request->user()->id,'plan.updated',$plan,$before,$plan->fresh()->toArray());
        return redirect()->route('network.plans.show',$plan)->with('success','Plan y módulos actualizados correctamente.');
    }
    public function destroy(Plan$plan,\Illuminate\Http\Request$request,NetworkAdminAuditService$audit)
    {
        $used=$plan->subscriptions()->exists()||$plan->tenants()->exists()||(\Illuminate\Support\Facades\Schema::hasTable('tenant_saas_orders')&&TenantSaasOrder::whereHas('product',fn($q)=>$q->where('plan_id',$plan->id))->exists())||(\Illuminate\Support\Facades\Schema::hasTable('saas_onboarding_applications')&&SaasOnboardingApplication::where('selected_plan_id',$plan->id)->exists());
        if($used){$before=$plan->status;$plan->update(['status'=>'inactive']);NetworkCommercialProduct::where('plan_id',$plan->id)->update(['is_active'=>false,'is_public'=>false,'archived_at'=>now()]);$audit->record($request->user()->id,'plan.archived',$plan,['status'=>$before],['status'=>'inactive']);return back()->with('success','Plan utilizado: fue archivado, no eliminado.');}
        $audit->record($request->user()->id,'plan.deleted',$plan,$plan->toArray(),[]);$plan->modules()->detach();NetworkCommercialProduct::where('plan_id',$plan->id)->delete();$plan->delete();return redirect()->route('network.plans.index')->with('success','Plan no utilizado eliminado.');
    }
    private function moduleGroups(): array
    {
        $labels=['B2C'=>'CANALES','B2B'=>'CANALES','SHIPPING'=>'LOGÍSTICA','TRACKING'=>'LOGÍSTICA','CRM'=>'NEGOCIO','INVOICING'=>'NEGOCIO','DRIVER'=>'OPERACIÓN','COMMERCE'=>'COMERCIAL','MARKETING'=>'COMERCIAL','GPS'=>'VISIBILIDAD','WAREHOUSE'=>'FULFILLMENT','API'=>'INTEGRACIONES'];
        return Module::orderBy('sort_order')->orderBy('name')->get()->groupBy(fn(Module $module)=>$labels[$module->code]??strtoupper($module->type))->all();
    }
}
