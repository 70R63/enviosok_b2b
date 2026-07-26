<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\CfdiZipValidationException;
use App\Models\ApiBillingRequest;
use App\Models\B2cInvoiceRequest;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class CfdiZipProcessor
{
    private const MAX_ZIP_BYTES = 15 * 1024 * 1024;
    private const MAX_PDF_BYTES = 15 * 1024 * 1024;
    private const MAX_XML_BYTES = 5 * 1024 * 1024;
    private const MAX_TOTAL_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;
    private const MAX_COMPRESSION_RATIO = 100;

    /**
     * @return array{
     *     pdf_path:string,
     *     xml_path:string,
     *     cfdi_uuid:string,
     *     cfdi_rfc_emisor:string,
     *     cfdi_rfc_receptor:string,
     *     cfdi_nombre_receptor:?string,
     *     cfdi_total:string,
     *     cfdi_fecha_emision:string,
     *     cfdi_fecha_timbrado:string
     * }
     */
    public function validateAndStore(
        UploadedFile $zipFile,
        B2cInvoiceRequest $invoiceRequest
    ): array {
        return $this->processAndStore(
            $zipFile,
            (string) $invoiceRequest->rfc,
            (float) $invoiceRequest->monto,
            sprintf(
                'facturacion/%d/%d',
                $invoiceRequest->user_id,
                $invoiceRequest->getKey()
            )
        );
    }

    /**
     * @return array{
     *     pdf_path:string,
     *     xml_path:string,
     *     cfdi_uuid:string,
     *     cfdi_rfc_emisor:string,
     *     cfdi_rfc_receptor:string,
     *     cfdi_nombre_receptor:?string,
     *     cfdi_total:string,
     *     cfdi_fecha_emision:string,
     *     cfdi_fecha_timbrado:string
     * }
     */
    public function validateAndStoreApi(
        UploadedFile $zipFile,
        ApiBillingRequest $billingRequest
    ): array {
        return $this->processAndStore(
            $zipFile,
            (string) $billingRequest->customer_rfc,
            (float) $billingRequest->total,
            sprintf(
                'facturacion/api-hub/%d/%s/%d',
                $billingRequest->api_client_id,
                strtolower((string) $billingRequest->environment),
                $billingRequest->getKey()
            )
        );
    }

    /**
     * @return array{
     *     pdf_path:string,
     *     xml_path:string,
     *     cfdi_uuid:string,
     *     cfdi_rfc_emisor:string,
     *     cfdi_rfc_receptor:string,
     *     cfdi_nombre_receptor:?string,
     *     cfdi_total:string,
     *     cfdi_fecha_emision:string,
     *     cfdi_fecha_timbrado:string
     * }
     */
    private function processAndStore(
        UploadedFile $zipFile,
        string $expectedRfc,
        float $expectedTotal,
        string $storagePrefix
    ): array {
        $this->validateUploadedZip($zipFile);

        $temporaryDirectory = storage_path(
            'app/tmp/cfdi/' . Str::uuid()->toString()
        );

        if (! File::makeDirectory($temporaryDirectory, 0700, true, true)) {
            throw new CfdiZipValidationException(
                'No fue posible preparar el directorio temporal para validar el ZIP.'
            );
        }

        $storedPaths = [];

        try {
            $extractedFiles = $this->inspectAndExtract(
                $zipFile->getRealPath(),
                $temporaryDirectory
            );

            $this->validatePdf($extractedFiles['pdf']);

            $metadata = $this->readCfdiXml($extractedFiles['xml']);
            $this->validateAgainstExpectedData(
                $metadata,
                $expectedRfc,
                $expectedTotal
            );

            $basePath = trim($storagePrefix, '/')
                . '/'
                . strtolower($metadata['cfdi_uuid']);

            $pdfPath = $basePath . '/factura.pdf';
            $xmlPath = $basePath . '/factura.xml';

            $this->storePrivateFile($extractedFiles['pdf'], $pdfPath);
            $storedPaths[] = $pdfPath;

            $this->storePrivateFile($extractedFiles['xml'], $xmlPath);
            $storedPaths[] = $xmlPath;

            return array_merge(
                $metadata,
                [
                    'pdf_path' => $pdfPath,
                    'xml_path' => $xmlPath,
                ]
            );
        } catch (CfdiZipValidationException $exception) {
            $this->deleteStoredPaths($storedPaths);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->deleteStoredPaths($storedPaths);
            report($exception);

            throw new CfdiZipValidationException(
                'No fue posible procesar el ZIP fiscal. Revisa que el archivo no esté dañado.'
            );
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    /**
     * @param array<int, string> $paths
     */
    public function deleteStoredPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '') {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function validateUploadedZip(UploadedFile $zipFile): void
    {
        if (! $zipFile->isValid()) {
            throw new CfdiZipValidationException(
                'El ZIP no se recibió correctamente. Intenta cargarlo nuevamente.'
            );
        }

        if (strtolower($zipFile->getClientOriginalExtension()) !== 'zip') {
            throw new CfdiZipValidationException(
                'El archivo debe tener extensión .zip.'
            );
        }

        $size = (int) $zipFile->getSize();

        if ($size <= 0 || $size > self::MAX_ZIP_BYTES) {
            throw new CfdiZipValidationException(
                'El ZIP debe pesar como máximo 15 MB.'
            );
        }

        $realPath = $zipFile->getRealPath();

        if ($realPath === false || ! is_file($realPath)) {
            throw new CfdiZipValidationException(
                'No fue posible leer el ZIP cargado.'
            );
        }

        $mimeType = $this->detectMimeType($realPath);
        $allowedMimeTypes = [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'multipart/x-zip',
            'application/octet-stream',
        ];

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new CfdiZipValidationException(
                'El tipo MIME del archivo no corresponde a un ZIP válido.'
            );
        }

        $signature = file_get_contents($realPath, false, null, 0, 4);
        $validSignatures = [
            "PK\x03\x04",
            "PK\x05\x06",
            "PK\x07\x08",
        ];

        if (! in_array($signature, $validSignatures, true)) {
            throw new CfdiZipValidationException(
                'La firma interna del archivo no corresponde a un ZIP válido.'
            );
        }
    }

    private function detectMimeType(string $path): string
    {
        if (! function_exists('finfo_open')) {
            throw new CfdiZipValidationException(
                'La extensión Fileinfo de PHP es necesaria para validar el ZIP.'
            );
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($fileInfo === false) {
            throw new CfdiZipValidationException(
                'No fue posible iniciar la validación MIME del ZIP.'
            );
        }

        try {
            return (string) finfo_file($fileInfo, $path);
        } finally {
            finfo_close($fileInfo);
        }
    }

    /**
     * @return array{pdf:string, xml:string}
     */
    private function inspectAndExtract(
        string $zipPath,
        string $temporaryDirectory
    ): array {
        if (! class_exists(ZipArchive::class)) {
            throw new CfdiZipValidationException(
                'La extensión ZIP de PHP no está habilitada en el servidor.'
            );
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::RDONLY);

        if ($openResult !== true) {
            throw new CfdiZipValidationException(
                'El archivo ZIP está dañado o no puede abrirse.'
            );
        }

        try {
            if ($zip->numFiles <= 0) {
                throw new CfdiZipValidationException(
                    'El ZIP está vacío.'
                );
            }

            $entries = [];
            $totalUncompressedBytes = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new CfdiZipValidationException(
                        'No fue posible inspeccionar todos los archivos del ZIP.'
                    );
                }

                $entryName = (string) ($stat['name'] ?? '');
                $normalizedName = $this->validateEntryName($entryName);

                if (str_ends_with($normalizedName, '/')) {
                    continue;
                }

                $this->rejectSymbolicLink($zip, $index);

                if ((int) ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new CfdiZipValidationException(
                        'El ZIP no debe contener archivos cifrados o protegidos con contraseña.'
                    );
                }

                $extension = strtolower(pathinfo($normalizedName, PATHINFO_EXTENSION));

                if (! in_array($extension, ['pdf', 'xml'], true)) {
                    throw new CfdiZipValidationException(
                        'El ZIP debe contener únicamente un PDF y un XML.'
                    );
                }

                if (isset($entries[$extension])) {
                    throw new CfdiZipValidationException(
                        'El ZIP debe contener exactamente un PDF y un XML.'
                    );
                }

                $uncompressedSize = (int) ($stat['size'] ?? 0);
                $compressedSize = (int) ($stat['comp_size'] ?? 0);
                $maxEntrySize = $extension === 'pdf'
                    ? self::MAX_PDF_BYTES
                    : self::MAX_XML_BYTES;

                if ($uncompressedSize <= 0 || $uncompressedSize > $maxEntrySize) {
                    throw new CfdiZipValidationException(
                        strtoupper($extension) . ' excede el tamaño permitido dentro del ZIP.'
                    );
                }

                if (
                    $compressedSize > 0
                    && ($uncompressedSize / $compressedSize) > self::MAX_COMPRESSION_RATIO
                ) {
                    throw new CfdiZipValidationException(
                        'El ZIP presenta una relación de compresión insegura.'
                    );
                }

                $totalUncompressedBytes += $uncompressedSize;

                if ($totalUncompressedBytes > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                    throw new CfdiZipValidationException(
                        'El contenido descomprimido excede el límite permitido.'
                    );
                }

                $entries[$extension] = [
                    'name' => $entryName,
                    'size' => $uncompressedSize,
                ];
            }

            if (array_keys($entries) !== ['pdf', 'xml'] && array_keys($entries) !== ['xml', 'pdf']) {
                throw new CfdiZipValidationException(
                    'El ZIP debe contener exactamente un PDF y un XML.'
                );
            }

            $pdfPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'factura.pdf';
            $xmlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'factura.xml';

            $this->extractEntrySafely(
                $zip,
                $entries['pdf']['name'],
                $pdfPath,
                (int) $entries['pdf']['size']
            );

            $this->extractEntrySafely(
                $zip,
                $entries['xml']['name'],
                $xmlPath,
                (int) $entries['xml']['size']
            );

            return [
                'pdf' => $pdfPath,
                'xml' => $xmlPath,
            ];
        } finally {
            $zip->close();
        }
    }

    private function validateEntryName(string $entryName): string
    {
        if ($entryName === '' || str_contains($entryName, "\0")) {
            throw new CfdiZipValidationException(
                'El ZIP contiene un nombre de archivo inválido.'
            );
        }

        $normalizedName = str_replace('\\', '/', $entryName);

        if (
            str_starts_with($normalizedName, '/')
            || preg_match('/^[A-Za-z]:\//', $normalizedName) === 1
        ) {
            throw new CfdiZipValidationException(
                'El ZIP contiene rutas absolutas no permitidas.'
            );
        }

        $segments = explode('/', rtrim($normalizedName, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new CfdiZipValidationException(
                    'El ZIP contiene una ruta insegura.'
                );
            }
        }

        return $normalizedName;
    }

    private function rejectSymbolicLink(ZipArchive $zip, int $index): void
    {
        if (! method_exists($zip, 'getExternalAttributesIndex')) {
            return;
        }

        $operatingSystem = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return;
        }

        $fileType = ($attributes >> 16) & 0170000;

        if ($fileType === 0120000) {
            throw new CfdiZipValidationException(
                'El ZIP no debe contener enlaces simbólicos.'
            );
        }
    }

    private function extractEntrySafely(
        ZipArchive $zip,
        string $entryName,
        string $destination,
        int $expectedBytes
    ): void {
        $source = $zip->getStream($entryName);

        if ($source === false) {
            throw new CfdiZipValidationException(
                'No fue posible leer uno de los archivos dentro del ZIP.'
            );
        }

        $target = fopen($destination, 'wb');

        if ($target === false) {
            fclose($source);

            throw new CfdiZipValidationException(
                'No fue posible preparar los archivos temporales del CFDI.'
            );
        }

        $writtenBytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, 8192);

                if ($chunk === false) {
                    throw new CfdiZipValidationException(
                        'No fue posible descomprimir completamente el ZIP.'
                    );
                }

                $writtenBytes += strlen($chunk);

                if ($writtenBytes > $expectedBytes || $writtenBytes > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                    throw new CfdiZipValidationException(
                        'El contenido descomprimido no coincide con el tamaño declarado.'
                    );
                }

                if ($chunk !== '' && fwrite($target, $chunk) === false) {
                    throw new CfdiZipValidationException(
                        'No fue posible escribir los archivos temporales del CFDI.'
                    );
                }
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        if ($writtenBytes !== $expectedBytes) {
            throw new CfdiZipValidationException(
                'El ZIP está incompleto o presenta contenido inconsistente.'
            );
        }
    }

    private function validatePdf(string $pdfPath): void
    {
        $signature = file_get_contents($pdfPath, false, null, 0, 5);

        if ($signature !== '%PDF-') {
            throw new CfdiZipValidationException(
                'El archivo con extensión PDF no contiene una firma PDF válida.'
            );
        }
    }

    /**
     * @return array{
     *     cfdi_uuid:string,
     *     cfdi_rfc_emisor:string,
     *     cfdi_rfc_receptor:string,
     *     cfdi_nombre_receptor:?string,
     *     cfdi_total:string,
     *     cfdi_fecha_emision:string,
     *     cfdi_fecha_timbrado:string
     * }
     */
    private function readCfdiXml(string $xmlPath): array
    {
        $xml = file_get_contents($xmlPath);

        if ($xml === false || trim($xml) === '') {
            throw new CfdiZipValidationException(
                'El XML está vacío o no puede leerse.'
            );
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new CfdiZipValidationException(
                'El XML contiene declaraciones DTD o entidades no permitidas.'
            );
        }

        if (! class_exists(DOMDocument::class)) {
            throw new CfdiZipValidationException(
                'La extensión DOM/XML de PHP no está habilitada en el servidor.'
            );
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument();
            $document->resolveExternals = false;
            $document->substituteEntities = false;

            $loaded = $document->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT
            );

            if (! $loaded) {
                throw new CfdiZipValidationException(
                    'El XML no tiene una estructura válida.'
                );
            }

            $xpath = new DOMXPath($document);
            $comprobante = $xpath->query(
                '/*[local-name()="Comprobante"]'
            )?->item(0);
            $emisor = $xpath->query(
                '/*[local-name()="Comprobante"]/*[local-name()="Emisor"]'
            )?->item(0);
            $receptor = $xpath->query(
                '/*[local-name()="Comprobante"]/*[local-name()="Receptor"]'
            )?->item(0);
            $timbre = $xpath->query(
                '//*[local-name()="TimbreFiscalDigital"]'
            )?->item(0);

            if (
                ! $comprobante instanceof DOMElement
                || ! $emisor instanceof DOMElement
                || ! $receptor instanceof DOMElement
                || ! $timbre instanceof DOMElement
            ) {
                throw new CfdiZipValidationException(
                    'El XML no contiene la estructura completa de un CFDI timbrado.'
                );
            }

            $version = trim($comprobante->getAttribute('Version'));

            if ($version !== '4.0') {
                throw new CfdiZipValidationException(
                    'El XML debe corresponder a un CFDI versión 4.0.'
                );
            }

            $uuid = strtoupper(trim($timbre->getAttribute('UUID')));
            $rfcEmisor = $this->normalizeRfc($emisor->getAttribute('Rfc'));
            $rfcReceptor = $this->normalizeRfc($receptor->getAttribute('Rfc'));
            $nombreReceptor = trim($receptor->getAttribute('Nombre')) ?: null;
            $total = trim($comprobante->getAttribute('Total'));
            $fechaEmision = $this->normalizeCfdiDate(
                $comprobante->getAttribute('Fecha'),
                'fecha de emisión'
            );
            $fechaTimbrado = $this->normalizeCfdiDate(
                $timbre->getAttribute('FechaTimbrado'),
                'fecha de timbrado'
            );

            if (
                preg_match(
                    '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/',
                    $uuid
                ) !== 1
            ) {
                throw new CfdiZipValidationException(
                    'El UUID fiscal del XML no es válido.'
                );
            }

            if ($rfcEmisor === '' || $rfcReceptor === '') {
                throw new CfdiZipValidationException(
                    'El XML no contiene los RFC de emisor y receptor.'
                );
            }

            if (preg_match('/^\d+(?:\.\d{1,6})?$/', $total) !== 1) {
                throw new CfdiZipValidationException(
                    'El total del CFDI no tiene un formato numérico válido.'
                );
            }

            return [
                'cfdi_uuid' => $uuid,
                'cfdi_rfc_emisor' => $rfcEmisor,
                'cfdi_rfc_receptor' => $rfcReceptor,
                'cfdi_nombre_receptor' => $nombreReceptor,
                'cfdi_total' => number_format((float) $total, 2, '.', ''),
                'cfdi_fecha_emision' => $fechaEmision,
                'cfdi_fecha_timbrado' => $fechaTimbrado,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    /**
     * @param array{cfdi_rfc_receptor:string, cfdi_total:string} $metadata
     */
    private function validateAgainstExpectedData(
        array $metadata,
        string $expectedRfc,
        float $expectedTotal
    ): void {
        $requestRfc = $this->normalizeRfc($expectedRfc);

        if (! hash_equals($requestRfc, $metadata['cfdi_rfc_receptor'])) {
            throw new CfdiZipValidationException(
                'El RFC receptor del XML no coincide con el RFC de la solicitud.'
            );
        }

        $requestCents = (int) round($expectedTotal * 100);
        $cfdiCents = (int) round((float) $metadata['cfdi_total'] * 100);

        if ($requestCents !== $cfdiCents) {
            throw new CfdiZipValidationException(
                'El total del XML no coincide con el monto pagado de la solicitud.'
            );
        }
    }

    private function normalizeRfc(?string $rfc): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $rfc)) ?? '');
    }

    private function normalizeCfdiDate(string $value, string $fieldName): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/',
                $value,
                $matches
            ) !== 1
        ) {
            throw new CfdiZipValidationException(
                'La ' . $fieldName . ' del XML no tiene un formato válido.'
            );
        }

        $normalized = $matches[1] . ' ' . $matches[2];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized);
        $dateErrors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($dateErrors)
                && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)
            )
        ) {
            throw new CfdiZipValidationException(
                'La ' . $fieldName . ' del XML no representa una fecha válida.'
            );
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function storePrivateFile(string $sourcePath, string $targetPath): void
    {
        $source = fopen($sourcePath, 'rb');

        if ($source === false) {
            throw new CfdiZipValidationException(
                'No fue posible preparar los documentos fiscales para guardarlos.'
            );
        }

        try {
            $stored = Storage::disk('local')->put($targetPath, $source);
        } finally {
            fclose($source);
        }

        if (! $stored) {
            throw new CfdiZipValidationException(
                'No fue posible guardar los documentos fiscales en almacenamiento privado.'
            );
        }
    }
}
