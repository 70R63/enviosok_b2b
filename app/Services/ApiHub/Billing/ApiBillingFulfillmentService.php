<?php

namespace App\Services\ApiHub\Billing;

use App\Exceptions\Billing\CfdiZipValidationException;
use App\Models\ApiBillingRequest;
use App\Models\B2cInvoiceRequest;
use App\Services\ApiHub\Webhooks\BillingWebhookPublisher;
use App\Services\Billing\CfdiZipProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApiBillingFulfillmentService
{
    public function __construct(
        private readonly CfdiZipProcessor $zipProcessor,
        private readonly BillingWebhookPublisher $webhookPublisher
    ) {
    }

    public function fulfillFromManualZip(
        ApiBillingRequest $billingRequest,
        UploadedFile $zipFile,
        int $managerId
    ): ApiBillingRequest {
        $storedPaths = [];

        try {
            $processedCfdi = $this->zipProcessor
                ->validateAndStoreApi(
                    $zipFile,
                    $billingRequest
                );

            $storedPaths = array_values(array_filter([
                (string) ($processedCfdi['pdf_path'] ?? ''),
                (string) ($processedCfdi['xml_path'] ?? ''),
            ]));

            return $this->registerProcessedCfdi(
                $billingRequest,
                $processedCfdi,
                $managerId
            );
        } catch (CfdiZipValidationException $exception) {
            $this->registerProcessingError(
                $billingRequest,
                $managerId,
                $exception->getMessage()
            );

            throw $exception;
        } catch (ValidationException $exception) {
            $this->zipProcessor->deleteStoredPaths(
                $storedPaths
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->zipProcessor->deleteStoredPaths(
                $storedPaths
            );

            throw $exception;
        }
    }

    /**
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
        ApiBillingRequest $billingRequest,
        array $processedCfdi,
        int $managerId
    ): ApiBillingRequest {
        return DB::transaction(function () use (
            $billingRequest,
            $processedCfdi,
            $managerId
        ): ApiBillingRequest {
            $lockedRequest = ApiBillingRequest::query()
                ->lockForUpdate()
                ->findOrFail($billingRequest->getKey());

            if (! $lockedRequest->canUploadDocuments()) {
                throw ValidationException::withMessages([
                    'cfdi_zip' =>
                        'La solicitud cambió de estado antes de terminar la carga.',
                ]);
            }

            $uuid = strtoupper(
                trim((string) $processedCfdi['cfdi_uuid'])
            );

            $usedByApiHub = ApiBillingRequest::query()
                ->where('cfdi_uuid', $uuid)
                ->where(
                    'id',
                    '<>',
                    $lockedRequest->getKey()
                )
                ->exists();

            $usedByB2c = B2cInvoiceRequest::query()
                ->where('cfdi_uuid', $uuid)
                ->exists();

            if ($usedByApiHub || $usedByB2c) {
                throw ValidationException::withMessages([
                    'cfdi_zip' =>
                        'El UUID fiscal ya está registrado en otra solicitud.',
                ]);
            }

            $lockedRequest->update([
                'status' => ApiBillingRequest::STATUS_FACTURADA,
                'managed_by_user_id' => $managerId,
                'provider_code' => 'MANUAL_CRM',
                'provider_reference' => $uuid,
                'cfdi_uuid' => $uuid,
                'cfdi_rfc_emisor' =>
                    $processedCfdi['cfdi_rfc_emisor'],
                'cfdi_rfc_receptor' =>
                    $processedCfdi['cfdi_rfc_receptor'],
                'cfdi_nombre_receptor' =>
                    $processedCfdi['cfdi_nombre_receptor'],
                'cfdi_total' => $processedCfdi['cfdi_total'],
                'cfdi_fecha_emision' =>
                    $processedCfdi['cfdi_fecha_emision'],
                'cfdi_fecha_timbrado' =>
                    $processedCfdi['cfdi_fecha_timbrado'],
                'pdf_path' => $processedCfdi['pdf_path'],
                'xml_path' => $processedCfdi['xml_path'],
                'issued_at' => now(),
                'error_code' => null,
                'error_message' => null,
                'response_payload' => [
                    'status' => ApiBillingRequest::STATUS_FACTURADA,
                    'provider' => 'MANUAL_CRM',
                    'uuid' => $uuid,
                ],
            ]);

            $lockedRequest->refresh();

            $this->webhookPublisher->publish(
                $lockedRequest,
                BillingWebhookPublisher::EVENT_ISSUED
            );

            return $lockedRequest;
        });
    }

    private function registerProcessingError(
        ApiBillingRequest $billingRequest,
        int $managerId,
        string $message
    ): void {
        $billingRequest->update([
            'managed_by_user_id' => $managerId,
            'error_code' => 'CFDI_VALIDATION_ERROR',
            'error_message' => $message,
        ]);
    }
}
