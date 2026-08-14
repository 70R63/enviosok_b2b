<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;

final class DriverPwaController extends Controller
{
    public function manifest()
    {
        return response()->json([
            'id' => '/driver/', 'name' => 'ZIGO Driver', 'short_name' => 'ZIGO Driver',
            'description' => 'Operación de última milla de ZIGO Platform.',
            'start_url' => '/driver/', 'scope' => '/driver/', 'display' => 'standalone',
            'orientation' => 'portrait-primary', 'theme_color' => '#0B2445', 'background_color' => '#F1F5FA',
            'icons' => [
                ['src' => '/images/driver/zigo-driver-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/driver/zigo-driver-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/driver/zigo-driver-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'prefer_related_applications' => false,
            'lang' => 'es-MX', 'categories' => ['business', 'navigation'],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function offline()
    {
        return response()->view('tenant.driver.offline', ['tenant' => null], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    public function serviceWorker()
    {
        return response(file_get_contents(public_path('js/zigo-driver-sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/driver/',
        ]);
    }
}
