<?php

namespace App\Http\Controllers\B2C;

use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderConsumerResolver;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Shipping\Local\LocalGuidePdfRenderer;
use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Models\B2cGuideRecoveryToken;
use TCPDF;

final class ProviderFulfillmentController extends Controller
{
    public function publicShow(ProviderContract $contract)
    {
        return $this->show(B2cCotizacion::findOrFail($contract->b2c_cotizacion_id));
    }

    public function publicGuide(ProviderContract $contract)
    {
        return $this->guide(B2cCotizacion::findOrFail($contract->b2c_cotizacion_id));
    }

    /** Existing bearer recovery grants read-only access, scoped to the consumer host. */
    public function recovered(B2cGuideRecoveryToken $access, string $token, bool $download = false)
    {
        $cotizacion = $access->cotizacion;
        $offer = $cotizacion->providerContract?->snapshot;
        $consumer = app(ProviderConsumerResolver::class)->current();
        abort_unless($access->exists && ! $access->revoked_at && $access->expires_at->isFuture()
            && $access->attempts <= $access->max_attempts
            && $cotizacion->provider === 'tenant_provider_local' && $offer && $consumer
            && (int) $consumer->id === (int) $offer->consumer_tenant_id
            && hash_equals($access->token_hash, hash('sha256', $token)), 404);
        $bridge = ProviderFulfillment::where('provider_contract_id', $cotizacion->providerContract->id)->first();
        $shipment = $bridge?->shipment;
        if ($download) {
            abort_unless($shipment, 404);

            return $this->pdf($cotizacion, $bridge, route('b2c.provider-fulfillment', $cotizacion->providerContract->uuid));
        }

        return response()->view('b2c.provider-fulfillment', ['cotizacion' => $cotizacion, 'shipment' => $shipment, 'recoveryToken' => $token])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(B2cCotizacion $cotizacion)
    {
        $bridge = $this->authorized($cotizacion);
        $shipment = $bridge?->shipment;

        return response()->view('b2c.provider-fulfillment', compact('cotizacion', 'shipment'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function guide(B2cCotizacion $cotizacion)
    {
        $bridge = $this->authorized($cotizacion);
        abort_unless($bridge, 404);

        return $this->pdf($cotizacion, $bridge, route('b2c.provider-fulfillment', $cotizacion->providerContract->uuid));
    }

    private function pdf(B2cCotizacion $cotizacion, ProviderFulfillment $bridge, string $url)
    {
        if (! class_exists(TCPDF::class)) {
            require_once base_path('vendor/tecnickcom/tcpdf/tcpdf.php');
        }
        $pdf = app(LocalGuidePdfRenderer::class)->render($bridge->shipment, $url);

        return response($pdf, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="local-guide.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    private function authorized(B2cCotizacion $quote): ?ProviderFulfillment
    {
        abort_unless($quote->provider === 'tenant_provider_local', 404);
        app(ProviderContractSelectionService::class)->assertCheckoutScope($quote);

        return ProviderFulfillment::where('provider_contract_id', $quote->providerContract->id)->first();
    }
}
