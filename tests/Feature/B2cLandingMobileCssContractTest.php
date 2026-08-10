<?php

namespace Tests\Feature;

use Tests\TestCase;

class B2cLandingMobileCssContractTest extends TestCase
{
    public function test_final_mobile_cascade_overrides_fixed_header_and_six_column_quote_grid(): void
    {
        $view = file_get_contents(resource_path('views/index.blade.php'));
        $start = strpos($view, 'B2C_RC05_MOBILE_START');
        $end = strpos($view, 'B2C_RC05_MOBILE_END');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $mobile = substr($view, $start, $end - $start);

        $this->assertStringContainsString('@media (max-width: 767px)', $mobile);
        $this->assertStringContainsString('.quote-form { display: grid !important; grid-template-columns: minmax(0,1fr) !important;', $mobile);
        $this->assertStringContainsString('.brand { min-width: 0 !important;', $mobile);
        $this->assertStringContainsString('.main-nav { display: none !important;', $mobile);
        $this->assertStringContainsString('.main-nav.is-open { display: grid !important;', $mobile);
        $this->assertStringContainsString('width: min(94vw,620px) !important;', $mobile);
        $this->assertStringNotContainsString('1.25fr 1.25fr 1.05fr .8fr 1.45fr 1.1fr', $mobile);
        $this->assertStringNotContainsString('position: absolute', $mobile);
        $this->assertStringNotContainsString('min-width: 430px', $mobile);
    }

    public function test_landing_mobile_controls_are_full_width_and_navigation_is_accessible(): void
    {
        $view = file_get_contents(resource_path('views/index.blade.php'));
        foreach (['320', '375', '390', '768', '1366'] as $width) {
            $this->assertMatchesRegularExpression('/^\d+$/', $width);
        }
        $this->assertStringContainsString('class="mobile-nav-toggle"', $view);
        $this->assertStringContainsString('aria-controls="main-navigation"', $view);
        $this->assertStringContainsString('aria-expanded="false"', $view);
        $this->assertStringContainsString('.btn-yellow, .quote-reset-link', $view);
        $this->assertStringContainsString('.landing-quote-select { width: 100% !important;', $view);
        $this->assertStringContainsString('.box-dimensions { display: grid !important;', $view);
    }

    public function test_responsive_guard_does_not_force_a_fixed_mobile_canvas(): void
    {
        $css = file_get_contents(public_path('css/b2c-responsive.css'));
        $this->assertStringNotContainsString('body { min-width: 320px', $css);
        $this->assertStringContainsString('body { min-width: 0;', $css);
    }
}
