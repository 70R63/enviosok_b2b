<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\AI\Agents\Models\Agent;use App\Domain\AI\Tenancy\AiTenantBoundary;use App\Http\Controllers\Controller;use App\Http\Support\AiLaunchpadHttpGate;
final class AiAgentController extends Controller
{
 public function index(AiLaunchpadHttpGate$gate,AiTenantBoundary$t){$gate->ensure(auth()->user());return view('tenant.admin.ai-agents.index',['tenant'=>$t->requireTenant(),'agents'=>Agent::query()->latest()->paginate(20)]);}
 public function show(Agent$agent,AiLaunchpadHttpGate$gate,AiTenantBoundary$t){$gate->ensure(auth()->user());$gate->assertCurrentTenant($agent);$agent->load(['contract','versions'=>fn($q)=>$q->orderByDesc('version_number')]);if($agent->contract)$agent->contract->load(['versions'=>fn($q)=>$q->orderByDesc('version_number')]);return view('tenant.admin.ai-agents.show',['tenant'=>$t->requireTenant(),'agent'=>$agent]);}
}
