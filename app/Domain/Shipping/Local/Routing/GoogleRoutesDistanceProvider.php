<?php

namespace App\Domain\Shipping\Local\Routing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GoogleRoutesDistanceProvider implements RouteDistanceProvider
{
    public function distance(string $originAddress, string $destinationAddress): ?array
    {
        $key = (string) config('services.google_routes.api_key');
        if ($key === '') return null;
        try {
            $response = Http::acceptJson()->withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
            ])->timeout((int) config('services.google_routes.timeout', 8))->post(
                rtrim((string) config('services.google_routes.base_url', 'https://routes.googleapis.com'), '/').'/directions/v2:computeRoutes',
                ['origin'=>['address'=>$originAddress], 'destination'=>['address'=>$destinationAddress], 'travelMode'=>'DRIVE']
            );
            if (! $response->successful()) { Log::warning('google_routes.unavailable', ['status'=>$response->status()]); return null; }
            $route = $response->json('routes.0');
            if (! is_array($route) || ! isset($route['distanceMeters'])) return null;
            $duration = isset($route['duration']) ? (int) round((float) rtrim((string) $route['duration'], 's')) : null;
            return ['distance_meters'=>(int) $route['distanceMeters'], 'duration_seconds'=>$duration];
        } catch (\Throwable $exception) {
            Log::warning('google_routes.unavailable', ['exception'=>$exception::class]);
            return null;
        }
    }
}
