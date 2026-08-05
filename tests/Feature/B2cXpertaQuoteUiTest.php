<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Models\B2cSaldo;
use Tests\TestCase;

final class B2cXpertaQuoteUiTest extends TestCase
{
    public function test_active_landing_view_renders_real_quote_metadata(): void
    {
        $quote = new B2cCotizacion([
            'cp_origen' => '09800', 'cp_destino' => '57820',
            'tipo_envio' => 'caja', 'peso' => 1, 'medidas' => null,
        ]);
        $quote->id = 88;
        $option = [
            'logistico'=>'Estafeta','logo'=>'img/estafeta.png','servicio'=>'Terrestre',
            'estimated_delivery_date'=>'2026-08-06','periodicity_name'=>'Diaria',
            'operating_days'=>['lunes','martes','miércoles','jueves','viernes','sábado'],
            'zone_code'=>'1','is_reexpedition'=>false,'restriction'=>false,
            'restriction_description'=>'','commercial_price'=>260.56,'precio'=>260.56,
            'weight_billable'=>1.0,'dimensions'=>'No aplica','insurance_enabled'=>false,
            'provider_total'=>116.00,'provider_base_price'=>116.00,'margen'=>144.56,
        ];

        $html = view('index', [
            'cotizacion_publica' => $quote,
            'cotizacion_id' => 88,
            'opciones' => [$option],
        ])->render();

        $this->assertStringContainsString('Entrega estimada:', $html);
        $this->assertStringContainsString('06/08/2026', $html);
        $this->assertStringContainsString('Frecuencia:', $html);
        $this->assertStringContainsString('Diaria', $html);
        $this->assertStringContainsString('lunes, martes, miércoles, jueves, viernes, sábado', $html);
        $this->assertStringContainsString('Área regular', $html);
        $this->assertStringContainsString('$260.56 MXN', $html);
        $this->assertStringContainsString('Resumen de tu cotización', $html);
        $this->assertStringContainsString('Para continuar con un envío tipo caja necesitas iniciar sesión o crear una cuenta.', $html);
        $this->assertStringContainsString('Iniciar sesión', $html);
        $this->assertStringContainsString('Crear cuenta', $html);
        $this->assertStringNotContainsString('provider_total', $html);
        $this->assertStringNotContainsString('provider_base_price', $html);
        $this->assertStringNotContainsString('144.56', $html);
        $this->assertStringNotContainsString('$116.00', $html);
    }

    public function test_guest_envelope_modal_keeps_continue_without_auth_message(): void
    {
        $quote = new B2cCotizacion(['cp_origen'=>'09800','cp_destino'=>'57820','tipo_envio'=>'sobre','peso'=>1]);
        $quote->id = 89;
        $option = ['logistico'=>'Estafeta','logo'=>'img/estafeta.png','servicio'=>'Terrestre',
            'estimated_delivery_date'=>'2026-08-06','periodicity_name'=>'Diaria','operating_days'=>['lunes'],
            'zone_code'=>'1','is_reexpedition'=>false,'restriction'=>false,'restriction_description'=>'',
            'commercial_price'=>260.56,'weight_billable'=>1.0,'dimensions'=>'No aplica','insurance_enabled'=>false];
        $html=view('index',['cotizacion_publica'=>$quote,'cotizacion_id'=>89,'opciones'=>[$option]])->render();

        $this->assertStringContainsString('id="landing-quote-continue"', $html);
        $this->assertStringNotContainsString('Para continuar con un envío tipo caja', $html);
        $this->assertStringNotContainsString('id="landing-quote-login"', $html);
    }

    public function test_quote_card_and_summary_only_render_public_commercial_data(): void
    {
        $quote = new B2cCotizacion([
            'cp_origen' => '09800',
            'cp_destino' => '57820',
            'peso' => 3.5,
            'medidas' => '30x20x10',
        ]);
        $quote->id = 42;
        $option = [
            'logistico' => 'Estafeta',
            'servicio' => 'Terrestre',
            'provider_source' => 'xperta',
            'estimated_delivery_date' => '2026-08-08',
            'periodicity_name' => 'Diaria',
            'operating_days' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
            'zone_code' => 'Z5',
            'is_reexpedition' => true,
            'extended_area' => true,
            'weight_billable' => 3.5,
            'dimensions' => '30x20x10',
            'insurance_enabled' => true,
            'commercial_price' => 299.99,
            'precio' => 299.99,
            'provider_total' => 167.04,
            'provider_base_price' => 150.00,
            'margen' => 82.95,
        ];

        $html = view('b2c.opciones', [
            'cotizacion' => $quote,
            'opciones' => [$option],
            'saldo' => new B2cSaldo(['saldo' => 0]),
        ])->render();

        $this->assertStringContainsString('2026-08-08', $html);
        $this->assertStringContainsString('Diaria', $html);
        $this->assertStringContainsString('Lun, Mar, Mié, Jue, Vie, Sáb', $html);
        $this->assertStringContainsString('Z5', $html);
        $this->assertStringContainsString('$299.99 MXN', $html);
        $this->assertStringContainsString('Resumen de tu cotización', $html);
        $this->assertStringNotContainsString('provider_base_price', $html);
        $this->assertStringNotContainsString('provider_total', $html);
        $this->assertStringNotContainsString('margen', strtolower($html));
        $this->assertStringNotContainsString('167.04', $html);
        $this->assertStringNotContainsString('150.00', $html);
        $this->assertStringNotContainsString('82.95', $html);
    }
}
