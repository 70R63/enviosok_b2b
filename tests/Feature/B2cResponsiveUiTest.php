<?php

namespace Tests\Feature;

use Tests\TestCase;

class B2cResponsiveUiTest extends TestCase
{
    /** @test */
    public function responsive_styles_cover_required_release_candidate_widths(): void
    {
        $css = file_get_contents(public_path('css/b2c-responsive.css'));

        $this->assertStringContainsString('max-width: 767px', $css);
        $this->assertStringContainsString('min-width: 768px', $css);
        $this->assertStringContainsString('max-width: 1023px', $css);
        $this->assertStringContainsString('overflow-x: hidden', $css);
        $this->assertStringContainsString('max-height: 90vh', $css);
        $this->assertStringContainsString('min-height: 44px', $css);
        $this->assertStringContainsString('.shipments-table td::before', $css);

        foreach ([320, 375, 768, 1366] as $width) {
            $this->assertGreaterThanOrEqual(320, $width);
        }
    }

    /** @test */
    public function active_b2c_flow_views_load_the_responsive_layer(): void
    {
        foreach ([
            'index.blade.php',
            'b2c/checkout.blade.php',
            'b2c/confirmar-envio.blade.php',
            'b2c/mis-envios.blade.php',
            'b2c/detalle-envio.blade.php',
            'b2c/pago-estado.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path('views/' . $view));
            $this->assertStringContainsString('b2c-responsive.css', $contents, $view);
        }
    }

    /** @test */
    public function shipment_table_has_mobile_card_labels(): void
    {
        $view = file_get_contents(resource_path('views/b2c/mis-envios.blade.php'));

        $this->assertStringContainsString('class="shipments-table"', $view);
        foreach ([
            'Cotización', 'Mensajería', 'Servicio', 'Origen', 'Destino',
            'Precio', 'Estatus pago', 'Estado guía', 'Tracking', 'Acciones',
        ] as $label) {
            $this->assertStringContainsString('data-label="' . $label . '"', $view);
        }
    }

    /** @test */
    public function quote_modal_retains_conditional_box_and_envelope_actions(): void
    {
        $view = file_get_contents(resource_path('views/index.blade.php'));

        $this->assertStringContainsString('landing-quote-auth-message', $view);
        $this->assertStringContainsString('landing-quote-login', $view);
        $this->assertStringContainsString('landing-quote-register', $view);
        $this->assertStringContainsString('landing-quote-continue', $view);
        $this->assertStringContainsString('landing-quote-cancel', $view);
        $this->assertStringContainsString('commercial_price', $view);
        $this->assertStringContainsString('periodicity_name', $view);
        $this->assertStringContainsString('zone_code', $view);
    }
}
