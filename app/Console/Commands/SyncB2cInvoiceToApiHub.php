<?php

namespace App\Console\Commands;

use App\Models\B2cInvoiceRequest;
use App\Services\ApiHub\Billing\Internal\B2cInternalBillingSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncB2cInvoiceToApiHub extends Command
{
    protected $signature = 'zigo:billing:sync-b2c
        {invoiceRequestId : ID de b2c_invoice_requests}
        {--dry-run : Construye y valida el snapshot sin escribir datos}
        {--payment-form= : Forma de pago SAT de dos dígitos cuando el snapshot no la tiene}';

    protected $description =
        'Previsualiza o sincroniza una solicitud B2C con Billing Core de API Hub';

    public function handle(
        B2cInternalBillingSynchronizer $synchronizer
    ): int {
        $invoiceRequest = B2cInvoiceRequest::query()
            ->with([
                'cotizacion',
                'apiBillingRequest.items',
            ])
            ->find($this->argument('invoiceRequestId'));

        if (! $invoiceRequest) {
            $this->error(
                'No se encontró la solicitud B2C indicada.'
            );

            return self::FAILURE;
        }

        $paymentForm = $this->option('payment-form');

        try {
            if ($this->option('dry-run')) {
                $preview = $synchronizer->preview(
                    $invoiceRequest,
                    is_string($paymentForm)
                        ? $paymentForm
                        : null
                );

                if (
                    $invoiceRequest->status
                    !== B2cInvoiceRequest::STATUS_SOLICITADA
                ) {
                    $this->warn(
                        'La previsualización es válida, pero el estado actual no permite una sincronización real.'
                    );
                }

                $this->line(json_encode(
                    $preview,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
                ));
                $this->info(
                    'Dry-run concluido: no se escribieron datos.'
                );

                return self::SUCCESS;
            }

            $result = $synchronizer->sync(
                $invoiceRequest,
                is_string($paymentForm)
                    ? $paymentForm
                    : null
            );
            $apiRequest = $result['request'];

            $this->table(
                [
                    'B2C request',
                    'API request',
                    'External ID',
                    'Estado',
                    'Repetición',
                ],
                [[
                    $invoiceRequest->getKey(),
                    $apiRequest->getKey(),
                    $apiRequest->external_id,
                    $apiRequest->status,
                    $result['replayed'] ? 'Sí' : 'No',
                ]]
            );

            $this->info(
                'La solicitud B2C quedó vinculada con Billing Core.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            if (method_exists($exception, 'details')) {
                $details = $exception->details();

                if (is_array($details) && $details !== []) {
                    $this->line(json_encode(
                        $details,
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ));
                }
            }

            return self::FAILURE;
        }
    }
}
