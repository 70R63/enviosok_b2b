<?php

namespace App\Services\Billing;

use App\Models\B2cInvoiceRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class InvoiceDocumentDownloadService
{
    private const FORMAT_PDF = 'pdf';
    private const FORMAT_XML = 'xml';
    private const FORMAT_ZIP = 'zip';

    /**
     * @return array<int, string>
     */
    public static function allowedFormats(): array
    {
        return [
            self::FORMAT_PDF,
            self::FORMAT_XML,
            self::FORMAT_ZIP,
        ];
    }

    public function download(
        B2cInvoiceRequest $invoiceRequest,
        string $format
    ): Response {
        $format = strtolower(trim($format));

        abort_unless(
            in_array($format, self::allowedFormats(), true),
            404
        );

        abort_unless(
            $invoiceRequest->puedeDescargarDocumentos(),
            404,
            'Los documentos fiscales no están disponibles.'
        );

        return match ($format) {
            self::FORMAT_PDF => $this->downloadStoredFile(
                $invoiceRequest,
                (string) $invoiceRequest->pdf_path,
                'pdf',
                'application/pdf'
            ),
            self::FORMAT_XML => $this->downloadStoredFile(
                $invoiceRequest,
                (string) $invoiceRequest->xml_path,
                'xml',
                'application/xml; charset=UTF-8'
            ),
            self::FORMAT_ZIP => $this->downloadZip($invoiceRequest),
        };
    }

    private function downloadStoredFile(
        B2cInvoiceRequest $invoiceRequest,
        string $storedPath,
        string $extension,
        string $mimeType
    ): Response {
        $disk = Storage::disk('local');

        abort_unless(
            $storedPath !== '' && $disk->exists($storedPath),
            404,
            'El documento fiscal solicitado no existe.'
        );

        return $disk->download(
            $storedPath,
            $this->documentBaseName($invoiceRequest) . '.' . $extension,
            $this->downloadHeaders($mimeType)
        );
    }

    private function downloadZip(
        B2cInvoiceRequest $invoiceRequest
    ): Response {
        abort_unless(
            class_exists(ZipArchive::class),
            503,
            'La generación del ZIP no está disponible en este momento.'
        );

        $disk = Storage::disk('local');
        $pdfPath = (string) $invoiceRequest->pdf_path;
        $xmlPath = (string) $invoiceRequest->xml_path;

        abort_unless(
            $pdfPath !== ''
            && $xmlPath !== ''
            && $disk->exists($pdfPath)
            && $disk->exists($xmlPath),
            404,
            'Los documentos fiscales no están completos.'
        );

        $temporaryDirectory = storage_path(
            'app/tmp/cfdi-downloads'
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
            abort(500, 'No fue posible preparar la descarga del ZIP.');
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
            abort(500, 'No fue posible generar el ZIP de la factura.');
        }

        $baseName = $this->documentBaseName($invoiceRequest);

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

            $closeResult = $zip->close();
            $zipClosed = true;

            if (! $closeResult) {
                throw new \RuntimeException(
                    'No fue posible cerrar correctamente el ZIP.'
                );
            }
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

            abort(500, 'No fue posible generar el ZIP de la factura.');
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
        B2cInvoiceRequest $invoiceRequest
    ): string {
        $uuid = preg_replace(
            '/[^A-Za-z0-9-]/',
            '',
            (string) $invoiceRequest->cfdi_uuid
        );

        $suffix = $uuid !== ''
            ? strtolower($uuid)
            : 'sin-uuid';

        return sprintf(
            'factura-zigo-cotizacion-%d-%s',
            $invoiceRequest->cotizacion_id,
            $suffix
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
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
    }
}
