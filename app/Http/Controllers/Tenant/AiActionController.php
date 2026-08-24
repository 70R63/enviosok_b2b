<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\AI\Actions\Models\ActionRun;use App\Domain\AI\Actions\Services\ConfirmActionRunService;use App\Domain\AI\Tenancy\AiTenantBoundary;use App\Http\Controllers\Controller;use App\Http\Requests\Tenant\CloseConversationRequest;use App\Http\Support\AiLaunchpadHttpGate;
final class AiActionController extends Controller
{
 public function show(ActionRun$actionRun,AiLaunchpadHttpGate$gate,AiTenantBoundary$tenants){$gate->ensure(auth()->user());$gate->assertCurrentTenant($actionRun);$actionRun->load(['conversation.agent','confirmer']);return view('tenant.admin.ai-actions.show',['tenant'=>$tenants->requireTenant(),'actionRun'=>$actionRun]);}
 public function confirm(CloseConversationRequest$request,ActionRun$actionRun,AiLaunchpadHttpGate$gate,ConfirmActionRunService$service){$gate->ensure($request->user());$gate->assertCurrentTenant($actionRun);try{$service->confirm($request->user(),$actionRun);}catch(\DomainException){return back()->withErrors(['action'=>'La acción ya no está disponible para confirmación.']);}return redirect()->route('tenant.admin.ai-conversations.show',$actionRun->conversation)->with('success','Acción completada.');}
}
