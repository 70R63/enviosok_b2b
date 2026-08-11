<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
final class EnsureConfiguredSurfaceHost
{
    public function handle(Request $request, Closure $next, string $surface)
    {
        if (! config('zigo_domains.routing_enabled') || ! app()->environment(['stage', 'staging', 'production'])) return $next($request);
        $expected = strtolower((string) config("zigo_surfaces.{$surface}.host"));
        abort_unless($expected !== '' && hash_equals($expected, strtolower($request->getHost())), 404);
        return $next($request);
    }
}
