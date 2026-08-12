<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SiteGroundWildcardFrontControllerTest extends TestCase
{
    public function test_versioned_router_uses_strict_stage_host_match_and_fixed_roots(): void
    {
        $router = file_get_contents(base_path('deploy/siteground/zigo-wildcard-index.php'));

        $this->assertStringContainsString("'/^[a-z0-9-]+-stage\\.zigo-envios\\.com$/', \$host", $router);
        $this->assertStringContainsString("'/home/customer/www/stage.zigo-envios.com'", $router);
        $this->assertStringContainsString("'/home/customer/www/zigo-envios.com'", $router);
        $this->assertStringContainsString("\$laravelRoot.'/bootstrap/app.php'", $router);
        $this->assertStringNotContainsString('$host.', $router);
    }
}
