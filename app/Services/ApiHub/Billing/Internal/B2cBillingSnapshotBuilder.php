<?php

namespace App\Services\ApiHub\Billing\Internal;

use App\Models\B2cInvoiceRequest;
use App\Services\ZigoProviderQuoteObservationService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class B2cBillingSnapshotBuilder
{
    public function build(
        B2cInvoiceRequest $invoiceRequest,
        ?string $paymentFormOverride = null
    ): array {
        $invoiceRequest->loadMissing('cotizacion');
        $cotizacion = $invoiceRequest->cotizacion;

        if (! $cotizacion) {
            throw new InvalidArgumentException(
                'La solicitud B2C no tiene una cotización relacionada.'
            );
        }

        $paymentStatus = strtolower(trim(
            (string) $invoiceRequest->payment_status
        ));

        if (! in_array(
            $paymentStatus,
            ['approved', 'saldo_prepago'],
            true
        )) {
            throw new InvalidArgumentException(
                'La solicitud B2C no contiene un pago confirmado.'
            );
        }

        $paymentMethod = strtoupper(trim(
            (string) $invoiceRequest->metodo_pago
        ));

        if (! in_array($paymentMethod, ['PUE', 'PPD'], true)) {
            throw new InvalidArgumentException(
                'El método de pago B2C debe ser PUE o PPD.'
            );
        }

        $paymentForm = $this->resolvePaymentForm(
            $invoiceRequest,
            $paymentFormOverride
        );

        $selection = app(
            ZigoProviderQuoteObservationService::class
        )->selectionMetadata($cotizacion);
        $commercialBreakdown = (array) (
            $selection['commercial_breakdown'] ?? []
        );
        $hasCommercialSnapshot = array_key_exists(
            'base',
            $commercialBreakdown
        );
        $total = $hasCommercialSnapshot
            ? $this->money($selection['customer_total'] ?? null)
            : $this->money($invoiceRequest->monto);

        if (
            $hasCommercialSnapshot
            && abs($total - $this->money($invoiceRequest->monto)) > 0.01
        ) {
            throw new InvalidArgumentException(
                'El total facturable no coincide con el snapshot comercial seleccionado.'
            );
        }
        $insurance = $cotizacion->requiere_seguro_envio
            ? $this->money($cotizacion->seguro_monto)
            : 0.0;

        if ($insurance > $total) {
            throw new InvalidArgumentException(
                'El seguro registrado supera el total pagado.'
            );
        }

        $shipping = $this->money($total - $insurance);

        if ($shipping <= 0) {
            throw new InvalidArgumentException(
                'El importe facturable del envío debe ser mayor que cero.'
            );
        }

        $items = [
            $this->shippingItem(
                $invoiceRequest,
                $shipping
            ),
        ];

        if ($insurance > 0) {
            $items[] = $this->insuranceItem(
                $invoiceRequest,
                $insurance
            );
        }

        return [
            'external_id' => $this->externalId($invoiceRequest),
            'source_system' => 'ZIGO_B2C',
            'customer' => [
                'rfc' => strtoupper(trim(
                    (string) $invoiceRequest->rfc
                )),
                'name' => trim(
                    (string) $invoiceRequest->razon_social
                ),
                'postal_code' => trim(
                    (string) $invoiceRequest->codigo_postal_fiscal
                ),
                'tax_regime' => trim(
                    (string) $invoiceRequest->regimen_fiscal
                ),
                'cfdi_use' => strtoupper(trim(
                    (string) $invoiceRequest->uso_cfdi
                )),
                'email' => strtolower(trim(
                    (string) $invoiceRequest->email_facturacion
                )),
            ],
            'payment' => [
                'reference' => $this->paymentReference(
                    $invoiceRequest
                ),
                'status' => 'PAID',
                'method' => $paymentMethod,
                'form' => $paymentForm,
                'currency' => 'MXN',
            ],
            'amounts' => [
                'subtotal' => $hasCommercialSnapshot
                    ? $this->money($selection['commercial_subtotal'] ?? null)
                    : $total,
                'discount' => 0.0,
                'tax' => $hasCommercialSnapshot
                    ? $this->money($selection['vat'] ?? null)
                    : 0.0,
                'shipping' => $shipping,
                'insurance' => $insurance,
                'total' => $total,
                'commercial_breakdown' => $hasCommercialSnapshot
                    ? $commercialBreakdown
                    : [],
            ],
            'items' => $items,
        ];
    }

    public function idempotencyKey(
        B2cInvoiceRequest $invoiceRequest
    ): string {
        return 'zigo-b2c-invoice-request-'
            . $invoiceRequest->getKey()
            . '-v1';
    }

    public function externalId(
        B2cInvoiceRequest $invoiceRequest
    ): string {
        return 'ZIGO-B2C-INVOICE-'
            . $invoiceRequest->getKey();
    }

    private function resolvePaymentForm(
        B2cInvoiceRequest $invoiceRequest,
        ?string $override
    ): string {
        $paymentForm = trim((string) $override);

        if ($paymentForm === '') {
            $paymentForm = trim(
                (string) $invoiceRequest->forma_pago
            );
        }

        if ($paymentForm === '') {
            $paymentForm = trim((string) config(
                'services.zigo_internal_billing.payment_forms.'
                . strtoupper((string) $invoiceRequest->payment_method)
            ));
        }

        if (preg_match('/^[0-9]{2}$/', $paymentForm) !== 1) {
            throw new InvalidArgumentException(
                'La forma de pago SAT no está disponible. Usa --payment-form=XX o configura el mapeo interno.'
            );
        }

        return $paymentForm;
    }

    private function paymentReference(
        B2cInvoiceRequest $invoiceRequest
    ): string {
        $reference = trim(
            (string) $invoiceRequest->payment_reference
        );

        if ($reference !== '') {
            return $reference;
        }

        return 'B2C-' . $invoiceRequest->cotizacion_id;
    }

    private function shippingItem(
        B2cInvoiceRequest $invoiceRequest,
        float $amount
    ): array {
        $cotizacion = $invoiceRequest->cotizacion;
        $description = trim(sprintf(
            'SERVICIO DE ENVÍO ZIGO %s %s',
            (string) $cotizacion->logistico,
            (string) $cotizacion->servicio
        ));

        return [
            'client_item_id' => 'b2c-cotizacion-'
                . $cotizacion->getKey()
                . '-shipping',
            'category' => 'SHIPPING',
            'product_service_code' => $this->requiredCode(
                'shipping_product_service_code',
                'servicio de envío'
            ),
            'unit_code' => $this->unitCode(),
            'description' => Str::limit(
                $description !== ''
                    ? $description
                    : 'SERVICIO DE ENVÍO ZIGO',
                255,
                ''
            ),
            'quantity' => 1,
            'unit_price' => $amount,
            'discount' => 0.0,
            'subtotal' => $amount,
            'tax_object' => $this->taxObject(),
            'tax_rate' => null,
            'tax_amount' => 0.0,
            'total' => $amount,
            'metadata' => $this->commercialMetadata(
                $invoiceRequest
            ),
        ];
    }

    private function insuranceItem(
        B2cInvoiceRequest $invoiceRequest,
        float $amount
    ): array {
        return [
            'client_item_id' => 'b2c-cotizacion-'
                . $invoiceRequest->cotizacion_id
                . '-insurance',
            'category' => 'INSURANCE',
            'product_service_code' => $this->requiredCode(
                'insurance_product_service_code',
                'protección de envío'
            ),
            'unit_code' => $this->unitCode(),
            'description' => 'PROTECCIÓN DE ENVÍO ZIGO',
            'quantity' => 1,
            'unit_price' => $amount,
            'discount' => 0.0,
            'subtotal' => $amount,
            'tax_object' => $this->taxObject(),
            'tax_rate' => null,
            'tax_amount' => 0.0,
            'total' => $amount,
            'metadata' => [
                'b2c_invoice_request_id' =>
                    $invoiceRequest->getKey(),
                'b2c_cotizacion_id' =>
                    $invoiceRequest->cotizacion_id,
                'declared_value' =>
                    $invoiceRequest->cotizacion->valor_declarado,
                'insurance_percentage' =>
                    $invoiceRequest->cotizacion->seguro_porcentaje,
                'insurance_vat_percentage' =>
                    $invoiceRequest->cotizacion
                        ->seguro_iva_porcentaje,
            ],
        ];
    }

    private function commercialMetadata(
        B2cInvoiceRequest $invoiceRequest
    ): array {
        $cotizacion = $invoiceRequest->cotizacion;
        $selection = app(
            ZigoProviderQuoteObservationService::class
        )->selectionMetadata($cotizacion);

        return [
            'b2c_invoice_request_id' =>
                $invoiceRequest->getKey(),
            'b2c_cotizacion_id' => $cotizacion->getKey(),
            'shipment_reference' => $cotizacion->referencia,
            'carrier' => $cotizacion->logistico,
            'service' => $cotizacion->servicio,
            'content' => $cotizacion->contenido,
            'tracking_number' => $cotizacion->tracking_number,
            'commercial_breakdown' => (array) (
                $selection['commercial_breakdown'] ?? []
            ),
            'commercial_subtotal' =>
                $selection['commercial_subtotal'] ?? null,
            'vat' => $selection['vat'] ?? null,
            'customer_total' =>
                $selection['customer_total'] ?? null,
            'origin_postal_code' => $cotizacion->cp_origen,
            'destination_postal_code' => $cotizacion->cp_destino,
        ];
    }

    private function requiredCode(
        string $configKey,
        string $concept
    ): string {
        $code = trim((string) config(
            'services.zigo_internal_billing.' . $configKey
        ));

        if (preg_match('/^[0-9]{8}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                "Configura una clave de producto o servicio válida para {$concept}."
            );
        }

        return $code;
    }

    private function unitCode(): string
    {
        $code = strtoupper(trim((string) config(
            'services.zigo_internal_billing.unit_code'
        )));

        if (preg_match('/^[A-Z0-9]{2,3}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'La clave de unidad interna no es válida.'
            );
        }

        return $code;
    }

    private function taxObject(): string
    {
        $code = trim((string) config(
            'services.zigo_internal_billing.tax_object'
        ));

        if (preg_match('/^[0-9]{2}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'El objeto de impuesto interno no es válido.'
            );
        }

        return $code;
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
