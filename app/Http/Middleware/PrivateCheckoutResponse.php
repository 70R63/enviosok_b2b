<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class PrivateCheckoutResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->route('cotizacion')?->provider === 'tenant_provider_local') {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        return $response;
    }
}
