<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\Network\Tenancy\Models\Tenant;use App\Domain\Network\Tenancy\TenantContext;use App\Domain\Network\Billing\{EntitlementService,SubscriptionService};use App\Http\Controllers\Controller;
final class TenantHomeController extends Controller
{
 public function preview(Tenant $tenant){return $this->render($tenant,true);}
 public function home(TenantContext $context){abort_unless($context->hasTenant(),404);return $this->render($context->tenant());}
 private function render(Tenant $tenant,bool $preview=false){$tenant->load(['branding','primaryDomain','currentPlan.modules'=>fn($q)=>$q->wherePivot('is_included',true)->orderBy('sort_order')]);$subscription=app(SubscriptionService::class)->currentForTenant($tenant);$modules=$preview?app(EntitlementService::class)->enabledModules($tenant):collect();$plan=$subscription?->plan??$tenant->currentPlan;return view('tenant.home',compact('tenant','subscription','modules','plan','preview'));}
}
