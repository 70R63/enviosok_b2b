<?php

namespace App\Services\ApiHub\Billing;

use App\Exceptions\ApiHub\Billing\BillingApiException;
use App\Models\ApiBillingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ApiBillingDocumentDownloadService
{
    private const FORMATS = [
        'pdf',
        'xml',
        'zip',
    ];

    public function download(
        ApiBillingRequest $billingRequest,
        string $format
    ): Response {
        $format = strtolower(trim($format));

        if (! in_array($format, self::FORMATS, true)) {
            throw new BillingApiException(
                'El formato solicitado no está disponible.',
                'INVALID_DOCUMENT_FORMAT',
                404
            );
        }

        if (! $billingRequest->documentsReady()) {
            throw new BillingApiException(
                'Los documentos fiscales todavía no están disponibles.',
                'BILLING_DOCUMENTS_NOT_READY',
                404
            );
        }

        return match ($format) {
            'pdf' => $this->downloadStoredFile(
                $billingRequest,
                (string) $billingRequest->pdf_path,
                'pdf',
                'application/pdf'
            ),
            'xml' => $this->downloadStoredFile(
                $billingRequest,
                (string) $billingRequest->xml_path,
                'xml',
                'application/xml; charset=UTF-8'
            ),
            'zip' => $this->downloadZip($billingRequest),
        };
    }

    private function downloadStoredFile(
        ApiBillingRequest $billingRequest,
        string $storedPath,
        string $extension,
        string $mimeType
    ): Response {
        $disk = Storage::disk('local');

        if ($storedPath === '' || ! $disk->exists($storedPath)) {
            throw new BillingApiException(
                'El documento fiscal no se encuentra disponible.',
                'BILLING_DOCUMENT_NOT_FOUND',
                404
            );
        }

        return response()->download(
            $disk->path($storedPath),
            $this->documentBaseName($billingRequest)
                . '.'
                . $extension,
            $this->downloadHeaders($mimeType)
        );
    }

    private function downloadZip(
        ApiBillingRequest $billingRequest
    ): Response {
        if (! class_exists(ZipArchive::class)) {
            throw new BillingApiException(
                'La generación del ZIP no está disponible.',
                'ZIP_EXTENSION_UNAVAILABLE',
                503
            );
        }

        $disk = Storage::disk('local');
        $pdfPath = (string) $billingRequest->pdf_path;
        $xmlPath = (string) $billingRequest->xml_path;

        if (
            $pdfPath === ''
            || $xmlPath === ''
            || ! $disk->exists($pdfPath)
            || ! $disk->exists($xmlPath)
        ) {
            throw new BillingApiException(
                'Los documentos fiscales no están completos.',
                'BILLING_DOCUMENTS_INCOMPLETE',
                404
            );
        }

        $temporaryDirectory = storage_path(
            'app/tmp/api-billing-downloads'
        );

        if (
            ! is_dir($temporaryDirectory)
            && ! File::makeDirectory(
                $temporaryDirectory,
                0700,
                true,
                true
            )
        ) {
            throw new BillingApiException(
                'No fue posible preparar la descarga del ZIP.',
                'BILLING_ZIP_TEMPORARY_DIRECTORY_ERROR',
                500
            );
        }

        $temporaryZip = $temporaryDirectory
            . DIRECTORY_SEPARATOR
            . Str::uuid()->toString()
            . '.zip';

        $zip = new ZipArchive();
        $openResult = $zip->open(
            $temporaryZip,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($openResult !== true) {
            throw new BillingApiException(
                'No fue posible generar el ZIP de la factura.',
                'BILLING_ZIP_CREATE_ERROR',
                500
            );
        }

        $baseName = $this->documentBaseName(
            $billingRequest
        );
        $zipClosed = false;

        try {
            $pdfAdded = $zip->addFile(
                $disk->path($pdfPath),
                $baseName . '.pdf'
            );
            $xmlAdded = $zip->addFile(
                $disk->path($xmlPath),
                $baseName . '.xml'
            );

            if (! $pdfAdded || ! $xmlAdded) {
                throw new \RuntimeException(
                    'No fue posible agregar los documentos al ZIP.'
                );
            }

            if (! $zip->close()) {
                throw new \RuntimeException(
                    'No fue posible cerrar correctamente el ZIP.'
                );
            }

            $zipClosed = true;
        } catch (\Throwable $exception) {
            if (! $zipClosed) {
                try {
                    $zip->close();
                } catch (\Throwable $closeException) {
                    report($closeException);
                }
            }

            File::delete($temporaryZip);
            report($exception);

            throw new BillingApiException(
                'No fue posible generar el ZIP de la factura.',
                'BILLING_ZIP_CREATE_ERROR',
                500
            );
        }

        return response()
            ->download(
                $temporaryZip,
                $baseName . '.zip',
                $this->downloadHeaders('application/zip')
            )
            ->deleteFileAfterSend(true);
    }

    private function documentBaseName(
        ApiBillingRequest $billingRequest
    ): string {
        $externalId = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '-',
            (string) $billingRequest->external_id
        );
        $uuid = preg_replace(
            '/[^A-Za-z0-9-]/',
            '',
            (string) $billingRequest->cfdi_uuid
        );

        return sprintf(
            'factura-%s-%s',
            strtolower($externalId ?: 'solicitud'),
            strtolower($uuid ?: 'sin-uuid')
        );
    }

    /**
     * @return array<string, string>
     */
    private function downloadHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' =>
                'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
    }
}
