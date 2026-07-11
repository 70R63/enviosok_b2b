<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;

class ValidateZigoApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $plainKey = $request->header('X-ZIGO-API-KEY');

        if (!$plainKey) {
            return response()->json([
                'success' => false,
                'message' => 'Falta header X-ZIGO-API-KEY',
            ], 401);
        }

        $keyHash = hash('sha256', $plainKey);

        $apiKey = ApiKey::with('client')
            ->where('key_hash', $keyHash)
            ->where('active', true)
            ->first();

        if (!$apiKey || !$apiKey->client || !$apiKey->client->active) {
            return response()->json([
                'success' => false,
                'message' => 'API Key inválida o inactiva',
            ], 401);
        }

        $apiClient = $apiKey->client;

        /*
         * Candado comercial:
         * Solo cuenta consumo real del API Hub.
         * No contamos /api/hub/ping porque es endpoint técnico de salud.
         */
        $currentMonthUsage = ApiUsageLog::query()
            ->where('api_client_id', $apiClient->id)
            ->where('endpoint', '!=', 'api/hub/ping')
            ->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->count();

        if ($currentMonthUsage >= $apiClient->monthly_limit) {
            $response = response()->json([
                'success' => false,
                'message' => 'Límite mensual de consultas excedido.',
                'client' => $apiClient->name,
                'plan' => $apiClient->plan,
                'monthly_limit' => $apiClient->monthly_limit,
                'current_usage' => $currentMonthUsage,
            ], 429);

            ApiUsageLog::create([
                'api_client_id' => $apiClient->id,
                'api_key_id' => $apiKey->id,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'status_code' => 429,
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
                'ip' => $request->ip(),
                'error_message' => 'Límite mensual de consultas excedido.',
            ]);

            return $response;
        }

        $apiKey->update([
            'last_used_at' => now(),
        ]);

        $request->attributes->set('api_client', $apiClient);
        $request->attributes->set('api_key', $apiKey);

        $response = $next($request);

        ApiUsageLog::create([
            'api_client_id' => $apiClient->id,
            'api_key_id' => $apiKey->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'ip' => $request->ip(),
            'error_message' => $response->getStatusCode() >= 400 ? 'Error HTTP ' . $response->getStatusCode() : null,
        ]);

        return $response;
    }
}