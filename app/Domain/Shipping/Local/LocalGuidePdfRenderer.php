<?php

namespace App\Domain\Shipping\Local;

use App\Domain\Shipping\Local\Models\LocalShipment;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use Throwable;
use App\Support\Presentation\AddressPresenter;

final class LocalGuidePdfRenderer
{
    public function render(LocalShipment $shipment, string $trackingUrl): string
    {
        $guide = is_array($shipment->guide_snapshot) ? $shipment->guide_snapshot : [];
        $brand = $this->text(data_get($guide, 'branding.brand_name'), 'ZIGO Local');
        $service = $this->text($guide['service_code'] ?? null, $shipment->service_code ?: 'Directo');
        $provider = $this->text($guide['provider'] ?? null, 'ZIGO Local');
        $pdf = new TCPDF('P', 'mm', [101.6, 152.4], true, 'UTF-8', false);
        $pdf->SetCreator('ZIGO Platform');
        $pdf->SetTitle('Guía '.$shipment->tracking_number);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetDrawColor(0, 0, 0);

        if (! $this->logo($pdf, $shipment, data_get($guide, 'branding.logo_path'))) {
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetXY(6, 6);
            $pdf->Cell(55, 6, $brand);
        }
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(6, 14);
        $pdf->Cell(55, 4, 'GUÍA DE ENVÍO');
        $pdf->SetXY(62, 6);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->MultiCell(33, 4, "SERVICIO\n".$service, 1, 'C');
        $pdf->SetXY(6, 20);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(89, 6, $shipment->tracking_number, 0, 1, 'C');
        $pdf->write1DBarcode($shipment->tracking_number, 'C128', 6, 27, 68, 15, .32, ['position' => 'S', 'border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => false]);
        $pdf->write2DBarcode($trackingUrl, 'QRCODE,M', 78, 26, 17, 17, ['border' => false, 'padding' => 0]);
        $pdf->SetXY(6, 43);
        $pdf->SetFont('helvetica', 'B', 6.5);
        $pdf->Cell(89, 3.5, 'RASTREA TU ENVÍO', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 5.8);
        $pdf->Cell(89, 3, $trackingUrl, 0, 1, 'C');

        $this->address($pdf, 49, 'RECOLECCIÓN / REMITENTE', $guide['sender'] ?? []);
        $this->address($pdf, 78, 'ENTREGA / DESTINATARIO', $guide['recipient'] ?? []);
        $this->package($pdf, 107, $guide['package'] ?? [], $guide['reference'] ?? null);
        $pdf->SetXY(6, 135);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(89, 5, 'Servicio: '.$service.'     Proveedor: '.$provider, 1, 1, 'C');
        try {
            $issued = data_get($guide, 'issued_at') ? \Carbon\Carbon::parse(data_get($guide, 'issued_at'))->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
        } catch (Throwable) {
            $issued = now()->format('d/m/Y H:i');
        }
        $pdf->SetXY(6, 143);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(89, 3, 'Generado por ZIGO Platform', 0, 1, 'C');
        $pdf->Cell(89, 3, 'Fecha de emisión: '.$issued, 0, 1, 'C');

        return $pdf->Output('', 'S');
    }

    private function address(TCPDF $pdf, float $y, string $title, mixed $party): void
    {
        $party = is_array($party) ? $party : [];
        $phone = $this->text($party['phone'] ?? null, null);
        $lines = AddressPresenter::lines($party);
        if ($phone) $lines[] = 'Tel. '.$phone;
        $pdf->Rect(6, $y, 89, 27);
        $pdf->SetFillColor(238, 238, 238);
        $pdf->SetXY(6, $y);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(89, 5, $title, 0, 1, 'L', true);
        $pdf->SetXY(8, $y + 6);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->MultiCell(85, 4, implode("\n", $lines), 0, 'L', false, 1, '', '', true, 0, false, true, 20, 'T');
    }

    private function package(TCPDF $pdf, float $y, mixed $package, mixed $reference): void
    {
        $package = is_array($package) ? $package : [];
        $type = ucfirst(mb_strtolower($this->text($package['type'] ?? null, 'Paquete')));
        $lines = [$type, 'Peso: '.$this->text($package['weight'] ?? null, $type === 'Sobre' ? '1' : '—').' kg'];
        $dimensions = [$package['length'] ?? null, $package['width'] ?? null, $package['height'] ?? null];
        if ($type !== 'Sobre' && collect($dimensions)->every(fn ($value) => is_scalar($value) && trim((string) $value) !== '')) {
            $lines[] = 'Medidas: '.implode(' × ', array_map(fn ($value) => trim((string) $value), $dimensions)).' cm';
        }
        if (($reference = $this->text($reference, null)) !== null) $lines[] = 'Referencia: '.$reference;
        $pdf->Rect(6, $y, 89, 25);
        $pdf->SetFillColor(238, 238, 238);
        $pdf->SetXY(6, $y);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(89, 5, 'PAQUETE', 0, 1, 'L', true);
        $pdf->SetXY(8, $y + 6);
        $pdf->SetFont('helvetica', '', 8);
        foreach ($lines as $line) $pdf->Cell(85, 4, $line, 0, 1, 'L');
    }

    private function logo(TCPDF $pdf, LocalShipment $shipment, mixed $path): bool
    {
        $tenant = $shipment->tenant()->first();
        if (! $tenant || ! is_string($path) || ! str_starts_with($path, 'tenant-branding/'.$tenant->uuid.'/')) return false;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) return false;
        try {
            $image = getimagesize($disk->path($path));
            if ($image === false || ! in_array($image['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) return false;
            $pdf->Image($disk->path($path), 6, 6, 50, 8, '', '', '', false, 300, '', false, false, 0, true);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function text(mixed $value, ?string $fallback): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : $fallback;
    }
}
