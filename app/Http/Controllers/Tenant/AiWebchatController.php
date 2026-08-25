<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Channels\Webchat\Models\WebchatChannel;
use App\Domain\AI\Channels\Webchat\Services\ManageWebchatChannelService;
use App\Domain\AI\Tenancy\AiTenantBoundary;
use App\Domain\AI\Usage\Exceptions\AiCapacityExceededException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
final class AiWebchatController extends Controller
{
 public function show(Request$r,Agent$agent,AiTenantBoundary$t){$t->assertResourceBelongsToCurrentTenant($agent);$channel=WebchatChannel::query()->where('tenant_id',$t->requireTenantId())->where('agent_id',$agent->id)->first();return view('tenant.admin.ai-agents.webchat',['tenant'=>$t->requireTenant(),'agent'=>$agent,'channel'=>$channel]);}
 public function save(Request$r,Agent$agent,ManageWebchatChannelService$s){$d=$r->validate(['display_name'=>['required','string','max:120'],'welcome_message'=>['nullable','string','max:500'],'primary_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/D'],'launcher_label'=>['required','string','max:80'],'enabled'=>['required','boolean'],'allowed_origins'=>['required','string','max:4000']]);$d['allowed_origins']=array_values(array_filter(array_map('trim',preg_split('/\R/',$d['allowed_origins'])?:[])));try{$s->save($r->user(),$agent,$d);}catch(AiCapacityExceededException){return back()->withErrors(['webchat'=>'Límite alcanzado. Amplía tu plan para agregar más capacidad.']);}return back()->with('success','Webchat actualizado.');}
 public function rotate(Request$r,Agent$agent,ManageWebchatChannelService$s){$s->rotate($r->user(),$agent);return back()->with('success','Clave pública regenerada.');}
}
