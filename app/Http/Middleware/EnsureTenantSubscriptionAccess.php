<?php
namespace App\Http\Middleware;
use App\Domain\Network\Billing\SubscriptionAccessService;use App\Domain\Network\Tenancy\TenantContext;use Closure;use Illuminate\Http\Request;
final class EnsureTenantSubscriptionAccess{public function __construct(private TenantContext$context,private SubscriptionAccessService$access){}public function handle(Request$request,Closure$next){$tenant=$this->context->tenant();abort_unless($tenant,404);if(!$this->access->isOperational($tenant))return response()->view('tenant.suspended',['tenant'=>$tenant->load('branding'),'subscription'=>$this->access->subscription($tenant)],503);return$next($request);}}
