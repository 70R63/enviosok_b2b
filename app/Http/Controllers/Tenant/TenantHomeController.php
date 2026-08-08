<?php
namespace App\Http\Controllers\Tenant;
use App\Domain\Network\Tenancy\Models\Tenant;use App\Domain\Network\Tenancy\TenantContext;use App\Http\Controllers\Controller;
final class TenantHomeController extends Controller
{
 public function preview(Tenant $tenant){return $this->render($tenant);}
 public function home(TenantContext $context){abort_unless($context->hasTenant(),404);return $this->render($context->tenant());}
 private function render(Tenant $tenant){$tenant->load(['branding','primaryDomain','currentPlan.modules'=>fn($q)=>$q->wherePivot('is_included',true)->orderBy('sort_order')]);return view('tenant.home',compact('tenant'));}
}
