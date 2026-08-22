<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\AI\Agents\Models\Agent;use App\Domain\AI\Tenancy\AiTenantBoundary;use App\Http\Controllers\Controller;use App\Http\Support\AiLaunchpadHttpGate;
final class AiAgentController extends Controller
{
 public function index(AiLaunchpadHttpGate$gate,AiTenantBoundary$t){$gate->ensure(auth()->user());return view('tenant.admin.ai-agents.index',['tenant'=>$t->requireTenant(),'agents'=>Agent::query()->latest()->paginate(20)]);}
 public function show(Agent$agent,AiLaunchpadHttpGate$gate,AiTenantBoundary$t){$gate->ensure(auth()->user());$gate->assertCurrentTenant($agent);$agent->load(['contract','versions'=>fn($q)=>$q->orderByDesc('version_number')]);if($agent->contract)$agent->contract->load(['versions'=>fn($q)=>$q->orderByDesc('version_number')]);$draft=$agent->versions->first(fn($v)=>$v->status===\App\Domain\AI\Agents\Enums\AgentVersionStatus::Draft);$links=$draft?\App\Domain\AI\Knowledge\Models\AgentVersionKnowledgeSource::query()->where('agent_version_id',$draft->id)->with('knowledgeVersion.source')->get():collect();$available=\App\Domain\AI\Knowledge\Models\KnowledgeSourceVersion::query()->where('status',\App\Domain\AI\Knowledge\Enums\KnowledgeVersionStatus::Approved->value)->whereHas('source',fn($q)=>$q->where('status',\App\Domain\AI\Knowledge\Enums\KnowledgeSourceStatus::Active->value))->with('source')->get();return view('tenant.admin.ai-agents.show',['tenant'=>$t->requireTenant(),'agent'=>$agent,'draftVersion'=>$draft,'knowledgeLinks'=>$links,'availableKnowledge'=>$available]);}
}
