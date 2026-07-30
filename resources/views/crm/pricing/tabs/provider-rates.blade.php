<div class="stats">
    <div class="stat">
        <span class="muted">Tarifarios</span>
        <strong>{{ $rateCards->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">Activos</span>
        <strong>{{ $rateCards->where('status', 'ACTIVE')->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">Dinámicos API</span>
        <strong>{{ $rateCards->where('pricing_scheme', 'DYNAMIC_API')->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">Renglones manuales</span>
        <strong>{{ $rateCards->sum(fn ($card) => $card->lines->count()) }}</strong>
    </div>
</div>

<div class="note" style="margin-bottom:16px;">
    Cuando un proveedor cambie precios, crea una <strong>nueva versión</strong>.
    La versión anterior permanece disponible como historial.
</div>

<details class="card compact-panel">
    <summary>
        <div class="summary-main">
            <div class="summary-title">Nueva versión tarifaria</div>
            <div class="summary-subtitle">
                Abre este formulario únicamente cuando necesites registrar o actualizar un tarifario.
            </div>
        </div>
    </summary>
    <div class="panel-body">
    <form method="POST"
          action="{{ route('crm.pricing.rate-cards.store') }}">
        @csrf

        <div class="form-grid">
            <div style="grid-column:span 2;">
                <label>Fuente → LTD → Servicio</label>
                <select name="agreement_service_id" required>
                    <option value="">Selecciona</option>
                    @foreach($agreementServices->where('active', true) as $agreementService)
                        <option value="{{ $agreementService->id }}">
                            {{ $agreementService->agreement?->source?->name }}
                            → {{ $agreementService->agreement?->ltd?->nombre }}
                            → {{ $agreementService->display_name
                                ?: $agreementService->service?->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Nombre del tarifario</label>
                <input name="name"
                       placeholder="Ej. DHL Express 2026"
                       required>
            </div>

            <div>
                <label>Esquema</label>
                <select name="pricing_scheme" required>
                    <option value="DYNAMIC_API">Dinámico API</option>
                    <option value="FLAT">Tarifa plana</option>
                    <option value="BASE_PLUS_EXTRA">Base + peso adicional</option>
                    <option value="WEIGHT_RANGE">Rango de peso</option>
                    <option value="ZONE_RANGE">Zona + rango</option>
                </select>
            </div>

            <div>
                <label>Estatus inicial</label>
                <select name="status" required>
                    <option value="DRAFT">Borrador</option>
                    <option value="ACTIVE">Publicar activa</option>
                </select>
            </div>

            <div>
                <label>Moneda</label>
                <input name="currency"
                       value="MXN"
                       maxlength="3"
                       required>
            </div>

            <div>
                <label>IVA %</label>
                <input type="number"
                       step="0.01"
                       name="tax_percentage"
                       value="16"
                       min="0"
                       max="100"
                       required>
            </div>

            <div>
                <label>Prioridad</label>
                <input type="number"
                       name="priority"
                       value="100"
                       min="1"
                       required>
            </div>

            <div>
                <label>Vigente desde</label>
                <input type="date" name="valid_from">
            </div>

            <div>
                <label>Vigente hasta</label>
                <input type="date" name="valid_to">
            </div>

            <div>
                <label>Referencia</label>
                <input name="source_reference"
                       placeholder="Contrato, lista o folio">
            </div>

            <div style="grid-column:span 3;">
                <label>Notas</label>
                <input name="notes"
                       placeholder="Observaciones de esta versión">
            </div>
        </div>

        <div class="warning" style="margin:18px 0;">
            Los campos siguientes crean el primer renglón del tarifario.
            En esquema <strong>Dinámico API</strong> pueden dejarse vacíos.
        </div>

        <div class="form-grid-5">
            <div>
                <label>Zona</label>
                <input name="zone_code" placeholder="Opcional">
            </div>

            <div>
                <label>Zona origen</label>
                <input name="origin_zone" placeholder="Opcional">
            </div>

            <div>
                <label>Zona destino</label>
                <input name="destination_zone" placeholder="Opcional">
            </div>

            <div>
                <label>Peso mínimo kg</label>
                <input type="number"
                       step="0.001"
                       name="min_weight_kg"
                       min="0">
            </div>

            <div>
                <label>Peso máximo kg</label>
                <input type="number"
                       step="0.001"
                       name="max_weight_kg"
                       min="0">
            </div>

            <div>
                <label>Peso incluido kg</label>
                <input type="number"
                       step="0.001"
                       name="included_weight_kg"
                       min="0">
            </div>

            <div>
                <label>Precio base</label>
                <input type="number"
                       step="0.01"
                       name="base_price"
                       min="0">
            </div>

            <div>
                <label>Unidad adicional kg</label>
                <input type="number"
                       step="0.001"
                       name="additional_weight_unit_kg"
                       min="0.001">
            </div>

            <div>
                <label>Precio adicional</label>
                <input type="number"
                       step="0.01"
                       name="additional_weight_price"
                       value="0"
                       min="0">
            </div>

            <div>
                <label>Área extendida</label>
                <input type="number"
                       step="0.01"
                       name="extended_area_price"
                       value="0"
                       min="0">
            </div>

            <div>
                <label>Exceso dimensión</label>
                <input type="number"
                       step="0.01"
                       name="oversize_price"
                       value="0"
                       min="0">
            </div>

            <div>
                <label>Seguro %</label>
                <input type="number"
                       step="0.0001"
                       name="insurance_percentage"
                       value="0"
                       min="0"
                       max="100">
            </div>

            <div>
                <label>Combustible %</label>
                <input type="number"
                       step="0.0001"
                       name="fuel_surcharge_percentage"
                       value="0"
                       min="0"
                       max="100">
            </div>

            <div>
                <label>Multipieza</label>
                <input type="number"
                       step="0.01"
                       name="multipiece_price"
                       value="0"
                       min="0">
            </div>
        </div>

        <div style="margin-top:16px;">
            <button class="btn" type="submit">
                Crear versión
            </button>
        </div>
    </form>
    </div>
</details>

@forelse($rateCards as $rateCard)
    @php
        $agreementService = $rateCard->agreementService;
        $agreement = $agreementService?->agreement;
        $source = $agreement?->source;
        $ltd = $agreement?->ltd;
        $service = $agreementService?->display_name
            ?: $agreementService?->service?->nombre;

        $rateReferences = $agreementService?->rateReferences
            ?? collect();

        $activeReference = $rateReferences->firstWhere(
            'status',
            'ACTIVE'
        );

        $latestObservation =
            $agreementService?->latestQuoteObservation;

        $referenceTotal = null;
        $referenceDifference = null;
        $referenceDifferencePercentage = null;

        if ($activeReference && $latestObservation?->success) {
            $referenceWeight = (float) (
                $latestObservation->weight_kg
                ?? 0
            );

            $includedWeight = (float) (
                $activeReference->included_weight_kg
                ?? 0
            );

            $additionalWeight = max(
                0,
                $referenceWeight - $includedWeight
            );

            $additionalUnit = (float) (
                $activeReference->additional_weight_unit_kg
                ?: 1
            );

            $additionalUnits =
                $additionalWeight > 0
                    ? (int) ceil(
                        $additionalWeight
                        / max($additionalUnit, 0.001)
                    )
                    : 0;

            $referenceSubtotal =
                (float) $activeReference->base_price
                + (
                    $additionalUnits
                    * (float) $activeReference
                        ->additional_weight_price
                );

            $referenceTotal = round(
                $referenceSubtotal
                * (
                    1
                    + (
                        (float) $activeReference
                            ->tax_percentage
                        / 100
                    )
                ),
                2
            );

            $referenceDifference = round(
                (float) $latestObservation->total
                - $referenceTotal,
                2
            );

            $referenceDifferencePercentage =
                $referenceTotal > 0
                    ? round(
                        (
                            $referenceDifference
                            / $referenceTotal
                        )
                        * 100,
                        2
                    )
                    : null;
        }
    @endphp

    <details class="card compact-panel rate-card-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">{{ $rateCard->name }}</div>
                <div class="summary-subtitle">
                    {{ $source?->name }}
                    → {{ $ltd?->nombre }}
                    → {{ $service }}
                </div>
                <div class="summary-meta">
                    @if($rateCard->pricing_scheme === 'DYNAMIC_API')
                        <span>Costo operativo consultado por API</span>
                    @else
                        <span>
                            {{ $rateCard->currency }}
                            /
                            {{ number_format((float) $rateCard->tax_percentage, 2) }}% IVA
                        </span>
                    @endif
                    <span>Prioridad {{ $rateCard->priority }}</span>
                    <span>
                        Vigencia:
                        {{ $rateCard->valid_from?->format('d/m/Y') ?? '-' }}
                        /
                        {{ $rateCard->valid_to?->format('d/m/Y') ?? '-' }}
                    </span>
                    @if($rateCard->pricing_scheme === 'DYNAMIC_API')
                        <span>
                            {{ $rateReferences->count() }}
                            referencia(s) comercial(es)
                        </span>
                    @else
                        <span>
                            {{ $rateCard->lines->count() }}
                            renglón(es)
                        </span>
                    @endif
                </div>
            </div>

            <div class="summary-badges">
                <span class="badge {{ $rateCard->pricing_scheme === 'DYNAMIC_API' ? 'dynamic' : 'manual' }}">
                    {{ $rateCard->pricing_scheme }}
                </span>
                <span class="badge {{ $rateCard->status === 'ACTIVE' ? 'on' : ($rateCard->status === 'DRAFT' ? 'draft' : 'off') }}">
                    {{ $rateCard->status }}
                </span>
                <span class="badge manual">
                    V{{ $rateCard->version }}
                </span>
            </div>
        </summary>

        <div class="panel-body">
        @if($rateCard->source_reference || $rateCard->notes)
            <div class="note" style="margin-top:14px;">
                @if($rateCard->source_reference)
                    <strong>Referencia:</strong>
                    {{ $rateCard->source_reference }}
                    <br>
                @endif

                @if($rateCard->notes)
                    {{ $rateCard->notes }}
                @endif
            </div>
        @endif

        <div class="inline" style="margin-top:14px;">
            <form method="POST"
                  action="{{ route('crm.pricing.rate-cards.toggle', $rateCard) }}">
                @csrf
                <button class="btn btn-sm {{ $rateCard->status === 'ACTIVE' ? 'btn-red' : 'btn-green' }}"
                        type="submit">
                    {{ $rateCard->status === 'ACTIVE'
                        ? 'Desactivar versión'
                        : 'Publicar versión' }}
                </button>
            </form>
        </div>

        @if($rateCard->pricing_scheme === 'DYNAMIC_API')
            <div class="note" style="margin-top:16px;">
                <strong>Configuración operativa dinámica.</strong>
                El precio utilizado para cotizar se consulta en tiempo
                real mediante la API. El costo operativo no se edita
                manualmente.
            </div>

            <div class="grid" style="margin-top:14px;">
                <section class="rate-line">
                    <h3>Último costo API</h3>

                    @if($latestObservation)
                        <div class="grid-3">
                            <div>
                                <strong>Resultado</strong>
                                <div style="margin-top:6px;">
                                    <span class="badge {{ $latestObservation->success ? 'on' : 'off' }}">
                                        {{ $latestObservation->success
                                            ? 'Exitoso'
                                            : 'Error' }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <strong>Costo</strong>
                                <div class="muted">
                                    @if($latestObservation->success)
                                        ${{ number_format((float) $latestObservation->total, 2) }}
                                        {{ $latestObservation->currency }}
                                    @else
                                        Sin costo disponible
                                    @endif
                                </div>
                            </div>

                            <div>
                                <strong>Fecha de consulta</strong>
                                <div class="muted">
                                    {{ $latestObservation->observed_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </div>
                            </div>

                            <div>
                                <strong>Ruta</strong>
                                <div class="muted">
                                    {{ $latestObservation->origin_zip }}
                                    →
                                    {{ $latestObservation->destination_zip }}
                                </div>
                            </div>

                            <div>
                                <strong>Peso y medidas</strong>
                                <div class="muted">
                                    {{ number_format((float) $latestObservation->weight_kg, 3) }} kg
                                    /
                                    {{ number_format((float) $latestObservation->length_cm, 2) }}
                                    ×
                                    {{ number_format((float) $latestObservation->width_cm, 2) }}
                                    ×
                                    {{ number_format((float) $latestObservation->height_cm, 2) }} cm
                                </div>
                            </div>

                            <div>
                                <strong>Área extendida</strong>
                                <div class="muted">
                                    ${{ number_format((float) $latestObservation->extended_area_amount, 2) }}
                                </div>
                            </div>
                        </div>

                        @if(!$latestObservation->success && $latestObservation->error_message)
                            <div class="warning" style="margin-top:12px;">
                                {{ $latestObservation->error_message }}
                            </div>
                        @endif
                    @else
                        <div class="warning">
                            Todavía no existe una consulta API registrada
                            para este servicio.
                        </div>
                    @endif
                </section>

                <section class="rate-line">
                    <h3>Referencia comercial activa</h3>

                    @if($activeReference)
                        <div class="grid-3">
                            <div>
                                <strong>Versión</strong>
                                <div class="muted">
                                    V{{ $activeReference->version }}
                                    /
                                    {{ $activeReference->status }}
                                </div>
                            </div>

                            <div>
                                <strong>Precio base pactado</strong>
                                <div class="muted">
                                    ${{ number_format((float) $activeReference->base_price, 2) }}
                                    {{ $activeReference->currency }}
                                </div>
                            </div>

                            <div>
                                <strong>Peso incluido</strong>
                                <div class="muted">
                                    {{ number_format((float) ($activeReference->included_weight_kg ?? 0), 3) }} kg
                                </div>
                            </div>

                            <div>
                                <strong>Kilogramo adicional</strong>
                                <div class="muted">
                                    ${{ number_format((float) $activeReference->additional_weight_price, 2) }}
                                    cada
                                    {{ number_format((float) ($activeReference->additional_weight_unit_kg ?: 1), 3) }} kg
                                </div>
                            </div>

                            <div>
                                <strong>IVA de referencia</strong>
                                <div class="muted">
                                    {{ number_format((float) $activeReference->tax_percentage, 2) }}%
                                </div>
                            </div>

                            <div>
                                <strong>Vigencia</strong>
                                <div class="muted">
                                    {{ $activeReference->valid_from?->format('d/m/Y') ?? '-' }}
                                    /
                                    {{ $activeReference->valid_to?->format('d/m/Y') ?? '-' }}
                                </div>
                            </div>
                        </div>

                        @if($latestObservation?->success && $referenceTotal !== null)
                            <div class="note" style="margin-top:12px;">
                                <strong>Comparación para la última consulta:</strong>
                                referencia estimada
                                ${{ number_format($referenceTotal, 2) }}
                                /
                                API
                                ${{ number_format((float) $latestObservation->total, 2) }}
                                /
                                diferencia
                                <strong>
                                    {{ $referenceDifference >= 0 ? '+' : '' }}
                                    ${{ number_format($referenceDifference, 2) }}
                                    @if($referenceDifferencePercentage !== null)
                                        (
                                        {{ $referenceDifferencePercentage >= 0 ? '+' : '' }}
                                        {{ number_format($referenceDifferencePercentage, 2) }}%
                                        )
                                    @endif
                                </strong>
                            </div>
                        @endif

                        @if($activeReference->source_reference)
                            <div class="small muted" style="margin-top:10px;">
                                Referencia:
                                {{ $activeReference->source_reference }}
                            </div>
                        @endif

                        @if($activeReference->document_path)
                            <div style="margin-top:10px;">
                                <a class="btn btn-sm btn-gray"
                                   href="{{ route('crm.pricing.rate-references.document', $activeReference) }}">
                                    Descargar documento
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="warning">
                            No existe una tarifa comercial de referencia
                            activa para este servicio.
                        </div>
                    @endif
                </section>
            </div>

            <details style="margin-top:14px;">
                <summary>
                    Historial de referencias
                    ({{ $rateReferences->count() }})
                </summary>

                @forelse($rateReferences as $reference)
                    <div class="rate-line">
                        <div class="grid-3">
                            <div>
                                <strong>
                                    {{ $reference->name }}
                                </strong>
                                <div class="muted">
                                    V{{ $reference->version }}
                                    /
                                    {{ $reference->status }}
                                </div>
                            </div>

                            <div>
                                <strong>Condición pactada</strong>
                                <div class="muted">
                                    Base
                                    ${{ number_format((float) $reference->base_price, 2) }}
                                    /
                                    {{ number_format((float) ($reference->included_weight_kg ?? 0), 3) }} kg incluidos
                                    /
                                    adicional
                                    ${{ number_format((float) $reference->additional_weight_price, 2) }}
                                </div>
                            </div>

                            <div>
                                <strong>Vigencia</strong>
                                <div class="muted">
                                    {{ $reference->valid_from?->format('d/m/Y') ?? '-' }}
                                    /
                                    {{ $reference->valid_to?->format('d/m/Y') ?? '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="inline" style="margin-top:10px;">
                            <span class="badge {{ $reference->status === 'ACTIVE' ? 'on' : ($reference->status === 'DRAFT' ? 'draft' : 'off') }}">
                                {{ $reference->status }}
                            </span>

                            <form method="POST"
                                  action="{{ route('crm.pricing.rate-references.toggle', $reference) }}">
                                @csrf
                                <button class="btn btn-sm {{ $reference->status === 'ACTIVE' ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $reference->status === 'ACTIVE'
                                        ? 'Desactivar referencia'
                                        : 'Activar referencia' }}
                                </button>
                            </form>

                            @if($reference->document_path)
                                <a class="btn btn-sm btn-gray"
                                   href="{{ route('crm.pricing.rate-references.document', $reference) }}">
                                    Documento
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="warning" style="margin-top:12px;">
                        No se han capturado referencias comerciales.
                    </div>
                @endforelse
            </details>

            <details style="margin-top:14px;">
                <summary>Nueva tarifa comercial de referencia</summary>

                <form method="POST"
                      enctype="multipart/form-data"
                      action="{{ route('crm.pricing.rate-references.store', $agreementService) }}"
                      style="margin-top:14px;">
                    @csrf

                    <div class="form-grid">
                        <div style="grid-column:span 2;">
                            <label>Nombre de la referencia</label>
                            <input name="name"
                                   placeholder="Ej. Acuerdo Xperta Estafeta 2026"
                                   required>
                        </div>

                        <div>
                            <label>Estatus inicial</label>
                            <select name="status" required>
                                <option value="DRAFT">Borrador</option>
                                <option value="ACTIVE">Publicar activa</option>
                            </select>
                        </div>

                        <div>
                            <label>Moneda</label>
                            <input name="currency"
                                   value="MXN"
                                   maxlength="3"
                                   required>
                        </div>

                        <div>
                            <label>Peso incluido kg</label>
                            <input type="number"
                                   step="0.001"
                                   name="included_weight_kg"
                                   min="0">
                        </div>

                        <div>
                            <label>Precio base pactado</label>
                            <input type="number"
                                   step="0.01"
                                   name="base_price"
                                   min="0"
                                   required>
                        </div>

                        <div>
                            <label>Unidad adicional kg</label>
                            <input type="number"
                                   step="0.001"
                                   name="additional_weight_unit_kg"
                                   min="0.001"
                                   value="1">
                        </div>

                        <div>
                            <label>Precio adicional</label>
                            <input type="number"
                                   step="0.01"
                                   name="additional_weight_price"
                                   min="0"
                                   value="0">
                        </div>

                        <div>
                            <label>IVA % referencia</label>
                            <input type="number"
                                   step="0.01"
                                   name="tax_percentage"
                                   min="0"
                                   max="100"
                                   value="16"
                                   required>
                        </div>

                        <div>
                            <label>Vigente desde</label>
                            <input type="date" name="valid_from">
                        </div>

                        <div>
                            <label>Vigente hasta</label>
                            <input type="date" name="valid_to">
                        </div>

                        <div>
                            <label>Contrato o folio</label>
                            <input name="source_reference"
                                   maxlength="160">
                        </div>

                        <div style="grid-column:span 2;">
                            <label>Documento soporte</label>
                            <input type="file"
                                   name="source_document"
                                   accept=".pdf,.xls,.xlsx,.csv,.doc,.docx">
                        </div>

                        <div style="grid-column:span 2;">
                            <label>Notas</label>
                            <textarea name="notes"
                                      placeholder="Condiciones comerciales y observaciones"></textarea>
                        </div>
                    </div>

                    <div style="margin-top:14px;">
                        <button class="btn btn-sm" type="submit">
                            Crear versión de referencia
                        </button>
                    </div>
                </form>
            </details>
        @else
            <details>
                <summary>
                    Renglones tarifarios ({{ $rateCard->lines->count() }})
                </summary>

                @forelse($rateCard->lines as $line)
                    <div class="rate-line">
                        <div class="grid-3">
                            <div>
                                <strong>Zona</strong>
                                <div class="muted">
                                    {{ $line->zone_code ?? '-' }}
                                    /
                                    {{ $line->origin_zone ?? '-' }}
                                    →
                                    {{ $line->destination_zone ?? '-' }}
                                </div>
                            </div>

                            <div>
                                <strong>Peso</strong>
                                <div class="muted">
                                    {{ $line->min_weight_kg ?? '-' }}
                                    a
                                    {{ $line->max_weight_kg ?? '-' }} kg
                                    / incluidos
                                    {{ $line->included_weight_kg ?? '-' }} kg
                                </div>
                            </div>

                            <div>
                                <strong>Precio</strong>
                                <div class="muted">
                                    Base ${{ number_format((float) $line->base_price, 2) }}
                                    / adicional
                                    ${{ number_format((float) $line->additional_weight_price, 2) }}
                                </div>
                            </div>
                        </div>

                        <div class="inline" style="margin-top:10px;">
                            <span class="badge {{ $line->active ? 'on' : 'off' }}">
                                {{ $line->active ? 'Activo' : 'Inactivo' }}
                            </span>

                            <form method="POST"
                                  action="{{ route('crm.pricing.rate-lines.toggle', $line) }}">
                                @csrf
                                <button class="btn btn-sm {{ $line->active ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $line->active ? 'Desactivar renglón' : 'Activar renglón' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="warning" style="margin-top:12px;">
                        Esta versión todavía no tiene renglones tarifarios.
                    </div>
                @endforelse

                <details>
                    <summary>Agregar otro renglón</summary>

                    <form method="POST"
                          action="{{ route('crm.pricing.rate-lines.store', $rateCard) }}"
                          style="margin-top:14px;">
                        @csrf

                        <div class="form-grid-5">
                            <div>
                                <label>Zona</label>
                                <input name="zone_code">
                            </div>

                            <div>
                                <label>Zona origen</label>
                                <input name="origin_zone">
                            </div>

                            <div>
                                <label>Zona destino</label>
                                <input name="destination_zone">
                            </div>

                            <div>
                                <label>Peso mínimo kg</label>
                                <input type="number"
                                       step="0.001"
                                       name="min_weight_kg"
                                       min="0">
                            </div>

                            <div>
                                <label>Peso máximo kg</label>
                                <input type="number"
                                       step="0.001"
                                       name="max_weight_kg"
                                       min="0">
                            </div>

                            <div>
                                <label>Peso incluido kg</label>
                                <input type="number"
                                       step="0.001"
                                       name="included_weight_kg"
                                       min="0">
                            </div>

                            <div>
                                <label>Precio base</label>
                                <input type="number"
                                       step="0.01"
                                       name="base_price"
                                       min="0"
                                       required>
                            </div>

                            <div>
                                <label>Unidad adicional kg</label>
                                <input type="number"
                                       step="0.001"
                                       name="additional_weight_unit_kg"
                                       min="0.001">
                            </div>

                            <div>
                                <label>Precio adicional</label>
                                <input type="number"
                                       step="0.01"
                                       name="additional_weight_price"
                                       value="0"
                                       min="0">
                            </div>

                            <div>
                                <label>Área extendida</label>
                                <input type="number"
                                       step="0.01"
                                       name="extended_area_price"
                                       value="0"
                                       min="0">
                            </div>

                            <div>
                                <label>Exceso dimensión</label>
                                <input type="number"
                                       step="0.01"
                                       name="oversize_price"
                                       value="0"
                                       min="0">
                            </div>

                            <div>
                                <label>Seguro %</label>
                                <input type="number"
                                       step="0.0001"
                                       name="insurance_percentage"
                                       value="0"
                                       min="0"
                                       max="100">
                            </div>

                            <div>
                                <label>Combustible %</label>
                                <input type="number"
                                       step="0.0001"
                                       name="fuel_surcharge_percentage"
                                       value="0"
                                       min="0"
                                       max="100">
                            </div>

                            <div>
                                <label>Multipieza</label>
                                <input type="number"
                                       step="0.01"
                                       name="multipiece_price"
                                       value="0"
                                       min="0">
                            </div>
                        </div>

                        <div style="margin-top:14px;">
                            <button class="btn btn-sm" type="submit">
                                Agregar renglón
                            </button>
                        </div>
                    </form>
                </details>
            </details>
        @endif
        </div>
    </details>
@empty
    <section class="card">
        No existen tarifarios registrados.
    </section>
@endforelse
