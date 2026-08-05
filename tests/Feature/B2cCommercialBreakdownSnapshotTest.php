<?php

namespace Tests\Feature;

use App\Models\B2cCotizacion;
use App\Models\B2cInvoiceRequest;
use App\Services\ApiHub\Billing\Internal\B2cBillingSnapshotBuilder;
use App\Services\ZigoProviderQuoteObservationService;
use Illuminate\Support\Facades\Blade;
use Mockery;
use Tests\TestCase;

final class B2cCommercialBreakdownSnapshotTest extends TestCase
{
    public function test_regular_area_renders_only_base_and_frozen_totals(): void
    {
        $html = $this->render([
            'commercial_breakdown' => [
                'base' => 180.00,
                'area_extendida' => 0,
                'kg_extra' => 0,
                'seguro' => 0,
                'otros' => 0,
            ],
            'commercial_subtotal' => 180.00,
            'vat' => 28.80,
            'customer_total' => 208.80,
        ]);

        $this->assertStringContainsString('Envío', $html);
        $this->assertStringContainsString('$180.00 MXN', $html);
        $this->assertStringContainsString('$28.80 MXN', $html);
        $this->assertStringContainsString('$208.80 MXN', $html);
        $this->assertStringNotContainsString('Área extendida', $html);
        $this->assertStringNotContainsString('Kg adicional', $html);
        $this->assertStringNotContainsString('Seguro', $html);
        $this->assertStringNotContainsString('Otros cargos', $html);
    }

    public function test_positive_concepts_render_once_without_duplicate_extended_area(): void
    {
        $html = $this->render([
            'commercial_breakdown' => [
                'base' => 180.00,
                'area_extendida' => 209.00,
                'kg_extra' => 31.50,
                'seguro' => 10.00,
                'otros' => 5.00,
            ],
            'commercial_subtotal' => 435.50,
            'vat' => 69.68,
            'customer_total' => 505.18,
        ]);

        foreach (['Área extendida', 'Kg adicional', 'Seguro', 'Otros cargos'] as $label) {
            $this->assertSame(1, substr_count($html, $label));
        }
        $this->assertSame(1, substr_count($html, 'data-concept="area_extendida"'));
        $this->assertSame(1, substr_count($html, 'data-concept="seguro"'));
        $this->assertStringContainsString('$435.50 MXN', $html);
        $this->assertStringContainsString('$69.68 MXN', $html);
        $this->assertStringContainsString('$505.18 MXN', $html);
    }

    public function test_public_breakdown_templates_do_not_reference_internal_pricing_fields(): void
    {
        $paths = [
            resource_path('views/b2c/checkout.blade.php'),
            resource_path('views/b2c/confirmar-envio.blade.php'),
            resource_path('views/b2c/detalle-envio.blade.php'),
            resource_path('views/b2c/pago-estado.blade.php'),
            resource_path('views/b2c/partials/commercial-breakdown.blade.php'),
        ];

        foreach ($paths as $path) {
            $contents = (string) file_get_contents($path);
            $this->assertStringNotContainsString('provider_total', $contents);
            $this->assertStringNotContainsString('provider_base_price', $contents);
            $this->assertStringNotContainsString('applied_rules', $contents);
        }
    }

    public function test_billing_payload_keeps_the_same_frozen_commercial_snapshot(): void
    {
        config()->set('services.zigo_internal_billing.shipping_product_service_code', '78102203');
        config()->set('services.zigo_internal_billing.unit_code', 'E48');
        config()->set('services.zigo_internal_billing.tax_object', '02');

        $snapshot = [
            'commercial_breakdown' => [
                'base' => 180.00,
                'area_extendida' => 209.00,
                'kg_extra' => 0,
                'seguro' => 0,
                'otros' => 0,
            ],
            'commercial_subtotal' => 389.00,
            'vat' => 62.24,
            'customer_total' => 451.24,
        ];
        $cotizacion = new B2cCotizacion([
            'logistico' => 'Estafeta',
            'servicio' => 'Terrestre',
            'precio' => 451.24,
            'zigo_final_price' => 451.24,
            'requiere_seguro_envio' => false,
            'seguro_monto' => 0,
            'cp_origen' => '09800',
            'cp_destino' => '57820',
        ]);
        $cotizacion->id = 88;
        $invoice = new B2cInvoiceRequest([
            'monto' => 451.24,
            'payment_status' => 'approved',
            'metodo_pago' => 'PUE',
            'forma_pago' => '03',
            'rfc' => 'XAXX010101000',
            'razon_social' => 'PUBLICO EN GENERAL',
            'codigo_postal_fiscal' => '09800',
            'regimen_fiscal' => '616',
            'uso_cfdi' => 'S01',
            'email_facturacion' => 'cliente@example.test',
            'cotizacion_id' => 88,
        ]);
        $invoice->id = 12;
        $invoice->setRelation('cotizacion', $cotizacion);

        $selection = Mockery::mock(ZigoProviderQuoteObservationService::class);
        $selection->shouldReceive('selectionMetadata')->andReturn($snapshot);
        $this->app->instance(ZigoProviderQuoteObservationService::class, $selection);

        $payload = app(B2cBillingSnapshotBuilder::class)->build($invoice);

        $this->assertSame(389.00, $payload['amounts']['subtotal']);
        $this->assertSame(62.24, $payload['amounts']['tax']);
        $this->assertSame(451.24, $payload['amounts']['total']);
        $this->assertSame($snapshot['commercial_breakdown'], $payload['amounts']['commercial_breakdown']);
        $this->assertSame($snapshot, array_intersect_key(
            $payload['items'][0]['metadata'],
            $snapshot
        ));
        $this->assertArrayNotHasKey('provider_base_price', $payload['items'][0]['metadata']);
        $this->assertArrayNotHasKey('zigo_margin_amount', $payload['items'][0]['metadata']);
    }

    private function render(array $snapshot): string
    {
        return Blade::render(
            "@include('b2c.partials.commercial-breakdown', ['commercialSnapshot' => \$snapshot])",
            ['snapshot' => $snapshot]
        );
    }
}
