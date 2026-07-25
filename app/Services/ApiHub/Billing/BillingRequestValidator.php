<?php

namespace App\Services\ApiHub\Billing;

use App\Exceptions\ApiHub\Billing\BillingApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillingRequestValidator
{
    private const CATEGORIES = [
        'PRODUCT',
        'SERVICE',
        'SHIPPING',
        'INSURANCE',
    ];

    public function validate(Request $request): array
    {
        $idempotencyKey = trim(
            (string) $request->header('Idempotency-Key')
        );

        if (
            preg_match(
                '/^[A-Za-z0-9._:-]{8,120}$/',
                $idempotencyKey
            ) !== 1
        ) {
            throw new BillingApiException(
                'El header Idempotency-Key es obligatorio y debe tener entre 8 y 120 caracteres válidos.',
                'INVALID_IDEMPOTENCY_KEY',
                422
            );
        }

        $payload = $this->normalizeInput(
            $request->all()
        );

        $validator = Validator::make(
            $payload,
            [
                'external_id' => [
                    'required',
                    'string',
                    'max:120',
                    'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
                ],
                'source_system' => [
                    'required',
                    'string',
                    'max:60',
                    'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                ],
                'customer' => ['required', 'array'],
                'customer.rfc' => [
                    'required',
                    'string',
                    'max:13',
                    'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u',
                ],
                'customer.name' => [
                    'required',
                    'string',
                    'max:254',
                ],
                'customer.postal_code' => [
                    'required',
                    'regex:/^[0-9]{5}$/',
                ],
                'customer.tax_regime' => [
                    'required',
                    'regex:/^[0-9]{3}$/',
                ],
                'customer.cfdi_use' => [
                    'required',
                    'regex:/^[A-Z0-9]{3,4}$/',
                ],
                'customer.email' => [
                    'nullable',
                    'email:rfc',
                    'max:254',
                ],
                'payment' => ['required', 'array'],
                'payment.reference' => [
                    'required',
                    'string',
                    'max:150',
                ],
                'payment.status' => [
                    'required',
                    'in:PAID',
                ],
                'payment.method' => [
                    'required',
                    'in:PUE,PPD',
                ],
                'payment.form' => [
                    'required',
                    'regex:/^[0-9]{2}$/',
                ],
                'payment.date' => [
                    'nullable',
                    'date',
                ],
                'payment.currency' => [
                    'required',
                    'regex:/^[A-Z]{3}$/',
                ],
                'payment.exchange_rate' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                    'max:99999999',
                ],
                'amounts' => ['required', 'array'],
                'amounts.subtotal' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'amounts.discount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'amounts.tax' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'amounts.shipping' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'amounts.insurance' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'amounts.total' => [
                    'required',
                    'numeric',
                    'gt:0',
                    'max:999999999999.99',
                ],
                'items' => [
                    'required',
                    'array',
                    'min:1',
                    'max:100',
                ],
                'items.*.client_item_id' => [
                    'nullable',
                    'string',
                    'max:120',
                ],
                'items.*.category' => [
                    'required',
                    'in:' . implode(',', self::CATEGORIES),
                ],
                'items.*.product_service_code' => [
                    'required',
                    'regex:/^[0-9]{8}$/',
                ],
                'items.*.unit_code' => [
                    'required',
                    'regex:/^[A-Z0-9]{2,3}$/',
                ],
                'items.*.description' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'items.*.quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                    'max:9999999999',
                ],
                'items.*.unit_price' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:99999999.999999',
                ],
                'items.*.discount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'items.*.subtotal' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'items.*.tax_object' => [
                    'required',
                    'regex:/^[0-9]{2}$/',
                ],
                'items.*.tax_rate' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:1',
                ],
                'items.*.tax_amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'items.*.total' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                ],
                'items.*.metadata' => [
                    'nullable',
                    'array',
                ],
            ]
        );

        if ($validator->fails()) {
            throw new BillingApiException(
                'La solicitud de facturación contiene datos inválidos.',
                'VALIDATION_ERROR',
                422,
                $validator->errors()->toArray()
            );
        }

        if (
            $payload['payment']['currency'] !== 'MXN'
            && empty($payload['payment']['exchange_rate'])
        ) {
            throw new BillingApiException(
                'El tipo de cambio es obligatorio cuando la moneda no es MXN.',
                'EXCHANGE_RATE_REQUIRED',
                422,
                [
                    'payment.exchange_rate' => [
                        'Captura el tipo de cambio utilizado.',
                    ],
                ]
            );
        }

        $allowedCfdiUses = config(
            'b2c_fiscal.usos_cfdi_por_regimen.'
            . $payload['customer']['tax_regime']
        );

        if (!is_array($allowedCfdiUses)) {
            throw new BillingApiException(
                'El régimen fiscal del receptor no está soportado.',
                'UNSUPPORTED_TAX_REGIME',
                422,
                [
                    'customer.tax_regime' => [
                        $payload['customer']['tax_regime'],
                    ],
                ]
            );
        }

        if (
            !in_array(
                $payload['customer']['cfdi_use'],
                $allowedCfdiUses,
                true
            )
        ) {
            throw new BillingApiException(
                'El uso de CFDI no es compatible con el régimen fiscal del receptor.',
                'CFDI_USE_NOT_ALLOWED',
                422,
                [
                    'customer.tax_regime' => [
                        $payload['customer']['tax_regime'],
                    ],
                    'customer.cfdi_use' => [
                        $payload['customer']['cfdi_use'],
                    ],
                ]
            );
        }

        $payload = $this->normalizeValidatedAmounts(
            $payload
        );

        $this->assertItemTotals($payload['items']);
        $this->assertSummaryTotals($payload);

        $payload['idempotency_key'] = $idempotencyKey;

        return $payload;
    }

    public function payloadHash(array $payload): string
    {
        $hashPayload = $payload;
        unset($hashPayload['idempotency_key']);

        return hash(
            'sha256',
            json_encode(
                $this->sortRecursively($hashPayload),
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
            )
        );
    }

    private function normalizeInput(array $payload): array
    {
        $payload['external_id'] = trim(
            (string) ($payload['external_id'] ?? '')
        );
        $payload['source_system'] = strtoupper(trim(
            (string) ($payload['source_system'] ?? '')
        ));

        $payload['customer'] = is_array(
            $payload['customer'] ?? null
        ) ? $payload['customer'] : [];
        $payload['customer']['rfc'] = strtoupper(trim(
            (string) ($payload['customer']['rfc'] ?? '')
        ));
        $payload['customer']['name'] = trim(
            (string) ($payload['customer']['name'] ?? '')
        );
        $payload['customer']['postal_code'] = trim(
            (string) ($payload['customer']['postal_code'] ?? '')
        );
        $payload['customer']['tax_regime'] = trim(
            (string) ($payload['customer']['tax_regime'] ?? '')
        );
        $payload['customer']['cfdi_use'] = strtoupper(trim(
            (string) ($payload['customer']['cfdi_use'] ?? '')
        ));
        $payload['customer']['email'] = isset(
            $payload['customer']['email']
        ) ? strtolower(trim(
            (string) $payload['customer']['email']
        )) : null;

        $payload['payment'] = is_array(
            $payload['payment'] ?? null
        ) ? $payload['payment'] : [];
        $payload['payment']['reference'] = trim(
            (string) ($payload['payment']['reference'] ?? '')
        );
        $payload['payment']['status'] = strtoupper(trim(
            (string) ($payload['payment']['status'] ?? '')
        ));
        $payload['payment']['method'] = strtoupper(trim(
            (string) ($payload['payment']['method'] ?? '')
        ));
        $payload['payment']['form'] = trim(
            (string) ($payload['payment']['form'] ?? '')
        );
        $payload['payment']['currency'] = strtoupper(trim(
            (string) ($payload['payment']['currency'] ?? 'MXN')
        ));

        $payload['amounts'] = is_array(
            $payload['amounts'] ?? null
        ) ? $payload['amounts'] : [];
        $payload['amounts']['shipping'] =
            $payload['amounts']['shipping'] ?? 0;
        $payload['amounts']['insurance'] =
            $payload['amounts']['insurance'] ?? 0;

        $payload['items'] = is_array(
            $payload['items'] ?? null
        ) ? $payload['items'] : [];

        foreach ($payload['items'] as $index => $item) {
            $item = is_array($item) ? $item : [];
            $item['client_item_id'] = isset(
                $item['client_item_id']
            ) ? trim((string) $item['client_item_id']) : null;
            $item['category'] = strtoupper(trim(
                (string) ($item['category'] ?? '')
            ));
            $item['product_service_code'] = trim(
                (string) ($item['product_service_code'] ?? '')
            );
            $item['unit_code'] = strtoupper(trim(
                (string) ($item['unit_code'] ?? '')
            ));
            $item['description'] = trim(
                (string) ($item['description'] ?? '')
            );
            $item['tax_object'] = trim(
                (string) ($item['tax_object'] ?? '')
            );
            $item['discount'] = $item['discount'] ?? 0;
            $item['tax_amount'] = $item['tax_amount'] ?? 0;
            $item['metadata'] = isset($item['metadata'])
                && is_array($item['metadata'])
                    ? $item['metadata']
                    : null;

            $payload['items'][$index] = $item;
        }

        return $payload;
    }

    private function normalizeValidatedAmounts(
        array $payload
    ): array {
        foreach (
            [
                'subtotal',
                'discount',
                'tax',
                'shipping',
                'insurance',
                'total',
            ] as $field
        ) {
            $payload['amounts'][$field] = round(
                (float) $payload['amounts'][$field],
                2
            );
        }

        if (isset($payload['payment']['exchange_rate'])) {
            $payload['payment']['exchange_rate'] = round(
                (float) $payload['payment']['exchange_rate'],
                6
            );
        }

        foreach ($payload['items'] as $index => $item) {
            $payload['items'][$index]['quantity'] = round(
                (float) $item['quantity'],
                4
            );
            $payload['items'][$index]['unit_price'] = round(
                (float) $item['unit_price'],
                6
            );

            foreach (
                [
                    'discount',
                    'subtotal',
                    'tax_amount',
                    'total',
                ] as $field
            ) {
                $payload['items'][$index][$field] = round(
                    (float) $item[$field],
                    2
                );
            }

            if (isset($item['tax_rate'])) {
                $payload['items'][$index]['tax_rate'] = round(
                    (float) $item['tax_rate'],
                    6
                );
            }
        }

        return $payload;
    }

    private function assertItemTotals(array $items): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            $base = round(
                $item['quantity'] * $item['unit_price'],
                2
            );
            $expectedSubtotal = round(
                $base - $item['discount'],
                2
            );

            if ($expectedSubtotal < 0) {
                $errors["items.$index.discount"][] =
                    'El descuento no puede superar el importe de la línea.';
            }

            if (
                $this->cents($item['subtotal'])
                !== $this->cents($expectedSubtotal)
            ) {
                $errors["items.$index.subtotal"][] =
                    'No coincide con cantidad × precio unitario − descuento.';
            }

            if (
                $item['tax_amount'] > 0
                && !isset($item['tax_rate'])
            ) {
                $errors["items.$index.tax_rate"][] =
                    'La tasa es obligatoria cuando existe impuesto.';
            }

            if (isset($item['tax_rate'])) {
                $expectedTax = round(
                    max($expectedSubtotal, 0)
                    * $item['tax_rate'],
                    2
                );

                if (
                    $this->cents($item['tax_amount'])
                    !== $this->cents($expectedTax)
                ) {
                    $errors["items.$index.tax_amount"][] =
                        'No coincide con el subtotal de la línea por la tasa indicada.';
                }
            }

            $expectedTotal = round(
                $item['subtotal'] + $item['tax_amount'],
                2
            );

            if (
                $this->cents($item['total'])
                !== $this->cents($expectedTotal)
            ) {
                $errors["items.$index.total"][] =
                    'No coincide con subtotal más impuesto.';
            }
        }

        if ($errors !== []) {
            throw new BillingApiException(
                'Los importes de uno o más conceptos no cuadran.',
                'ITEM_TOTAL_MISMATCH',
                422,
                $errors
            );
        }
    }

    private function assertSummaryTotals(array $payload): void
    {
        $items = $payload['items'];

        $calculated = [
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'shipping' => 0,
            'insurance' => 0,
            'total' => 0,
        ];

        foreach ($items as $item) {
            $calculated['subtotal'] += $item['subtotal'];
            $calculated['discount'] += $item['discount'];
            $calculated['tax'] += $item['tax_amount'];
            $calculated['total'] += $item['total'];

            if ($item['category'] === 'SHIPPING') {
                $calculated['shipping'] += $item['total'];
            }

            if ($item['category'] === 'INSURANCE') {
                $calculated['insurance'] += $item['total'];
            }
        }

        $errors = [];

        foreach ($calculated as $field => $value) {
            $value = round($value, 2);

            if (
                $this->cents($payload['amounts'][$field])
                !== $this->cents($value)
            ) {
                $errors["amounts.$field"][] = sprintf(
                    'El valor enviado es %.2f y el calculado desde los conceptos es %.2f.',
                    $payload['amounts'][$field],
                    $value
                );
            }
        }

        if ($errors !== []) {
            throw new BillingApiException(
                'El resumen de importes no coincide con los conceptos facturables.',
                'SUMMARY_TOTAL_MISMATCH',
                422,
                $errors
            );
        }
    }

    private function cents(float|int|string $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function sortRecursively(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                fn ($item) => is_array($item)
                    ? $this->sortRecursively($item)
                    : $item,
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
