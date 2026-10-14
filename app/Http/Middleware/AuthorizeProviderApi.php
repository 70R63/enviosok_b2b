<?php

namespace App\Http\Middleware;

use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\ProviderApiPrincipal;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthorizeProviderApi
{
    public function handle(Request $r, Closure $next)
    {
        if (! app()->environment(['local', 'testing']) && ! $r->isSecure()) {
            return response()->json(['error' => ['code' => 'TLS_REQUIRED']], 426);
        }
        $length = (int) $r->server('CONTENT_LENGTH', 0);
        if ($length > 8 * 1024 * 1024 || strlen($r->getContent()) > 8 * 1024 * 1024) {
            return response()->json(['error' => ['code' => 'REQUEST_TOO_LARGE']], 413);
        }
        try {
            $i = ProviderIntegration::where('api_client_id', $r->attributes->get('api_client')->id)->firstOrFail();
            $principal = new ProviderApiPrincipal($i->id, $r->attributes->get('api_key')->id);
            $principal->integration();
            $r->attributes->set('provider_principal', $principal);

            return $next($r);
        } catch (ValidationException $e) {
            return response()->json(['error' => ['code' => 'INVALID_PROVIDER_COMMAND']], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'NOT_FOUND']], 404);
        } catch (HttpException $e) {
            return response()->json(['error' => ['code' => 'PROVIDER_COMMAND_DENIED']], $e->getStatusCode());
        } catch (\DomainException) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION']], 422);
        } catch (\Throwable $e) {
            Log::warning('provider.api.unavailable', ['class' => get_class($e)]);

            return response()->json(['error' => ['code' => 'PROVIDER_API_UNAVAILABLE']], 503);
        }
    }
}
