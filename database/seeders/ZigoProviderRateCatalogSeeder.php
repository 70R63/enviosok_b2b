<?php

namespace Database\Seeders;

use App\Models\Ltd;
use App\Models\Servicio;
use App\Models\ZigoAgreementService;
use App\Models\ZigoContractRate;
use App\Models\ZigoProviderRateCard;
use App\Models\ZigoProviderRateLine;
use App\Models\ZigoProviderSource;
use App\Models\ZigoShippingAgreement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ZigoProviderRateCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $estafeta = $this->findEstafeta();

            $terrestre = $this->findService(
                preferredId: 1,
                names: [
                    'Terrestre',
                ]
            );

            $diaSiguiente = $this->findService(
                preferredId: 5,
                names: [
                    'DIA SIG.',
                    'Dia Sig',
                    'Día Sig',
                    'Dia siguiente',
                    'Día siguiente',
                ]
            );

            $xpertaSource =
                ZigoProviderSource::query()
                    ->updateOrCreate(
                        [
                            'code' => 'XPERTA',
                        ],
                        [
                            'name' => 'Xperta',
                            'source_type' =>
                                ZigoProviderSource::TYPE_INTEGRATOR,
                            'active' => true,
                            'notes' =>
                                'Integrador dinámico de paqueterías.',
                        ]
                    );

            $contractSource =
                ZigoProviderSource::query()
                    ->updateOrCreate(
                        [
                            'code' => 'CONTRATO_ZIGO',
                        ],
                        [
                            'name' =>
                                'Contrato directo ZIGO',
                            'source_type' =>
                                ZigoProviderSource::TYPE_MANUAL,
                            'active' => true,
                            'notes' =>
                                'Tarifas negociadas y administradas '
                                . 'por ZIGO.',
                        ]
                    );

            $xpertaAgreement =
                ZigoShippingAgreement::query()
                    ->updateOrCreate(
                        [
                            'provider_source_id' =>
                                $xpertaSource->id,
                            'ltd_id' =>
                                $estafeta->id,
                            'name' =>
                                'Xperta - Estafeta',
                        ],
                        [
                            'rate_mode' =>
                                ZigoShippingAgreement::MODE_DYNAMIC_API,
                            'currency' => 'MXN',
                            'priority' => 100,
                            'active' => true,
                            'external_reference' =>
                                'XPERTA_ESTAFETA',
                            'notes' =>
                                'El costo se consulta en tiempo real '
                                . 'mediante Xperta.',
                        ]
                    );

            $contractAgreement =
                ZigoShippingAgreement::query()
                    ->updateOrCreate(
                        [
                            'provider_source_id' =>
                                $contractSource->id,
                            'ltd_id' =>
                                $estafeta->id,
                            'name' =>
                                'Estafeta contractual ZIGO',
                        ],
                        [
                            'rate_mode' =>
                                ZigoShippingAgreement::MODE_MANUAL,
                            'currency' => 'MXN',
                            'priority' => 200,
                            'active' => true,
                            'external_reference' =>
                                'CONTRATO_INICIAL_ZIGO',
                            'notes' =>
                                'Convenio contractual inicial '
                                . 'administrado por ZIGO.',
                        ]
                    );

            $xpertaTerrestre =
                $this->upsertAgreementService(
                    agreement: $xpertaAgreement,
                    service: $terrestre,
                    externalCode: 'terrestre',
                    displayName: 'Terrestre',
                    priority: 100
                );

            $xpertaDiaSiguiente =
                $this->upsertAgreementService(
                    agreement: $xpertaAgreement,
                    service: $diaSiguiente,
                    externalCode: 'diasig',
                    displayName: 'Día siguiente',
                    priority: 200
                );

            $contractTerrestre =
                $this->upsertAgreementService(
                    agreement: $contractAgreement,
                    service: $terrestre,
                    externalCode: 'TERRESTRE',
                    displayName: 'Terrestre',
                    priority: 100
                );

            $contractDiaSiguiente =
                $this->upsertAgreementService(
                    agreement: $contractAgreement,
                    service: $diaSiguiente,
                    externalCode: 'DIA_SIGUIENTE',
                    displayName: 'Día siguiente',
                    priority: 200
                );

            $this->upsertDynamicRateCard(
                agreementService:
                    $xpertaTerrestre,
                name:
                    'Xperta Estafeta Terrestre',
                sourceReference:
                    'XPERTA_API_ESTAFETA_TERRESTRE'
            );

            $this->upsertDynamicRateCard(
                agreementService:
                    $xpertaDiaSiguiente,
                name:
                    'Xperta Estafeta Día siguiente',
                sourceReference:
                    'XPERTA_API_ESTAFETA_DIA_SIGUIENTE'
            );

            $this->upsertContractRateCard(
                agreementService:
                    $contractTerrestre,
                serviceCode:
                    'TERRESTRE'
            );

            $this->upsertContractRateCard(
                agreementService:
                    $contractDiaSiguiente,
                serviceCode:
                    'DIA_SIGUIENTE'
            );
        });
    }

    private function findEstafeta(): Ltd
    {
        foreach (
            [
                'ESTAFETA_MEXICANA',
                'ESTAFETA',
            ]
            as $name
        ) {
            $ltd = Ltd::query()
                ->where('nombre', $name)
                ->first();

            if ($ltd) {
                return $ltd;
            }
        }

        throw new RuntimeException(
            'No se encontró la LTD Estafeta.'
        );
    }

    private function findService(
        int $preferredId,
        array $names
    ): Servicio {
        $normalizedNames = collect($names)
            ->map(
                fn (string $name): string =>
                    $this->normalize($name)
            )
            ->all();

        $preferred = Servicio::query()
            ->find($preferredId);

        if (
            $preferred
            && in_array(
                $this->normalize(
                    (string) $preferred->nombre
                ),
                $normalizedNames,
                true
            )
        ) {
            return $preferred;
        }

        foreach ($names as $name) {
            $service = Servicio::query()
                ->where('nombre', $name)
                ->first();

            if ($service) {
                return $service;
            }
        }

        $service = Servicio::query()
            ->get()
            ->first(
                fn (Servicio $item): bool =>
                    in_array(
                        $this->normalize(
                            (string) $item->nombre
                        ),
                        $normalizedNames,
                        true
                    )
            );

        if ($service) {
            return $service;
        }

        throw new RuntimeException(
            'No se encontró el servicio: '
            . implode(' / ', $names)
        );
    }

    private function upsertAgreementService(
        ZigoShippingAgreement $agreement,
        Servicio $service,
        string $externalCode,
        string $displayName,
        int $priority
    ): ZigoAgreementService {
        return ZigoAgreementService::query()
            ->updateOrCreate(
                [
                    'shipping_agreement_id' =>
                        $agreement->id,
                    'servicio_id' =>
                        $service->id,
                ],
                [
                    'external_service_code' =>
                        $externalCode,
                    'display_name' =>
                        $displayName,
                    'priority' =>
                        $priority,
                    'active' => true,
                ]
            );
    }

    private function upsertDynamicRateCard(
        ZigoAgreementService $agreementService,
        string $name,
        string $sourceReference
    ): void {
        $rateCard =
            ZigoProviderRateCard::query()
                ->updateOrCreate(
                    [
                        'agreement_service_id' =>
                            $agreementService->id,
                        'version' => 1,
                    ],
                    [
                        'name' => $name,
                        'pricing_scheme' =>
                            ZigoProviderRateCard::SCHEME_DYNAMIC_API,
                        'status' =>
                            ZigoProviderRateCard::STATUS_ACTIVE,
                        'currency' => 'MXN',
                        /*
                         * La respuesta Xperta se utiliza como costo
                         * total normalizado. No se agrega IVA otra vez.
                         */
                        'tax_percentage' => 0,
                        'priority' => 100,
                        'source_reference' =>
                            $sourceReference,
                        'notes' =>
                            'Costo dinámico consultado desde Xperta.',
                    ]
                );

        ZigoProviderRateLine::query()
            ->where(
                'rate_card_id',
                $rateCard->id
            )
            ->delete();
    }

    private function upsertContractRateCard(
        ZigoAgreementService $agreementService,
        string $serviceCode
    ): void {
        $contract =
            ZigoContractRate::query()
                ->where(
                    'service_code',
                    $serviceCode
                )
                ->where('active', true)
                ->latest('id')
                ->first();

        if (!$contract) {
            throw new RuntimeException(
                "No existe tarifa contractual activa "
                . "para {$serviceCode}."
            );
        }

        $rateCard =
            ZigoProviderRateCard::query()
                ->updateOrCreate(
                    [
                        'agreement_service_id' =>
                            $agreementService->id,
                        'version' => 1,
                    ],
                    [
                        'name' =>
                            $contract->name,
                        'pricing_scheme' =>
                            ZigoProviderRateCard::SCHEME_BASE_PLUS_EXTRA,
                        'status' =>
                            ZigoProviderRateCard::STATUS_ACTIVE,
                        'currency' =>
                            $contract->currency,
                        'tax_percentage' =>
                            $contract->tax_percentage,
                        'priority' => 100,
                        'valid_from' =>
                            $contract->valid_from,
                        'valid_to' =>
                            $contract->valid_to,
                        'source_reference' =>
                            $contract->source,
                        'notes' =>
                            $contract->notes,
                    ]
                );

        ZigoProviderRateLine::query()
            ->updateOrCreate(
                [
                    'rate_card_id' =>
                        $rateCard->id,
                    'sort_order' => 100,
                ],
                [
                    'zone_code' => null,
                    'origin_zone' => null,
                    'destination_zone' => null,
                    'min_weight_kg' => null,
                    'max_weight_kg' => null,
                    'included_weight_kg' =>
                        $contract->included_weight_kg,
                    'base_price' =>
                        $contract->base_price,
                    'additional_weight_unit_kg' =>
                        $contract
                            ->additional_weight_unit_kg,
                    'additional_weight_price' =>
                        $contract
                            ->additional_weight_price,
                    'extended_area_price' => 0,
                    'oversize_price' => 0,
                    'insurance_percentage' => 0,
                    'fuel_surcharge_percentage' => 0,
                    'multipiece_price' => 0,
                    'active' => true,
                ]
            );
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' '
            )
            ->trim()
            ->toString();
    }
}
