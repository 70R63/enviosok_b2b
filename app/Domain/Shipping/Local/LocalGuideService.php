<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShipment;
use TCPDF;

final class LocalGuideService
{
    public function pdf(LocalShipment $shipment, string $tenantHost): string
    {
        if (! class_exists(TCPDF::class)) {
            require_once base_path('vendor/tecnickcom/tcpdf/tcpdf.php');
        }
        return app(LocalGuidePdfRenderer::class)->render($shipment, $this->trackingUrl($shipment, $tenantHost));
    }

    public function trackingUrl(LocalShipment $shipment, string $tenantHost): string
    {
        return 'https://'.$tenantHost.'/rastreo/'.rawurlencode($shipment->tracking_number);
    }

}
