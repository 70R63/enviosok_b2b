<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Ltd;
use App\Models\Servicio;
use App\Models\ZigoAgreementService;
use App\Models\ZigoClientPricingRule;
use App\Models\ZigoPricingAdjustment;
use App\Models\ZigoPricingRule;
use App\Models\ZigoProviderRateCard;
use App\Models\ZigoProviderRateLine;
use App\Models\ZigoProviderRateReference;
use App\Models\ZigoProviderSource;
use App\Models\ZigoShippingAgreement;
use App\Services\ZigoPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmPricingController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = (string) $request->query(
            'tab',
            'provider-rates'
        );

        $allowedTabs = [
            'provider-rates',
            'sources',
            'profits',
            'simulator',
        ];

        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'provider-rates';
        }

        $providerSources = ZigoProviderSource::query()
            ->withCount('agreements')
            ->orderBy('name')
            ->get();

        $agreements = ZigoShippingAgreement::query()
            ->with([
                'source:id,code,name',
                'ltd:id,nombre',
            ])
            ->withCount('services')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $agreementServices = ZigoAgreementService::query()
            ->with([
                'agreement.source:id,code,name',
                'agreement.ltd:id,nombre',
                'service:id,nombre',
            ])
            ->withCount('rateCards')
            ->orderBy('shipping_agreement_id')
            ->orderBy('priority')
            ->get();

        $rateCards = ZigoProviderRateCard::query()
            ->with([
                'agreementService.agreement.source:id,code,name',
                'agreementService.agreement.ltd:id,nombre',
                'agreementService.service:id,nombre',
                'agreementService.rateReferences' =>
                    fn ($query) =>
                        $query->orderByDesc('version'),
                'agreementService.latestQuoteObservation',
                'lines',
            ])
            ->orderByDesc('id')
            ->get();

        $ltds = Ltd::query()
            ->where('estatus', 1)
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
            ]);

        $services = Servicio::query()
            ->where('estatus', 1)
            ->orderBy('prioridad')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
            ]);

        $pricingRules = ZigoPricingRule::query()
            ->orderBy('customer_segment')
            ->orderBy('package_type')
            ->get();

        $adjustments = ZigoPricingAdjustment::query()
            ->latest()
            ->get();

        $clientRules = ZigoClientPricingRule::query()
            ->latest()
            ->get();

        $pricingCarriers = ZigoPricingRule::query()
            ->select('carrier')
            ->distinct()
            ->orderBy('carrier')
            ->pluck('carrier');

        if ($pricingCarriers->isEmpty()) {
            $pricingCarriers = collect([
                'ESTAFETA',
            ]);
        }

        return view(
            'crm.pricing.index',
            compact(
                'activeTab',
                'providerSources',
                'agreements',
                'agreementServices',
                'rateCards',
                'ltds',
                'services',
                'pricingRules',
                'adjustments',
                'clientRules',
                'pricingCarriers'
            )
        );
    }

    public function storeSource(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique(
                    'zigo_provider_sources',
                    'code'
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'source_type' => [
                'required',
                Rule::in([
                    ZigoProviderSource::TYPE_INTEGRATOR,
                    ZigoProviderSource::TYPE_DIRECT,
                    ZigoProviderSource::TYPE_MANUAL,
                ]),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        ZigoProviderSource::query()->create([
            'code' => Str::upper($data['code']),
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->redirectToTab(
            'sources',
            'Fuente tarifaria creada correctamente.'
        );
    }

    public function toggleSource(
        ZigoProviderSource $source
    ): RedirectResponse {
        $source->update([
            'active' => !$source->active,
        ]);

        return $this->redirectToTab(
            'sources',
            'Fuente tarifaria actualizada.'
        );
    }

    public function storeAgreement(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'provider_source_id' => [
                'required',
                'integer',
                Rule::exists(
                    'zigo_provider_sources',
                    'id'
                ),
            ],
            'ltd_id' => [
                'required',
                'integer',
                Rule::exists('ltds', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'rate_mode' => [
                'required',
                Rule::in([
                    ZigoShippingAgreement::MODE_DYNAMIC_API,
                    ZigoShippingAgreement::MODE_MANUAL,
                    ZigoShippingAgreement::MODE_HYBRID,
                ]),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'priority' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
            ],
            'valid_from' => [
                'nullable',
                'date',
            ],
            'valid_to' => [
                'nullable',
                'date',
                'after_or_equal:valid_from',
            ],
            'external_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        ZigoShippingAgreement::query()->create([
            'provider_source_id' =>
                $data['provider_source_id'],
            'ltd_id' => $data['ltd_id'],
            'name' => $data['name'],
            'rate_mode' => $data['rate_mode'],
            'currency' => Str::upper(
                $data['currency']
            ),
            'priority' => $data['priority'],
            'active' => true,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'external_reference' =>
                $data['external_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $this->redirectToTab(
            'sources',
            'Convenio logístico creado correctamente.'
        );
    }

    public function toggleAgreement(
        ZigoShippingAgreement $agreement
    ): RedirectResponse {
        $agreement->update([
            'active' => !$agreement->active,
            'updated_by' => auth()->id(),
        ]);

        return $this->redirectToTab(
            'sources',
            'Convenio logístico actualizado.'
        );
    }

    public function storeAgreementService(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'shipping_agreement_id' => [
                'required',
                'integer',
                Rule::exists(
                    'zigo_shipping_agreements',
                    'id'
                ),
            ],
            'servicio_id' => [
                'required',
                'integer',
                Rule::exists(
                    'servicios',
                    'id'
                ),
                Rule::unique(
                    'zigo_agreement_services',
                    'servicio_id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'shipping_agreement_id',
                            $request->input(
                                'shipping_agreement_id'
                            )
                        )
                ),
            ],
            'external_service_code' => [
                'nullable',
                'string',
                'max:80',
            ],
            'display_name' => [
                'nullable',
                'string',
                'max:120',
            ],
            'priority' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
            ],
        ]);

        ZigoAgreementService::query()->create([
            'shipping_agreement_id' =>
                $data['shipping_agreement_id'],
            'servicio_id' => $data['servicio_id'],
            'external_service_code' =>
                $data['external_service_code'] ?? null,
            'display_name' =>
                $data['display_name'] ?? null,
            'priority' => $data['priority'],
            'active' => true,
        ]);

        return $this->redirectToTab(
            'sources',
            'Servicio asociado al convenio.'
        );
    }

    public function toggleAgreementService(
        ZigoAgreementService $agreementService
    ): RedirectResponse {
        $agreementService->update([
            'active' => !$agreementService->active,
        ]);

        return $this->redirectToTab(
            'sources',
            'Servicio del convenio actualizado.'
        );
    }

    public function storeRateCard(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'agreement_service_id' => [
                'required',
                'integer',
                Rule::exists(
                    'zigo_agreement_services',
                    'id'
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'pricing_scheme' => [
                'required',
                Rule::in([
                    ZigoProviderRateCard::SCHEME_DYNAMIC_API,
                    ZigoProviderRateCard::SCHEME_FLAT,
                    ZigoProviderRateCard::SCHEME_BASE_PLUS_EXTRA,
                    ZigoProviderRateCard::SCHEME_WEIGHT_RANGE,
                    ZigoProviderRateCard::SCHEME_ZONE_RANGE,
                ]),
            ],
            'status' => [
                'required',
                Rule::in([
                    ZigoProviderRateCard::STATUS_DRAFT,
                    ZigoProviderRateCard::STATUS_ACTIVE,
                ]),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'tax_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'priority' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
            ],
            'valid_from' => [
                'nullable',
                'date',
            ],
            'valid_to' => [
                'nullable',
                'date',
                'after_or_equal:valid_from',
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'zone_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'origin_zone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'destination_zone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'min_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_weight_kg' => [
                'nullable',
                'numeric',
                'gte:min_weight_kg',
            ],
            'included_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'base_price' => [
                Rule::requiredIf(
                    $request->input('pricing_scheme')
                    !== ZigoProviderRateCard::SCHEME_DYNAMIC_API
                ),
                'nullable',
                'numeric',
                'min:0',
            ],
            'additional_weight_unit_kg' => [
                'nullable',
                'numeric',
                'min:0.001',
            ],
            'additional_weight_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'extended_area_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'oversize_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'insurance_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'fuel_surcharge_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'multipiece_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        DB::transaction(function () use ($data): void {
            $version = (
                (int) ZigoProviderRateCard::query()
                    ->where(
                        'agreement_service_id',
                        $data['agreement_service_id']
                    )
                    ->max('version')
            ) + 1;

            if (
                $data['status']
                === ZigoProviderRateCard::STATUS_ACTIVE
            ) {
                ZigoProviderRateCard::query()
                    ->where(
                        'agreement_service_id',
                        $data['agreement_service_id']
                    )
                    ->where(
                        'status',
                        ZigoProviderRateCard::STATUS_ACTIVE
                    )
                    ->update([
                        'status' =>
                            ZigoProviderRateCard::STATUS_INACTIVE,
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
            }

            $rateCard = ZigoProviderRateCard::query()->create([
                'agreement_service_id' =>
                    $data['agreement_service_id'],
                'name' => $data['name'],
                'pricing_scheme' =>
                    $data['pricing_scheme'],
                'version' => $version,
                'status' => $data['status'],
                'currency' => Str::upper(
                    $data['currency']
                ),
                'tax_percentage' =>
                    $data['tax_percentage'],
                'priority' => $data['priority'],
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'source_reference' =>
                    $data['source_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if (
                $data['pricing_scheme']
                !== ZigoProviderRateCard::SCHEME_DYNAMIC_API
            ) {
                $this->createRateLine(
                    $rateCard,
                    $data,
                    100
                );
            }
        });

        return $this->redirectToTab(
            'provider-rates',
            'Nueva versión tarifaria creada.'
        );
    }

    public function toggleRateCard(
        ZigoProviderRateCard $rateCard
    ): RedirectResponse {
        DB::transaction(function () use ($rateCard): void {
            if (
                $rateCard->status
                === ZigoProviderRateCard::STATUS_ACTIVE
            ) {
                $rateCard->update([
                    'status' =>
                        ZigoProviderRateCard::STATUS_INACTIVE,
                    'updated_by' => auth()->id(),
                ]);

                return;
            }

            ZigoProviderRateCard::query()
                ->where(
                    'agreement_service_id',
                    $rateCard->agreement_service_id
                )
                ->where(
                    'status',
                    ZigoProviderRateCard::STATUS_ACTIVE
                )
                ->where('id', '<>', $rateCard->id)
                ->update([
                    'status' =>
                        ZigoProviderRateCard::STATUS_INACTIVE,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $rateCard->update([
                'status' =>
                    ZigoProviderRateCard::STATUS_ACTIVE,
                'updated_by' => auth()->id(),
            ]);
        });

        return $this->redirectToTab(
            'provider-rates',
            'Estatus del tarifario actualizado.'
        );
    }

    public function storeRateLine(
        Request $request,
        ZigoProviderRateCard $rateCard
    ): RedirectResponse {
        if (
            $rateCard->pricing_scheme
            === ZigoProviderRateCard::SCHEME_DYNAMIC_API
        ) {
            return $this->redirectToTab(
                'provider-rates',
                'Los tarifarios dinámicos no utilizan '
                . 'renglones manuales.',
                'error'
            );
        }

        $data = $request->validate([
            'zone_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'origin_zone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'destination_zone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'min_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_weight_kg' => [
                'nullable',
                'numeric',
                'gte:min_weight_kg',
            ],
            'included_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'base_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'additional_weight_unit_kg' => [
                'nullable',
                'numeric',
                'min:0.001',
            ],
            'additional_weight_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'extended_area_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'oversize_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'insurance_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'fuel_surcharge_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'multipiece_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $nextSortOrder = (
            (int) $rateCard->lines()->max(
                'sort_order'
            )
        ) + 100;

        $this->createRateLine(
            $rateCard,
            $data,
            $nextSortOrder
        );

        return $this->redirectToTab(
            'provider-rates',
            'Renglón tarifario agregado.'
        );
    }

    public function toggleRateLine(
        ZigoProviderRateLine $rateLine
    ): RedirectResponse {
        $rateLine->update([
            'active' => !$rateLine->active,
        ]);

        return $this->redirectToTab(
            'provider-rates',
            'Renglón tarifario actualizado.'
        );
    }

    public function storeRateReference(
        Request $request,
        ZigoAgreementService $agreementService
    ): RedirectResponse {
        $agreementService->load('agreement');

        if (
            !in_array(
                $agreementService->agreement?->rate_mode,
                [
                    ZigoShippingAgreement::MODE_DYNAMIC_API,
                    ZigoShippingAgreement::MODE_HYBRID,
                ],
                true
            )
        ) {
            return $this->redirectToTab(
                'provider-rates',
                'Las tarifas comerciales de referencia '
                . 'solo aplican a convenios dinámicos '
                . 'o híbridos.',
                'error'
            );
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'status' => [
                'required',
                Rule::in([
                    ZigoProviderRateReference::STATUS_DRAFT,
                    ZigoProviderRateReference::STATUS_ACTIVE,
                ]),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'tax_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'included_weight_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'base_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'additional_weight_unit_kg' => [
                'nullable',
                'numeric',
                'min:0.001',
            ],
            'additional_weight_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'valid_from' => [
                'nullable',
                'date',
            ],
            'valid_to' => [
                'nullable',
                'date',
                'after_or_equal:valid_from',
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:160',
            ],
            'source_document' => [
                'nullable',
                'file',
                'mimes:pdf,xls,xlsx,csv,doc,docx',
                'max:10240',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $version = (
            (int) ZigoProviderRateReference::query()
                ->where(
                    'agreement_service_id',
                    $agreementService->id
                )
                ->max('version')
        ) + 1;

        $documentPath = null;
        $documentOriginalName = null;
        $documentMimeType = null;

        if ($request->hasFile('source_document')) {
            $document = $request->file('source_document');
            $extension = strtolower(
                (string) $document->getClientOriginalExtension()
            );

            $storedName = Str::uuid()->toString()
                . ($extension !== '' ? ".{$extension}" : '');

            $documentPath = $document->storeAs(
                "pricing/provider-rate-references/"
                . $agreementService->id
                . "/v{$version}",
                $storedName,
                'local'
            );

            $documentOriginalName =
                $document->getClientOriginalName();

            $documentMimeType =
                $document->getMimeType();
        }

        DB::transaction(
            function () use (
                $agreementService,
                $data,
                $version,
                $documentPath,
                $documentOriginalName,
                $documentMimeType
            ): void {
                if (
                    $data['status']
                    === ZigoProviderRateReference::STATUS_ACTIVE
                ) {
                    ZigoProviderRateReference::query()
                        ->where(
                            'agreement_service_id',
                            $agreementService->id
                        )
                        ->where(
                            'status',
                            ZigoProviderRateReference::STATUS_ACTIVE
                        )
                        ->update([
                            'status' =>
                                ZigoProviderRateReference::STATUS_INACTIVE,
                            'updated_by' => auth()->id(),
                            'updated_at' => now(),
                        ]);
                }

                ZigoProviderRateReference::query()->create([
                    'agreement_service_id' =>
                        $agreementService->id,
                    'name' => $data['name'],
                    'version' => $version,
                    'status' => $data['status'],
                    'currency' => Str::upper(
                        $data['currency']
                    ),
                    'tax_percentage' =>
                        $data['tax_percentage'],
                    'included_weight_kg' =>
                        $data['included_weight_kg'] ?? null,
                    'base_price' =>
                        $data['base_price'],
                    'additional_weight_unit_kg' =>
                        $data['additional_weight_unit_kg']
                        ?? null,
                    'additional_weight_price' =>
                        $data['additional_weight_price'] ?? 0,
                    'valid_from' =>
                        $data['valid_from'] ?? null,
                    'valid_to' =>
                        $data['valid_to'] ?? null,
                    'source_reference' =>
                        $data['source_reference'] ?? null,
                    'document_path' => $documentPath,
                    'document_original_name' =>
                        $documentOriginalName,
                    'document_mime_type' =>
                        $documentMimeType,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        );

        return $this->redirectToTab(
            'provider-rates',
            'Tarifa comercial de referencia creada.'
        );
    }

    public function toggleRateReference(
        ZigoProviderRateReference $rateReference
    ): RedirectResponse {
        DB::transaction(
            function () use ($rateReference): void {
                if (
                    $rateReference->status
                    === ZigoProviderRateReference::STATUS_ACTIVE
                ) {
                    $rateReference->update([
                        'status' =>
                            ZigoProviderRateReference::STATUS_INACTIVE,
                        'updated_by' => auth()->id(),
                    ]);

                    return;
                }

                ZigoProviderRateReference::query()
                    ->where(
                        'agreement_service_id',
                        $rateReference->agreement_service_id
                    )
                    ->where(
                        'status',
                        ZigoProviderRateReference::STATUS_ACTIVE
                    )
                    ->where('id', '<>', $rateReference->id)
                    ->update([
                        'status' =>
                            ZigoProviderRateReference::STATUS_INACTIVE,
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);

                $rateReference->update([
                    'status' =>
                        ZigoProviderRateReference::STATUS_ACTIVE,
                    'updated_by' => auth()->id(),
                ]);
            }
        );

        return $this->redirectToTab(
            'provider-rates',
            'Tarifa comercial de referencia actualizada.'
        );
    }

    public function downloadRateReferenceDocument(
        ZigoProviderRateReference $rateReference
    ) {
        if (
            !$rateReference->document_path
            || !Storage::disk('local')->exists(
                $rateReference->document_path
            )
        ) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $rateReference->document_path,
            $rateReference->document_original_name
                ?: basename($rateReference->document_path),
            [
                'Content-Type' =>
                    $rateReference->document_mime_type
                    ?: 'application/octet-stream',
            ]
        );
    }

    public function simulate(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'carrier' => [
                'required',
                'string',
            ],
            'customer_segment' => [
                'required',
                'string',
            ],
            'plan' => [
                'nullable',
                'string',
            ],
            'package_type' => [
                'required',
                'string',
            ],
            'base_price' => [
                'required',
                'numeric',
                'min:1',
            ],
            'crm_client_id' => [
                'nullable',
                'integer',
            ],
            'api_client_id' => [
                'nullable',
                'integer',
            ],
            'user_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $result = app(
            ZigoPricingService::class
        )->calculate($data);

        return redirect()
            ->route(
                'crm.pricing.index',
                [
                    'tab' => 'simulator',
                ]
            )
            ->with('simulation', $result)
            ->withInput();
    }

    public function toggleRule(
        ZigoPricingRule $rule
    ): RedirectResponse {
        $rule->update([
            'active' => !$rule->active,
        ]);

        return $this->redirectToTab(
            'profits',
            'Regla base actualizada.'
        );
    }

    public function toggleAdjustment(
        ZigoPricingAdjustment $adjustment
    ): RedirectResponse {
        $adjustment->update([
            'active' => !$adjustment->active,
        ]);

        return $this->redirectToTab(
            'profits',
            'Ajuste global actualizado.'
        );
    }

    public function toggleClientRule(
        ZigoClientPricingRule $clientRule
    ): RedirectResponse {
        $clientRule->update([
            'active' => !$clientRule->active,
        ]);

        return $this->redirectToTab(
            'profits',
            'Regla por cliente actualizada.'
        );
    }

    public function storeRule(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
            ],
            'carrier' => [
                'required',
                'string',
                'max:50',
            ],
            'customer_segment' => [
                'required',
                Rule::in([
                    'anonymous',
                    'b2c',
                    'b2b',
                    'api',
                ]),
            ],
            'plan' => [
                'nullable',
                'string',
                'max:50',
            ],
            'package_type' => [
                'required',
                Rule::in([
                    'sobre',
                    'caja',
                    'all',
                ]),
            ],
            'margin_percentage' => [
                'required',
                'numeric',
                'min:0',
            ],
            'fixed_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        ZigoPricingRule::query()->create([
            'name' => $data['name'],
            'carrier' => Str::upper(
                $data['carrier']
            ),
            'customer_segment' =>
                $data['customer_segment'],
            'plan' => $data['plan'] ?: null,
            'package_type' =>
                $data['package_type'],
            'margin_percentage' =>
                $data['margin_percentage'],
            'fixed_fee' =>
                $data['fixed_fee'] ?? 0,
            'min_price' =>
                $data['min_price'] ?? null,
            'active' => true,
        ]);

        return $this->redirectToTab(
            'profits',
            'Regla base de margen creada correctamente.'
        );
    }

    public function storeAdjustment(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
            ],
            'carrier' => [
                'required',
                'string',
                'max:50',
            ],
            'customer_segment' => [
                'required',
                Rule::in([
                    'anonymous',
                    'b2c',
                    'b2b',
                    'api',
                    'all',
                ]),
            ],
            'package_type' => [
                'required',
                Rule::in([
                    'sobre',
                    'caja',
                    'all',
                ]),
            ],
            'adjustment_type' => [
                'required',
                Rule::in([
                    'surcharge_percentage',
                    'surcharge_fixed',
                    'discount_percentage',
                    'discount_fixed',
                ]),
            ],
            'adjustment_value' => [
                'required',
                'numeric',
                'min:0',
            ],
            'max_uses' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ]);

        ZigoPricingAdjustment::query()->create([
            'name' => $data['name'],
            'carrier' => Str::upper(
                $data['carrier']
            ),
            'customer_segment' =>
                $data['customer_segment'],
            'package_type' =>
                $data['package_type'],
            'adjustment_type' =>
                $data['adjustment_type'],
            'adjustment_value' =>
                $data['adjustment_value'],
            'max_uses' =>
                $data['max_uses'] ?? null,
            'starts_at' =>
                $data['starts_at'] ?? null,
            'ends_at' =>
                $data['ends_at'] ?? null,
            'active' => true,
        ]);

        return $this->redirectToTab(
            'profits',
            'Ajuste global creado correctamente.'
        );
    }

    public function storeClientRule(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
            ],
            'crm_client_id' => [
                'nullable',
                'integer',
            ],
            'api_client_id' => [
                'nullable',
                'integer',
            ],
            'user_id' => [
                'nullable',
                'integer',
            ],
            'customer_segment' => [
                'nullable',
                Rule::in([
                    'b2c',
                    'b2b',
                    'api',
                ]),
            ],
            'package_type' => [
                'required',
                Rule::in([
                    'sobre',
                    'caja',
                    'all',
                ]),
            ],
            'discount_type' => [
                'required',
                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
            ],
            'max_uses' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ]);

        if (
            empty($data['crm_client_id'])
            && empty($data['api_client_id'])
            && empty($data['user_id'])
        ) {
            return $this->redirectToTab(
                'profits',
                'Debes indicar al menos CRM Client, '
                . 'API Client o User.',
                'error'
            );
        }

        ZigoClientPricingRule::query()->create([
            'name' => $data['name'],
            'crm_client_id' =>
                $data['crm_client_id'] ?? null,
            'api_client_id' =>
                $data['api_client_id'] ?? null,
            'user_id' =>
                $data['user_id'] ?? null,
            'customer_segment' =>
                $data['customer_segment'] ?? null,
            'package_type' =>
                $data['package_type'],
            'discount_type' =>
                $data['discount_type'],
            'discount_value' =>
                $data['discount_value'],
            'max_uses' =>
                $data['max_uses'] ?? null,
            'starts_at' =>
                $data['starts_at'] ?? null,
            'ends_at' =>
                $data['ends_at'] ?? null,
            'active' => true,
        ]);

        return $this->redirectToTab(
            'profits',
            'Promoción por cliente creada correctamente.'
        );
    }

    private function createRateLine(
        ZigoProviderRateCard $rateCard,
        array $data,
        int $sortOrder
    ): ZigoProviderRateLine {
        return $rateCard->lines()->create([
            'zone_code' =>
                $data['zone_code'] ?? null,
            'origin_zone' =>
                $data['origin_zone'] ?? null,
            'destination_zone' =>
                $data['destination_zone'] ?? null,
            'min_weight_kg' =>
                $data['min_weight_kg'] ?? null,
            'max_weight_kg' =>
                $data['max_weight_kg'] ?? null,
            'included_weight_kg' =>
                $data['included_weight_kg'] ?? null,
            'base_price' =>
                $data['base_price'] ?? 0,
            'additional_weight_unit_kg' =>
                $data['additional_weight_unit_kg']
                ?? null,
            'additional_weight_price' =>
                $data['additional_weight_price'] ?? 0,
            'extended_area_price' =>
                $data['extended_area_price'] ?? 0,
            'oversize_price' =>
                $data['oversize_price'] ?? 0,
            'insurance_percentage' =>
                $data['insurance_percentage'] ?? 0,
            'fuel_surcharge_percentage' =>
                $data['fuel_surcharge_percentage']
                ?? 0,
            'multipiece_price' =>
                $data['multipiece_price'] ?? 0,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function redirectToTab(
        string $tab,
        string $message,
        string $messageType = 'success'
    ): RedirectResponse {
        return redirect()
            ->route(
                'crm.pricing.index',
                [
                    'tab' => $tab,
                ]
            )
            ->with(
                $messageType,
                $message
            );
    }
}
