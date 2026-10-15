<?php

namespace App\Http\Controllers\B2C;

use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderConsumerResolver;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderTrackingProjection;
use App\Domain\Shipping\Local\LocalGuidePdfRenderer;
use App\Http\Controllers\Controller;
use App\Models\B2cCotizacion;
use App\Models\B2cGuideRecoveryToken;
use Illuminate\Support\Facades\Storage;
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
        $this->authorizeRecovery($access, $token);
        $cotizacion = $access->cotizacion;
        $bridge = ProviderFulfillment::where('provider_contract_id', $cotizacion->providerContract->id)->first();
        $shipment = $bridge?->shipment;
        if ($download) {
            abort_unless($shipment, 404);

            return $this->pdf($cotizacion, $bridge, route('b2c.provider-fulfillment', $cotizacion->providerContract->uuid));
        }

        return response()->view('b2c.provider-fulfillment', ['cotizacion' => $cotizacion, 'shipment' => $shipment, 'bridge' => $bridge,
            'trackingProjection' => $this->projection($bridge), 'recoveryToken' => $token])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(B2cCotizacion $cotizacion)
    {
        $bridge = $this->authorized($cotizacion);
        $shipment = $bridge?->shipment;
        $trackingProjection = $this->projection($bridge);

        return response()->view('b2c.provider-fulfillment', compact('cotizacion', 'shipment', 'bridge', 'trackingProjection'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function guide(B2cCotizacion $cotizacion)
    {
        $bridge = $this->authorized($cotizacion);
        abort_unless($bridge, 404);

        return $this->pdf($cotizacion, $bridge, route('b2c.provider-fulfillment', $cotizacion->providerContract->uuid));
    }

    public function evidence(ProviderContract $contract, string $kind)
    {
        $bridge = $this->authorized(B2cCotizacion::findOrFail($contract->b2c_cotizacion_id));
        abort_unless($bridge, 404);
        return $this->evidenceFile($bridge, $kind);
    }

    public function recoveredEvidence(B2cGuideRecoveryToken $access, string $token, string $kind)
    {
        $this->authorizeRecovery($access, $token);
        $bridge = ProviderFulfillment::where('provider_contract_id', $access->cotizacion->providerContract->id)->firstOrFail();
        return $this->evidenceFile($bridge, $kind);
    }

    private function projection(?ProviderFulfillment $bridge): ?array
    {
        if (! $bridge) return null;
        $consumer = app(ProviderConsumerResolver::class)->current();
        abort_unless($consumer, 404);
        return app(ProviderTrackingProjection::class)->project($bridge, $consumer);
    }

    private function evidenceFile(ProviderFulfillment $bridge, string $kind)
    {
        abort_unless(in_array($kind, ['photo', 'signature'], true), 404);
        $consumer = app(ProviderConsumerResolver::class)->current();
        abort_unless($consumer, 404);
        $proof = app(ProviderTrackingProjection::class)->proof($bridge, $consumer);
        abort_unless($proof, 404);
        $path = $kind === 'photo' ? $proof->photo_path : $proof->signature_path;
        abort_unless($path && preg_match('#^pod/(photos|signatures)/[a-f0-9-]+\.(jpg|png|webp)$#i', $path)
            && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeRecovery(B2cGuideRecoveryToken $access, string $token): void
    {
        $cotizacion = $access->cotizacion;
        $offer = $cotizacion?->providerContract?->snapshot;
        $consumer = app(ProviderConsumerResolver::class)->current();
        abort_unless($access->exists && ! $access->revoked_at && $access->expires_at->isFuture()
            && $access->attempts <= $access->max_attempts && $cotizacion->provider === 'tenant_provider_local' && $offer && $consumer
            && (int) $consumer->id === (int) $offer->consumer_tenant_id
            && hash_equals($access->token_hash, hash('sha256', $token)), 404);
    }

    private function pdf(B2cCotizacion $cotizacion, ProviderFulfillment $bridge, string $url)
    {
        if (! class_exists(TCPDF::class)) {
            require_once base_path('vendor/tecnickcom/tcpdf/tcpdf.php');
        }
        $corporate = app(ProviderConsumerResolver::class)->corporate();
        if ($corporate && (int) $bridge->consumer_tenant_id === (int) $corporate->id) {
            $url = 'https://'.config('zigo_domains.portals.b2c.host').'/rastreo?'.http_build_query(['tracking_number' => $bridge->shipment->tracking_number]);
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
