<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\CfdiZipValidationException;
use App\Models\ApiBillingRequest;
use App\Models\B2cInvoiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class InvoiceFulfillmentService
{
    public function __construct(
        private CfdiZipProcessor $zipProcessor
    ) {
    }

    public function fulfillFromManualZip(
        B2cInvoiceRequest $invoiceRequest,
        UploadedFile $zipFile,
        int $managerId
    ): B2cInvoiceRequest {
        $storedPaths = [];

        try {
            $processedCfdi = $this->zipProcessor->validateAndStore(
                $zipFile,
                $invoiceRequest
            );

            $storedPaths = $this->storedPathsFrom($processedCfdi);

            return $this->registerProcessedCfdi(
                $invoiceRequest,
                $processedCfdi,
                $managerId
            );
        } catch (CfdiZipValidationException $exception) {
            $this->registerProcessingError(
                $invoiceRequest,
                $managerId,
                $exception->getMessage()
            );

            throw $exception;
        } catch (ValidationException $exception) {
            $this->zipProcessor->deleteStoredPaths($storedPaths);
            throw $exception;
        } catch (Throwable $exception) {
            $this->zipProcessor->deleteStoredPaths($storedPaths);
            throw $exception;
        }
    }

    /**
     * Registra un CFDI ya validado y almacenado por cualquier origen.
     *
     * El flujo manual usa CfdiZipProcessor. Un proveedor futuro de API Hub
     * puede entregar el mismo arreglo sin modificar controladores, vistas
     * ni la estructura principal de la solicitud.
     *
     * @param array{
     *     pdf_path:string,
     *     xml_path:string,
     *     cfdi_uuid:string,
     *     cfdi_rfc_emisor:string,
     *     cfdi_rfc_receptor:string,
     *     cfdi_nombre_receptor:?string,
     *     cfdi_total:string,
     *     cfdi_fecha_emision:string,
     *     cfdi_fecha_timbrado:string
     * } $processedCfdi
     */
    public function registerProcessedCfdi(
        B2cInvoiceRequest $invoiceRequest,
        array $processedCfdi,
        int $managerId
    ): B2cInvoiceRequest {
        $this->validateProcessedCfdiPayload($processedCfdi);

        return DB::transaction(function () use (
            $invoiceRequest,
            $processedCfdi,
            $managerId
        ): B2cInvoiceRequest {
            $lockedRequest = B2cInvoiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($invoiceRequest->getKey());

            if (! $lockedRequest->puedeCargarDocumentos()) {
                throw ValidationException::withMessages([
                    'cfdi_zip' => 'La solicitud cambió de estado antes de terminar la carga.',
                ]);
            }

            $uuidAlreadyUsed = B2cInvoiceRequest::query()
                ->where('cfdi_uuid', $processedCfdi['cfdi_uuid'])
                ->where('id', '<>', $lockedRequest->getKey())
                ->exists();

            $uuidUsedByApiHub = ApiBillingRequest::query()
                ->where('cfdi_uuid', $processedCfdi['cfdi_uuid'])
                ->exists();

            if ($uuidAlreadyUsed || $uuidUsedByApiHub) {
                throw ValidationException::withMessages([
                    'cfdi_zip' => 'El UUID fiscal ya está registrado en otra solicitud.',
                ]);
            }

            $lockedRequest->update([
                'status' => B2cInvoiceRequest::STATUS_FACTURADA,
                'managed_by_user_id' => $managerId,
                'facturada_at' => now(),
                'cfdi_uuid' => $processedCfdi['cfdi_uuid'],
                'cfdi_rfc_emisor' => $processedCfdi['cfdi_rfc_emisor'],
                'cfdi_rfc_receptor' => $processedCfdi['cfdi_rfc_receptor'],
                'cfdi_nombre_receptor' => $processedCfdi['cfdi_nombre_receptor'],
                'cfdi_total' => $processedCfdi['cfdi_total'],
                'cfdi_fecha_emision' => $processedCfdi['cfdi_fecha_emision'],
                'cfdi_fecha_timbrado' => $processedCfdi['cfdi_fecha_timbrado'],
                'pdf_path' => $processedCfdi['pdf_path'],
                'xml_path' => $processedCfdi['xml_path'],
                'error_message' => null,
            ]);

            return $lockedRequest->refresh();
        });
    }

    private function registerProcessingError(
        B2cInvoiceRequest $invoiceRequest,
        int $managerId,
        string $message
    ): void {
        $invoiceRequest->update([
            'managed_by_user_id' => $managerId,
            'error_message' => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $processedCfdi
     * @return array<int, string>
     */
    private function storedPathsFrom(array $processedCfdi): array
    {
        return array_values(array_filter([
            isset($processedCfdi['pdf_path'])
                ? (string) $processedCfdi['pdf_path']
                : '',
            isset($processedCfdi['xml_path'])
                ? (string) $processedCfdi['xml_path']
                : '',
        ]));
    }

    /**
     * @param array<string, mixed> $processedCfdi
     */
    private function validateProcessedCfdiPayload(array $processedCfdi): void
    {
        $requiredFields = [
            'pdf_path',
            'xml_path',
            'cfdi_uuid',
            'cfdi_rfc_emisor',
            'cfdi_rfc_receptor',
            'cfdi_nombre_receptor',
            'cfdi_total',
            'cfdi_fecha_emision',
            'cfdi_fecha_timbrado',
        ];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $processedCfdi)) {
                throw new InvalidArgumentException(
                    "Falta el dato obligatorio {$field} para registrar el CFDI."
                );
            }
        }

        foreach ([
            'pdf_path',
            'xml_path',
            'cfdi_uuid',
            'cfdi_rfc_emisor',
            'cfdi_rfc_receptor',
            'cfdi_total',
            'cfdi_fecha_emision',
            'cfdi_fecha_timbrado',
        ] as $field) {
            if (trim((string) $processedCfdi[$field]) === '') {
                throw new InvalidArgumentException(
                    "El dato {$field} no puede estar vacío al registrar el CFDI."
                );
            }
        }
    }
}
