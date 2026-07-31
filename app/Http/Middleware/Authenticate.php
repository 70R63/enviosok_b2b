<?php

namespace App\Http\Middleware;

use App\Services\ZigoDomainResolver;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Log;
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__); 
        if ($request->expectsJson()) {
            return null;
        }

        $domainResolver = app(ZigoDomainResolver::class);

        if ($domainResolver->isSubdomainRoutingEnabled()) {
            $loginRoutes = [
                'crm' => 'crm.login',
                'b2b' => 'negocios.login',
                'support' => 'soporte.login',
            ];

            $portal = $domainResolver->currentPortal($request->getHost());

            if (isset($loginRoutes[$portal])) {
                return route($loginRoutes[$portal]);
            }
        }

        return route('login');
    }
}
