<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Models\ZigoAgreementService;
use App\Models\ZigoProviderRateCard;
use App\Models\ZigoProviderRateLine;
use App\Models\ZigoShippingAgreement;
use App\Services\Shipping\Xperta\XpertaQuoteService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZigoProviderRateEngineService
{
    public function __construct(
        private XpertaQuoteService $xpertaQuoteService
    ) {
    }

    public function getOptionsForCotizacion(
        B2cCotizacion $cotizacion
    ): array {
        $agreements = ZigoShippingAgreement::query()
            ->with([
                'source',
                'ltd',
            ])
            ->where('active', true)
            ->whereHas(
                'source',
                fn ($query) =>
                    $query->where('active', true)
            )
            ->where(
                fn ($query) =>
                    $query
                        ->whereNull('valid_from')
                        ->orWhereDate(
                            'valid_from',
                            '<=',
                            today()
                        )
            )
            ->where(
                fn ($query) =>
                    $query
                        ->whereNull('valid_to')
                        ->orWhereDate(
                            'valid_to',
                            '>=',
                            today()
                        )
            )
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $resolvedOptions = [];

        foreach ($agreements as $agreement) {
            $agreementServices =
                ZigoAgreementService::query()
                    ->with('service')
                    ->where(
                        'shipping_agreement_id',
                        $agreement->id
                    )
                    ->where('active', true)
                    ->orderBy('priority')
                    ->orderBy('id')
                    ->get();

            foreach (
                $agreementServices
                as $agreementService
            ) {
                $serviceName = trim(
                    (string) (
                        $agreementService->display_name
                        ?: optional(
                            $agreementService->service
                        )->nombre
                        ?: $agreementService
                            ->external_service_code
                    )
                );

                if ($serviceName === '') {
                    continue;
                }

                $carrierName =
                    $this->carrierLabel(
                        (string) optional(
                            $agreement->ltd
                        )->nombre
                    );

                $optionKey =
                    $this->optionKey(
                        $carrierName,
                        $serviceName
                    );

                if (
                    isset(
                        $resolvedOptions[$optionKey]
                    )
                ) {
                    continue;
                }

                $rateCard =
                    $this->resolveRateCard(
                        $agreementService
                    );

                if (!$rateCard) {
                    continue;
                }

                try {
                    $option =
                        $this->resolveOption(
                            $cotizacion,
                            $agreement,
                            $agreementService,
                            $rateCard,
                            $carrierName,
                            $serviceName
                        );

                    if ($option === null) {
                        continue;
                    }

                    $resolvedOptions[$optionKey] =
                        array_merge(
                            $option,
                            [
                                'rate_engine' =>
                                    'zigo_v2',

                                'provider_source_id' =>
                                    $agreement
                                        ->provider_source_id,

                                'provider_source_code' =>
                                    optional(
                                        $agreement->source
                                    )->code,

                                'shipping_agreement_id' =>
                                    $agreement->id,

                                'agreement_service_id' =>
                                    $agreementService->id,

                                'rate_card_id' =>
                                    $rateCard->id,

                                'rate_card_version' =>
                                    $rateCard->version,

                                'rate_mode' =>
                                    $agreement->rate_mode,

                                'pricing_scheme' =>
                                    $rateCard
                                        ->pricing_scheme,

                                'currency' =>
                                    $rateCard->currency,
                            ]
                        );
                } catch (Throwable $exception) {
                    Log::warning(
                        'ZIGO Rate Engine V2 - '
                        . 'No se pudo resolver convenio',
                        [
                            'cotizacion_id' =>
                                $cotizacion->id,

                            'agreement_id' =>
                                $agreement->id,

                            'agreement_service_id' =>
                                $agreementService->id,

                            'rate_card_id' =>
                                $rateCard->id,

                            'message' =>
                                $exception->getMessage(),
                        ]
                    );
                }
            }
        }

        if ($resolvedOptions === []) {
            throw new RuntimeException(
                'El motor tarifario V2 no encontró '
                . 'opciones aplicables.'
            );
        }

        return array_values(
            $resolvedOptions
        );
    }

    private function resolveOption(
        B2cCotizacion $cotizacion,
        ZigoShippingAgreement $agreement,
        ZigoAgreementService $agreementService,
        ZigoProviderRateCard $rateCard,
        string $carrierName,
        string $serviceName
    ): ?array {
        return match (
            strtoupper(
                trim(
                    (string) $agreement->rate_mode
                )
            )
        ) {
            ZigoShippingAgreement::MODE_MANUAL =>
                $this->resolveManualOption(
                    $cotizacion,
                    $agreement,
                    $agreementService,
                    $rateCard,
                    $carrierName,
                    $serviceName
                ),

            ZigoShippingAgreement::MODE_DYNAMIC_API =>
                $this->resolveDynamicOption(
                    $cotizacion,
                    $agreement,
                    $agreementService
                ),

            ZigoShippingAgreement::MODE_HYBRID =>
                $this->resolveHybridOption(
                    $cotizacion,
                    $agreement,
                    $agreementService,
                    $rateCard,
                    $carrierName,
                    $serviceName
                ),

            default =>
                throw new RuntimeException(
                    'Modo tarifario no reconocido: '
                    . $agreement->rate_mode
                ),
        };
    }

    private function resolveDynamicOption(
        B2cCotizacion $cotizacion,
        ZigoShippingAgreement $agreement,
        ZigoAgreementService $agreementService
    ): ?array {
        $sourceCode = strtoupper(
            trim(
                (string) optional(
                    $agreement->source
                )->code
            )
        );

        if ($sourceCode !== 'XPERTA') {
            throw new RuntimeException(
                'No existe adaptador dinámico '
                . "para la fuente {$sourceCode}."
            );
        }

        if (
            !config(
                'zigo_provider_rates'
                . '.allow_xperta_b2c',
                false
            )
        ) {
            return null;
        }

        if (
            !config(
                'services.xperta.enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'Xperta está desactivado.'
            );
        }

        $serviceCode = trim(
            (string) (
                $agreementService
                    ->external_service_code
                ?: optional(
                    $agreementService->service
                )->nombre
            )
        );

        if ($serviceCode === '') {
            throw new RuntimeException(
                'El servicio dinámico no tiene '
                . 'código externo.'
            );
        }

        return $this->xpertaQuoteService->quote(
            $cotizacion,
            $this->normalizeExternalService(
                $serviceCode
            )
        );
    }

    private function resolveHybridOption(
        B2cCotizacion $cotizacion,
        ZigoShippingAgreement $agreement,
        ZigoAgreementService $agreementService,
        ZigoProviderRateCard $rateCard,
        string $carrierName,
        string $serviceName
    ): array {
        try {
            $dynamic =
                $this->resolveDynamicOption(
                    $cotizacion,
                    $agreement,
                    $agreementService
                );

            if ($dynamic !== null) {
                return $dynamic;
            }
        } catch (Throwable $exception) {
            Log::warning(
                'ZIGO Rate Engine V2 - '
                . 'Fallback HYBRID a tarifa manual',
                [
                    'cotizacion_id' =>
                        $cotizacion->id,

                    'agreement_id' =>
                        $agreement->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }

        return $this->resolveManualOption(
            $cotizacion,
            $agreement,
            $agreementService,
            $rateCard,
            $carrierName,
            $serviceName
        );
    }

    private function resolveManualOption(
        B2cCotizacion $cotizacion,
        ZigoShippingAgreement $agreement,
        ZigoAgreementService $agreementService,
        ZigoProviderRateCard $rateCard,
        string $carrierName,
        string $serviceName
    ): array {
        $weight = $this->billableWeight(
            $cotizacion
        );

        $originZone = substr(
            preg_replace(
                '/\D/',
                '',
                (string) $cotizacion->cp_origen
            ),
            0,
            5
        );

        $destinationZone = substr(
            preg_replace(
                '/\D/',
                '',
                (string) $cotizacion->cp_destino
            ),
            0,
            5
        );

        $rateLine =
            ZigoProviderRateLine::query()
                ->where(
                    'rate_card_id',
                    $rateCard->id
                )
                ->where('active', true)
                ->where(
                    fn ($query) =>
                        $query
                            ->whereNull(
                                'min_weight_kg'
                            )
                            ->orWhere(
                                'min_weight_kg',
                                '<=',
                                $weight
                            )
                )
                ->where(
                    fn ($query) =>
                        $query
                            ->whereNull(
                                'max_weight_kg'
                            )
                            ->orWhere(
                                'max_weight_kg',
                                '>=',
                                $weight
                            )
                )
                ->where(
                    fn ($query) =>
                        $query
                            ->whereNull(
                                'origin_zone'
                            )
                            ->orWhere(
                                'origin_zone',
                                $originZone
                            )
                )
                ->where(
                    fn ($query) =>
                        $query
                            ->whereNull(
                                'destination_zone'
                            )
                            ->orWhere(
                                'destination_zone',
                                $destinationZone
                            )
                )
                ->orderByRaw(
                    'CASE WHEN origin_zone IS NULL '
                    . 'THEN 1 ELSE 0 END'
                )
                ->orderByRaw(
                    'CASE WHEN destination_zone IS NULL '
                    . 'THEN 1 ELSE 0 END'
                )
                ->orderByRaw(
                    'CASE WHEN min_weight_kg IS NULL '
                    . 'THEN 1 ELSE 0 END'
                )
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

        if (!$rateLine) {
            throw new RuntimeException(
                'No existe un renglón tarifario '
                . 'aplicable para '
                . number_format(
                    $weight,
                    3
                )
                . ' kg.'
            );
        }

        $includedWeight = max(
            (float) (
                $rateLine
                    ->included_weight_kg
                ?? 0
            ),
            0
        );

        $additionalWeight = max(
            0,
            round(
                $weight - $includedWeight,
                3
            )
        );

        $additionalUnit = (float) (
            $rateLine
                ->additional_weight_unit_kg
            ?: 1
        );

        if (
            $additionalWeight > 0
            && $additionalUnit <= 0
        ) {
            throw new RuntimeException(
                'La unidad de peso adicional '
                . 'no es válida.'
            );
        }

        $additionalUnits =
            $additionalWeight > 0
                ? (int) ceil(
                    (
                        $additionalWeight
                        / $additionalUnit
                    )
                    - 0.0000001
                )
                : 0;

        $baseAmount = round(
            (float) $rateLine->base_price,
            2
        );

        $additionalAmount = round(
            $additionalUnits
            * (float) $rateLine
                ->additional_weight_price,
            2
        );

        $extendedAreaAmount = 0.00;
        $oversizeAmount = 0.00;
        $multipieceAmount = 0.00;

        $subtotalBeforeFuel = round(
            $baseAmount
            + $additionalAmount
            + $extendedAreaAmount
            + $oversizeAmount
            + $multipieceAmount,
            2
        );

        $fuelPercentage = round(
            (float) $rateLine
                ->fuel_surcharge_percentage,
            4
        );

        $fuelAmount = round(
            $subtotalBeforeFuel
            * ($fuelPercentage / 100),
            2
        );

        $netAmount = round(
            $subtotalBeforeFuel
            + $fuelAmount,
            2
        );

        $taxPercentage = round(
            (float) $rateCard
                ->tax_percentage,
            2
        );

        $taxAmount = round(
            $netAmount
            * ($taxPercentage / 100),
            2
        );

        $total = round(
            $netAmount + $taxAmount,
            2
        );

        if ($total <= 0) {
            throw new RuntimeException(
                'El tarifario manual produjo '
                . 'un total inválido.'
            );
        }

        return [
            'logistico' => $carrierName,
            'logo' =>
                $this->carrierLogo(
                    $carrierName
                ),
            'servicio' => $serviceName,
            'service_code' =>
                $agreementService
                    ->external_service_code,
            'entrega' =>
                $this->deliveryLabel(
                    $serviceName
                ),
            'base_price' => $total,
            'provider_source' =>
                strtolower(
                    (string) optional(
                        $agreement->source
                    )->code
                ),
            'extended_area' => false,
            'extended_area_amount' =>
                $extendedAreaAmount,

            'provider_breakdown' => [
                'weight_kg' => $weight,
                'included_weight_kg' =>
                    $includedWeight,
                'additional_weight_kg' =>
                    $additionalWeight,
                'additional_units' =>
                    $additionalUnits,
                'base_amount' =>
                    $baseAmount,
                'additional_amount' =>
                    $additionalAmount,
                'fuel_percentage' =>
                    $fuelPercentage,
                'fuel_amount' =>
                    $fuelAmount,
                'net_amount' =>
                    $netAmount,
                'tax_percentage' =>
                    $taxPercentage,
                'tax_amount' =>
                    $taxAmount,
                'total' => $total,

                'configured_extended_area_price' =>
                    round(
                        (float) $rateLine
                            ->extended_area_price,
                        2
                    ),

                'configured_oversize_price' =>
                    round(
                        (float) $rateLine
                            ->oversize_price,
                        2
                    ),

                'configured_multipiece_price' =>
                    round(
                        (float) $rateLine
                            ->multipiece_price,
                        2
                    ),
            ],
        ];
    }

    private function resolveRateCard(
        ZigoAgreementService $agreementService
    ): ?ZigoProviderRateCard {
        return ZigoProviderRateCard::query()
            ->where(
                'agreement_service_id',
                $agreementService->id
            )
            ->where(
                'status',
                ZigoProviderRateCard::STATUS_ACTIVE
            )
            ->where(
                fn ($query) =>
                    $query
                        ->whereNull('valid_from')
                        ->orWhereDate(
                            'valid_from',
                            '<=',
                            today()
                        )
            )
            ->where(
                fn ($query) =>
                    $query
                        ->whereNull('valid_to')
                        ->orWhereDate(
                            'valid_to',
                            '>=',
                            today()
                        )
            )
            ->orderBy('priority')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    private function billableWeight(
        B2cCotizacion $cotizacion
    ): float {
        $weight = (float) (
            $cotizacion->peso_facturable
            ?: $cotizacion->peso
        );

        if ($weight <= 0) {
            throw new RuntimeException(
                'La cotización no tiene '
                . 'peso facturable válido.'
            );
        }

        return round(
            $weight,
            3
        );
    }

    private function optionKey(
        string $carrier,
        string $service
    ): string {
        return strtoupper(
            trim(
                Str::ascii(
                    $carrier
                    . '|'
                    . $service
                )
            )
        );
    }

    private function carrierLabel(
        string $carrier
    ): string {
        $normalized = strtolower(
            Str::ascii(
                trim($carrier)
            )
        );

        if (
            str_contains(
                $normalized,
                'estafeta'
            )
        ) {
            return 'Estafeta';
        }

        return Str::title(
            str_replace(
                '_',
                ' ',
                trim($carrier)
            )
        );
    }

    private function carrierLogo(
        string $carrier
    ): ?string {
        return strcasecmp(
            $carrier,
            'Estafeta'
        ) === 0
            ? 'img/estafeta.png'
            : null;
    }

    private function deliveryLabel(
        string $service
    ): string {
        $normalized = strtolower(
            Str::ascii($service)
        );

        if (
            str_contains(
                $normalized,
                'dia siguiente'
            )
            || str_contains(
                $normalized,
                'dia sig'
            )
        ) {
            return 'Día hábil siguiente';
        }

        if (
            str_contains(
                $normalized,
                'terrestre'
            )
        ) {
            return '2 a 5 días hábiles';
        }

        return 'Entrega según cobertura';
    }

    private function normalizeExternalService(
        string $service
    ): string {
        $normalized = strtolower(
            trim(
                Str::ascii($service)
            )
        );

        if (
            str_contains(
                $normalized,
                'terrestre'
            )
        ) {
            return 'terrestre';
        }

        if (
            str_contains(
                $normalized,
                'dia siguiente'
            )
            || str_contains(
                $normalized,
                'dia sig'
            )
            || $normalized === 'diasig'
        ) {
            return 'diasig';
        }

        return trim(
            preg_replace(
                '/[^a-z0-9]+/',
                '_',
                $normalized
            ),
            '_'
        );
    }
}