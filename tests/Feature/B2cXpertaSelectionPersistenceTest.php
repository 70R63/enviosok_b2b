<?php

namespace Tests\Feature;

use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Models\B2cCotizacion;
use App\Services\ZigoProviderQuoteObservationService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class B2cXpertaSelectionPersistenceTest extends TestCase
{
    public function test_selection_preserves_commercial_price_and_quote_metadata(): void
    {
        $saved = null;
        $quote = Mockery::mock(B2cCotizacion::class)->makePartial();
        $quote->id = 88;
        $quote->requiere_seguro_envio = false;
        $quote->seguro_monto = 0;
        $quote->shouldReceive('update')->once()->with(Mockery::on(
            function (array $values) use (&$saved): bool {
                $saved = $values;
                return true;
            }
        ))->andReturnTrue();

        $option = [
            'logistico' => 'Estafeta', 'servicio' => 'Terrestre',
            'service_code' => 'terrestre', 'provider_source' => 'xperta',
            'carrier' => 'estafeta', 'quote_source' => 'xperta_estafeta',
            'provider_total' => 116.00, 'base_price' => 116.00,
            'request_fingerprint' => 'safe-fingerprint',
            'correlation_id' => 'safe-correlation',
            'quote_expires_at' => now()->addMinutes(30),
            'estimated_delivery_date' => '2026-08-06', 'zone_code' => '1',
            'periodicity_name' => 'Diaria',
            'operating_days' => ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
            'is_reexpedition' => false, 'is_ocurre' => false,
            'restriction' => false, 'restriction_description' => '',
            'pricing' => [
                'base_price' => 116.00, 'final_price' => 149.50,
                'margin_percentage' => 0, 'fixed_fee' => 0, 'margin_amount' => 0,
                'adjustment_type' => null, 'adjustment_value' => 0, 'adjustment_amount' => 0,
                'discount_type' => null, 'discount_value' => 0, 'discount_amount' => 0,
                'profit_amount' => 33.50, 'customer_segment' => 'b2c',
                'pricing_rule_id' => null, 'adjustment_id' => null,
                'client_pricing_rule_id' => null,
            ],
        ];

        $snapshot = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $snapshot->shouldReceive('attachSelectionMetadata')
            ->once()->with($quote, $option);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $snapshot);

        $method = new ReflectionMethod(
            CotizacionPublicaController::class,
            'applyPricingToCotizacion'
        );
        $method->setAccessible(true);
        $method->invoke(new CotizacionPublicaController(), $quote, $option);

        $this->assertSame('terrestre', $saved['service_code']);
        $this->assertSame('Terrestre', $saved['servicio']);
        $this->assertSame(116.00, $saved['provider_total']);
        $this->assertSame(116.00, $saved['provider_base_price']);
        $this->assertSame(149.50, $saved['precio']);
        $this->assertSame(149.50, $saved['zigo_final_price']);
        $this->assertSame('xperta_estafeta', $saved['quote_source']);
        $this->assertSame('safe-fingerprint', $saved['quote_request_fingerprint']);
        $this->assertSame('safe-correlation', $saved['quote_correlation_id']);
        $this->assertNotNull($saved['quote_expires_at']);
    }
}
