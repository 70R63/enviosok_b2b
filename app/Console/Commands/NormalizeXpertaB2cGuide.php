<?php

namespace App\Console\Commands;

use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\XpertaGuideResponseNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class NormalizeXpertaB2cGuide extends Command
{
    protected $signature = 'zigo:xperta-guide-normalize
        {cotizacion_id : ID de la cotización B2C}
        {--dry-run : Validar el snapshot sin modificar la cotización}
        {--confirm : Confirmar explícitamente la normalización}';

    protected $description = 'Normaliza offline una guía Xperta ya creada desde su snapshot';

    public function handle(XpertaGuideResponseNormalizer $normalizer): int
    {
        $cotizacion = B2cCotizacion::query()->find($this->argument('cotizacion_id'));
        if (!$cotizacion) return $this->commandFailure('Cotización no encontrada.');
        if ($cotizacion->hasGeneratedGuide()) {
            $this->info('La guía ya está generada; no se modificó la cotización.');
            return self::SUCCESS;
        }
        if ($cotizacion->payment_status !== 'approved') return $this->commandFailure('La cotización no tiene pago approved.');
        $snapshot = (array) $cotizacion->guia_response_snapshot;
        $normalized = $normalizer->normalize($snapshot);
        if (!$normalizer->isRecoverable($normalized)) return $this->commandFailure('El snapshot no contiene una guía Xperta exitosa recuperable.');

        $this->line('WayBill: ' . $normalized['waybill']);
        $this->line('Tracking: ' . $normalized['tracking']);
        $this->line('Documento: ' . $normalized['document_type']);
        if ($this->option('dry-run')) {
            $this->info('Dry-run completado; cero llamadas HTTP y cero modificaciones.');
            return self::SUCCESS;
        }
        if (!$this->option('confirm')) return $this->commandFailure('Agrega --confirm para normalizar la guía.');

        $documentPath = $this->storePdf($cotizacion, $normalized);

        DB::transaction(function () use ($cotizacion, $normalized, $documentPath): void {
            $locked = B2cCotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasGeneratedGuide()) return;
            $locked->forceFill([
                'guia_id' => $normalized['waybill'],
                'tracking_number' => $normalized['tracking'],
                'guia_provider_reference' => $normalized['provider_reference'],
                'guia_provider_request_number' => ctype_digit((string) $normalized['provider_request_number'])
                    ? (int) $normalized['provider_request_number'] : null,
                'guia_provider_status' => 'GENERADA',
                'guia_estatus' => 'GENERADA',
                'estatus' => 'GUIA_GENERADA',
                'guia_generated_at' => now(),
                'guia_generation_started_at' => null,
                'guia_last_error_code' => null,
                'guia_last_error_message' => null,
                'documento' => $documentPath,
                'guia_label_format' => $documentPath ? 'PDF' : null,
            ])->save();
        });
        Log::notice('Guía Xperta normalizada desde snapshot', [
            'cotizacion_id' => $cotizacion->id,
            'waybill' => $normalized['waybill'],
            'tracking' => $normalized['tracking'],
            'document_type' => $normalized['document_type'],
            'source' => 'guia_response_snapshot',
        ]);
        $this->info('Guía normalizada sin llamar a Xperta.');
        return self::SUCCESS;
    }

    private function commandFailure(string $message): int
    {
        $this->error($message);
        return self::FAILURE;
    }

    private function storePdf(B2cCotizacion $cotizacion, array $normalized): ?string
    {
        if ($normalized['document_type'] !== 'pdf_base64') return null;
        $encoded = preg_replace('#^data:application/pdf;base64,#i', '', trim((string) $normalized['document']));
        $binary = base64_decode($encoded, true);
        if ($binary === false || !str_starts_with($binary, '%PDF-')) return null;
        $path = 'private/b2c/labels/' . $cotizacion->id . '/' . Str::uuid() . '.pdf';
        if (!Storage::disk('local')->put($path, $binary)) return null;
        return $path;
    }
}
