<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ZigoDriverPwaTest extends TestCase
{
    public function test_central_login_manifest_offline_and_service_worker_are_host_scoped(): void
    {
        $host = config('zigo_driver.host');
        $this->get("http://{$host}/driver/login")->assertOk()->assertSee('ZIGO DRIVER')->assertSee('by ZIGO Platform')->assertDontSee('RapidGo Driver');
        $manifest = $this->get("http://{$host}/driver/manifest.webmanifest")->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $manifest->assertJsonPath('name', 'ZIGO Driver')->assertJsonPath('start_url', '/driver/')->assertJsonPath('scope', '/driver/')->assertJsonMissingPath('icons');
        $this->get("http://{$host}/driver/offline")->assertOk()->assertSee('Sin conexión.')->assertSee('Revisa tu conexión para continuar operando.');
        $this->get("http://{$host}/driver/service-worker.js")->assertOk()->assertHeader('Service-Worker-Allowed', '/driver/');
    }

    public function test_service_worker_contract_is_static_only_and_never_caches_mutations_or_protected_data(): void
    {
        $worker = file_get_contents(public_path('js/zigo-driver-sw.js'));
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("STATIC_ASSETS.includes(url.pathname)", $worker);
        $this->assertStringContainsString("fetch(request).catch", $worker);
        foreach (['shipments', 'earnings', 'proof', 'signature', 'gps', 'api'] as $sensitive) {
            $this->assertStringNotContainsString("'/driver/{$sensitive}", $worker);
        }
    }

    public function test_context_contract_never_trusts_request_tenant_id_and_logout_clears_profile(): void
    {
        $workspace = file_get_contents(app_path('Domain/Shipping/LastMile/DriverWorkspaceService.php'));
        $middleware = file_get_contents(app_path('Http/Middleware/ResolveCentralDriverContext.php'));
        $auth = file_get_contents(app_path('Http/Controllers/Driver/CentralDriverAuthController.php'));
        $this->assertStringContainsString("where('user_id', \$user->getKey())", $workspace);
        $this->assertStringContainsString("where('role', 'driver')", $workspace);
        $this->assertStringContainsString("where('status', 'active')", $workspace);
        $this->assertStringContainsString("entitlements->has(\$profile->tenant, 'DRIVER')", $workspace);
        $this->assertStringNotContainsString("input('tenant_id'", $workspace.$middleware.$auth);
        $this->assertStringContainsString("session()->forget", $auth);
        $this->assertStringContainsString("session()->put(\$key, \$profile->uuid)", $middleware);
    }

    public function test_driver_surfaces_and_private_cache_contract_exist(): void
    {
        foreach (['deliveries','earnings','profile','support','workspaces','offline'] as $view) {
            $this->assertFileExists(resource_path("views/tenant/driver/{$view}.blade.php"));
        }
        $routes = app('router')->getRoutes();
        foreach (['driver.dashboard','driver.deliveries','driver.earnings','driver.profile','driver.support','tenant.driver.dashboard','tenant.driver.deliveries'] as $name) {
            $this->assertNotNull($routes->getByName($name));
        }
        $privacy = file_get_contents(app_path('Http/Middleware/ProtectDriverResponse.php'));
        $this->assertStringContainsString('private, no-store', $privacy);
        $this->assertStringContainsString('noindex, nofollow', $privacy);
    }

    public function test_network_map_registers_zigo_driver_as_current_product_and_marketplace_as_future(): void
    {
        $map = config('zigo_network_map.nodes');
        $driver = collect($map)->firstWhere('code', 'DRIVER');
        $marketplace = collect($map)->firstWhere('code', 'DRIVER_MARKETPLACE');
        $this->assertSame('ZIGO Driver', $driver['label']);
        $this->assertContains('Driver PWA', $driver['current_capabilities']);
        $this->assertContains('Driver Marketplace', $driver['future_capabilities']);
        $this->assertSame('planned', $marketplace['implementation_status']);
    }
}
