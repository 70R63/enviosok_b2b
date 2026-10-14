<?php

namespace App\Http\Controllers\API\Hub\V1;

use App\Domain\Network\Providers\ProviderApiOperations;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProviderFulfillmentApiController extends Controller
{
    public function index(Request $r, ProviderApiOperations $s)
    {
        return $this->respond(fn () => response()->json(['data' => $s->listing($r->attributes->get('provider_principal'), max(1, (int) $r->query('page', 1)))]));
    }

    public function show(Request $r, string $fulfillment, ProviderApiOperations $s)
    {
        return $this->respond(fn () => response()->json(['data' => $s->detail($r->attributes->get('provider_principal'), $fulfillment)]));
    }

    public function command(Request $r, string $fulfillment, ProviderApiOperations $s)
    {
        return $this->respond(function () use ($r, $fulfillment, $s) {
            $action = $r->route()->defaults['provider_action'];
            $signature = null;
            if ($r->filled('signature')) {
                $raw = $r->input('signature');
                abort_unless(is_string($raw) && strlen($raw) <= 750000 && str_starts_with($raw, 'data:image/png;base64,'), 422);
                $signature = base64_decode(substr($raw, 22), true);
                abort_unless($signature !== false, 422);
            }
            $result = $s->command($r->attributes->get('provider_principal'), $fulfillment, $action, (string) $r->header('Idempotency-Key'),
                $r->except('photo'), $r->file('photo'), $signature, (string) $r->attributes->get('zigo_request_id'));
            $response = response()->json($result['body'], $result['status']);
            if ($result['replay'] ?? false) {
                $response->headers->set('X-ZIGO-Idempotent-Replay', 'true');
            }

return $response;
        });
    }

    private function respond(callable $callback)
    {
        try {
            return $callback();
        } catch (ValidationException) {
            return response()->json(['error' => ['code' => 'INVALID_PROVIDER_COMMAND']], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => ['code' => 'NOT_FOUND']], 404);
        } catch (HttpException $e) {
            return response()->json(['error' => ['code' => 'PROVIDER_COMMAND_DENIED']], $e->getStatusCode());
        } catch (\DomainException) {
            return response()->json(['error' => ['code' => 'INVALID_TRANSITION']], 422);
        } catch (\Throwable $e) {
            Log::warning('provider.api.unavailable', ['class' => get_class($e)]);

            return response()->json(['error' => ['code' => 'PROVIDER_API_UNAVAILABLE']],503);
        }
    }
}
