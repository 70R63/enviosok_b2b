<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShipment;
use TCPDF;

final class LocalGuideService
{
    public function pdf(LocalShipment $shipment, string $tenantHost): string
    {
        $guide = $shipment->guide_snapshot;
        $pdf = new TCPDF('P', 'mm', [101.6, 152.4], true, 'UTF-8', false);
        $pdf->SetCreator('ZIGO Local');
        $pdf->SetTitle('Guía '.$shipment->tracking_number);
        $pdf->SetMargins(6, 6, 6);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->Cell(0, 8, (string) ($guide['branding']['brand_name'] ?? 'ZIGO Local'), 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, (string) ($guide['provider'] ?? 'ZIGO Local').' · '.(string) $guide['service_code'], 0, 1);
        $pdf->write1DBarcode($shipment->tracking_number, 'C128', 6, 22, 70, 17, 0.32, ['position' => 'S', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => true]);
        $trackingUrl = $this->trackingUrl($shipment, $tenantHost);
        $pdf->write2DBarcode($trackingUrl, 'QRCODE,M', 79, 22, 17, 17, ['border' => false, 'padding' => 0]);
        $pdf->SetY(43);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'REMITENTE', 0, 1);
        $this->address($pdf, $guide['sender'] ?? []);
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'DESTINATARIO', 0, 1);
        $this->address($pdf, $guide['recipient'] ?? []);
        $package = $guide['package'] ?? [];
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'PAQUETE', 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 4, sprintf('%s · %s kg · %s x %s x %s cm', $package['type'] ?? 'Paquete', $package['weight'] ?? '—', $package['length'] ?? '—', $package['width'] ?? '—', $package['height'] ?? '—'));
        if (! empty($guide['reference'])) {
            $pdf->MultiCell(0, 4, 'Referencia: '.$guide['reference']);
        }

        return $pdf->Output('', 'S');
    }

    public function trackingUrl(LocalShipment $shipment, string $tenantHost): string
    {
        return 'https://'.$tenantHost.'/rastreo/'.rawurlencode($shipment->tracking_number);
    }

    private function address(TCPDF $pdf, array $address): void
    {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 4, implode("\n", array_filter([$address['name'] ?? null, $address['address'] ?? null, isset($address['postal_code']) ? 'CP '.$address['postal_code'] : null, $address['phone'] ?? null])));
    }
}
