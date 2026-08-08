<?php
namespace App\Http\Middleware;
use App\Domain\Network\Tenancy\TenantDomainResolver;use Closure;use Illuminate\Http\Request;
final class ResolveNetworkTenant
{
 public function __construct(private TenantDomainResolver $resolver){}
 public function handle(Request $request,Closure $next){$domain=$this->resolver->resolve($request->getHost());abort_unless($domain,404);$request->attributes->set('tenant_domain',$domain);return $next($request);}
}
