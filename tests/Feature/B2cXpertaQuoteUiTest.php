<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Models\B2cSaldo;
use Tests\TestCase;

final class B2cXpertaQuoteUiTest extends TestCase
{
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
