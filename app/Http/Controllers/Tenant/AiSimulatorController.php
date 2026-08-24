<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\{Agent, AgentVersion};
use App\Domain\AI\Agents\Services\AgentVersionLifecycleService;
use App\Domain\AI\Simulator\Models\{SimulationRun, SimulationScenario};
use App\Domain\AI\Simulator\Services\{AiReadinessService, PublishReadyAgentVersionService, RunAgentSimulationService, SimulationScenarioMutationService};
use App\Domain\AI\Simulator\Support\SimulationScenarioDefinition;
use App\Domain\AI\Simulator\Support\ScenarioDefinitionFromForm;
use App\Domain\AI\Actions\ActionRegistry;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Support\AiLaunchpadHttpGate;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;

final class AiSimulatorController extends Controller
{
    public function show(Agent $agent, AiLaunchpadHttpGate $gate, AiReadinessService $readiness, ActionRegistry $actions)
    {
        $gate->ensure(auth()->user()); $gate->assertCurrentTenant($agent); $version = $this->candidate($agent);
        $tenant=Tenant::query()->findOrFail($agent->tenant_id);$trusted=$version?array_filter($actions->availableFor($tenant),fn($definition)=>in_array($definition->key,$version->contractVersion?->allowed_actions??[],true)):[];
        return view('tenant.admin.ai-agents.simulator', ['tenant'=>$tenant,'agent'=>$agent,'version'=>$version,'scenarios'=>SimulationScenario::query()->where('agent_id',$agent->id)->latest()->get(),'runs'=>SimulationRun::query()->where('agent_id',$agent->id)->with('cases.scenario')->latest()->limit(10)->get(),'readiness'=>$version?$readiness->evaluate($agent,$version):null,'trustedActions'=>$trusted,'maxTurns'=>(int)config('ai.simulator.max_turns_per_scenario',10)]);
    }
    public function store(Request $request, Agent $agent, AiLaunchpadHttpGate $gate, ScenarioDefinitionFromForm $builder, SimulationScenarioMutationService $mutations): RedirectResponse
    {
        $gate->ensure($request->user());$gate->assertCurrentTenant($agent);$version=$this->candidate($agent);abort_unless($version,404);$this->rejectExtra($request);
        $data=$this->validateForm($request);try{$definition=$builder->build($data['builder'],Tenant::query()->findOrFail($agent->tenant_id),$version);}catch(\InvalidArgumentException){throw ValidationException::withMessages(['turns'=>'Revisa los mensajes, la acción y el resultado simulado.']);}
        try{$mutations->create($request->user(),$agent,['name'=>trim($data['name']),'description'=>$data['description']??null,'enabled'=>$data['enabled'],'definition'=>$definition]);}catch(\DomainException$e){if($e->getMessage()==='simulation_scenario_limit')throw ValidationException::withMessages(['name'=>'Se alcanzó el máximo de escenarios.']);throw$e;}return back()->with('success','Escenario creado.');
    }
    public function update(Request $request, Agent $agent, SimulationScenario $scenario, AiLaunchpadHttpGate $gate, ScenarioDefinitionFromForm $builder, SimulationScenarioMutationService $mutations): RedirectResponse
    {
        $gate->ensure($request->user());$gate->assertCurrentTenant($agent);$gate->assertCurrentTenant($scenario);abort_unless($scenario->agent_id===$agent->id,404);$version=$this->candidate($agent);abort_unless($version,404);$this->rejectExtra($request);$data=$this->validateForm($request);
        try{$definition=$builder->build($data['builder'],Tenant::query()->findOrFail($agent->tenant_id),$version);}catch(\InvalidArgumentException){throw ValidationException::withMessages(['turns'=>'Revisa los mensajes, la acción y el resultado simulado.']);}$mutations->update($request->user(),$agent,$scenario,['name'=>trim($data['name']),'description'=>$data['description']??null,'enabled'=>$data['enabled'],'definition'=>$definition]);return back()->with('success','Escenario actualizado; la evidencia histórica se conserva.');
    }
    public function run(Request $request, Agent $agent, AiLaunchpadHttpGate $gate, RunAgentSimulationService $service): RedirectResponse
    {$gate->ensure($request->user());$gate->assertCurrentTenant($agent);$version=$this->candidate($agent);abort_unless($version,404);$service->runAll($request->user(),$agent,$version);return back()->with('success','Prueba completada.');}
    public function runOne(Request $request, Agent $agent, SimulationScenario $scenario, AiLaunchpadHttpGate $gate, RunAgentSimulationService $service): RedirectResponse
    {$gate->ensure($request->user());$gate->assertCurrentTenant($agent);$gate->assertCurrentTenant($scenario);abort_unless($scenario->agent_id===$agent->id,404);$version=$this->candidate($agent);abort_unless($version,404);$service->runAll($request->user(),$agent,$version,$scenario);return back()->with('success','Escenario probado.');}
    public function review(Request $request, Agent $agent, AiLaunchpadHttpGate $gate, AgentVersionLifecycleService $lifecycle): RedirectResponse
    {$gate->ensure($request->user());$gate->assertCurrentTenant($agent);$version=$this->candidate($agent);abort_unless($version?->status===AgentVersionStatus::Testing,404);$lifecycle->approve($request->user(),$version,['manual_review'=>true,'source'=>'ai_simulator']);return back()->with('success','Revisión humana registrada.');}
    public function publish(Request $request, Agent $agent, AiLaunchpadHttpGate $gate, PublishReadyAgentVersionService $service): RedirectResponse
    {$gate->ensure($request->user());$gate->assertCurrentTenant($agent);$version=AgentVersion::query()->where('agent_id',$agent->id)->where('status',AgentVersionStatus::Approved->value)->latest('version_number')->firstOrFail();try{$service->publish($request->user(),$agent,$version);}catch(\DomainException){throw ValidationException::withMessages(['publish'=>'La versión ya no está lista para publicar. Vuelve a ejecutar las pruebas.']);}return redirect()->route('tenant.admin.ai-agents.show',$agent)->with('success','Versión publicada.');}
    private function candidate(Agent $agent): ?AgentVersion{return AgentVersion::query()->where('agent_id',$agent->id)->whereIn('status',[AgentVersionStatus::Testing->value,AgentVersionStatus::Approved->value])->latest('version_number')->first();}
    private function rejectExtra(Request$request):void{$allowed=['_token','_method','name','description','enabled','turns','expected_handoff','expected_outcome_type','response_contains','response_not_contains','response_completed'];foreach(array_keys($request->all())as$key)if(!in_array($key,$allowed,true))throw ValidationException::withMessages([$key=>'Este campo no está permitido.']);}
    private function validateForm(Request$request):array{$data=$request->validate(['name'=>['required','string','max:160'],'description'=>['nullable','string','max:2000'],'enabled'=>['required','boolean'],'turns'=>['required','array','min:1','max:'.(int)config('ai.simulator.max_turns_per_scenario',10)],'turns.*.message'=>['required','string'],'turns.*.action_key'=>['nullable','string','max:64'],'turns.*.fixture_fields'=>['nullable','array','max:50'],'turns.*.fixture_fields.*.property'=>['required_with:turns.*.fixture_fields','string','max:100'],'turns.*.fixture_fields.*.value'=>['present'],'turns.*.simulate_confirmation'=>['nullable','boolean'],'expected_handoff'=>['required','in:any,yes,no'],'expected_outcome_type'=>['nullable','in:resolved'],'response_contains'=>['nullable','string','max:500'],'response_not_contains'=>['nullable','string','max:500'],'response_completed'=>['nullable','boolean']]);$builder=$data;unset($builder['name'],$builder['description'],$builder['enabled']);return['name'=>$data['name'],'description'=>$data['description']??null,'enabled'=>$data['enabled'],'builder'=>$builder];}
}
