<div class="stats">
    <div class="stat">
        <span class="muted">Fuentes</span>
        <strong>{{ $providerSources->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">Convenios</span>
        <strong>{{ $agreements->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">Servicios asociados</span>
        <strong>{{ $agreementServices->count() }}</strong>
    </div>
    <div class="stat">
        <span class="muted">LTD disponibles</span>
        <strong>{{ $ltds->count() }}</strong>
    </div>
</div>

<div class="note" style="margin-bottom:22px;">
    Una fuente puede integrar varias LTD. Ejemplo:
    <strong>Xperta → Estafeta</strong> y
    <strong>Xperta → DHL</strong>.
    Cada convenio administra únicamente los servicios contratados para esa LTD.
</div>

<div class="compact-actions">
    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Nueva fuente</div>
                <div class="summary-subtitle">Integrador, contrato directo o fuente manual.</div>
            </div>
        </summary>
        <div class="panel-body">
        <form method="POST"
              action="{{ route('crm.pricing.sources.store') }}">
            @csrf

            <div class="form-grid">
                <div>
                    <label>Código</label>
                    <input name="code"
                           placeholder="Ej. DHL_DIRECTO"
                           required>
                </div>

                <div>
                    <label>Nombre</label>
                    <input name="name"
                           placeholder="Ej. DHL directo"
                           required>
                </div>

                <div>
                    <label>Tipo</label>
                    <select name="source_type" required>
                        <option value="INTEGRATOR">Integrador</option>
                        <option value="DIRECT">Directo</option>
                        <option value="MANUAL">Manual / contractual</option>
                    </select>
                </div>

                <div>
                    <label>Notas</label>
                    <input name="notes"
                           placeholder="Descripción opcional">
                </div>
            </div>

            <div style="margin-top:14px;">
                <button class="btn" type="submit">
                    Crear fuente
                </button>
            </div>
        </form>
        </div>
    </details>

    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Nuevo convenio fuente–LTD</div>
                <div class="summary-subtitle">Ejemplo: Xperta → DHL.</div>
            </div>
        </summary>
        <div class="panel-body">
    <form method="POST"
          action="{{ route('crm.pricing.agreements.store') }}">
        @csrf

        <div class="form-grid">
            <div>
                <label>Fuente</label>
                <select name="provider_source_id" required>
                    <option value="">Selecciona</option>
                    @foreach($providerSources->where('active', true) as $source)
                        <option value="{{ $source->id }}">
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>LTD</label>
                <select name="ltd_id" required>
                    <option value="">Selecciona</option>
                    @foreach($ltds as $ltd)
                        <option value="{{ $ltd->id }}">
                            {{ $ltd->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Nombre del convenio</label>
                <input name="name"
                       placeholder="Ej. Xperta - DHL"
                       required>
            </div>

            <div>
                <label>Modo tarifario</label>
                <select name="rate_mode" required>
                    <option value="DYNAMIC_API">Dinámico API</option>
                    <option value="MANUAL">Manual</option>
                    <option value="HYBRID">Híbrido</option>
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
                <label>Referencia externa</label>
                <input name="external_reference"
                       placeholder="Contrato o identificador">
            </div>

            <div style="grid-column:span 3;">
                <label>Notas</label>
                <input name="notes"
                       placeholder="Condiciones generales del convenio">
            </div>
        </div>

        <div style="margin-top:14px;">
            <button class="btn" type="submit">
                Crear convenio
            </button>
        </div>
    </form>
        </div>
    </details>

    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Asociar servicio</div>
                <div class="summary-subtitle">Vincula los servicios contratados con cada convenio.</div>
            </div>
        </summary>
        <div class="panel-body">
        <form method="POST"
              action="{{ route('crm.pricing.agreement-services.store') }}">
            @csrf

            <div class="form-grid">
                <div style="grid-column:span 2;">
                    <label>Convenio</label>
                    <select name="shipping_agreement_id" required>
                        <option value="">Selecciona</option>
                        @foreach($agreements->where('active', true) as $agreement)
                            <option value="{{ $agreement->id }}">
                                {{ $agreement->source?->name }}
                                → {{ $agreement->ltd?->nombre }}
                                → {{ $agreement->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Servicio ZIGO</label>
                    <select name="servicio_id" required>
                        <option value="">Selecciona</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Código externo</label>
                    <input name="external_service_code"
                           placeholder="Ej. express">
                </div>

                <div>
                    <label>Nombre visible</label>
                    <input name="display_name"
                           placeholder="Ej. Express">
                </div>

                <div>
                    <label>Prioridad</label>
                    <input type="number"
                           name="priority"
                           value="100"
                           min="1"
                           required>
                </div>
            </div>

            <div style="margin-top:14px;">
                <button class="btn" type="submit">
                    Asociar servicio
                </button>
            </div>
        </form>
    </section>
        </div>
    </details>
</div>

    <section class="card">
        <h2>Fuentes registradas</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Convenios</th>
                        <th>Estatus</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providerSources as $source)
                        <tr>
                            <td><strong>{{ $source->code }}</strong></td>
                            <td>{{ $source->name }}</td>
                            <td>{{ $source->source_type }}</td>
                            <td>{{ $source->agreements_count }}</td>
                            <td>
                                <span class="badge {{ $source->active ? 'on' : 'off' }}">
                                    {{ $source->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('crm.pricing.sources.toggle', $source) }}">
                                    @csrf
                                    <button class="btn btn-sm {{ $source->active ? 'btn-red' : 'btn-green' }}"
                                            type="submit">
                                        {{ $source->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                No existen fuentes registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

<section class="card">
    <h2>Convenios registrados</h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fuente</th>
                    <th>LTD</th>
                    <th>Convenio</th>
                    <th>Modo</th>
                    <th>Prioridad</th>
                    <th>Servicios</th>
                    <th>Vigencia</th>
                    <th>Estatus</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agreements as $agreement)
                    <tr>
                        <td>{{ $agreement->source?->name }}</td>
                        <td>{{ $agreement->ltd?->nombre }}</td>
                        <td>
                            <strong>{{ $agreement->name }}</strong>
                            @if($agreement->external_reference)
                                <div class="small muted">
                                    {{ $agreement->external_reference }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $agreement->rate_mode === 'DYNAMIC_API' ? 'dynamic' : 'manual' }}">
                                {{ $agreement->rate_mode }}
                            </span>
                        </td>
                        <td>{{ $agreement->priority }}</td>
                        <td>{{ $agreement->services_count }}</td>
                        <td class="nowrap">
                            {{ $agreement->valid_from?->format('d/m/Y') ?? '-' }}
                            /
                            {{ $agreement->valid_to?->format('d/m/Y') ?? '-' }}
                        </td>
                        <td>
                            <span class="badge {{ $agreement->active ? 'on' : 'off' }}">
                                {{ $agreement->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('crm.pricing.agreements.toggle', $agreement) }}">
                                @csrf
                                <button class="btn btn-sm {{ $agreement->active ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $agreement->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            No existen convenios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

    <section class="card">
        <h2>Servicios por convenio</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fuente / LTD</th>
                        <th>Convenio</th>
                        <th>Servicio</th>
                        <th>Código externo</th>
                        <th>Tarifarios</th>
                        <th>Estatus</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agreementServices as $agreementService)
                        <tr>
                            <td>
                                {{ $agreementService->agreement?->source?->name }}
                                /
                                {{ $agreementService->agreement?->ltd?->nombre }}
                            </td>
                            <td>{{ $agreementService->agreement?->name }}</td>
                            <td>
                                {{ $agreementService->display_name
                                    ?: $agreementService->service?->nombre }}
                            </td>
                            <td>{{ $agreementService->external_service_code ?? '-' }}</td>
                            <td>{{ $agreementService->rate_cards_count }}</td>
                            <td>
                                <span class="badge {{ $agreementService->active ? 'on' : 'off' }}">
                                    {{ $agreementService->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('crm.pricing.agreement-services.toggle', $agreementService) }}">
                                    @csrf
                                    <button class="btn btn-sm {{ $agreementService->active ? 'btn-red' : 'btn-green' }}"
                                            type="submit">
                                        {{ $agreementService->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                No existen servicios asociados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
