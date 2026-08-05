<div class="note" style="margin-bottom:22px;">
    Estas reglas se aplican después de obtener el costo proveedor.
    No sustituyen ni modifican los tarifarios por LTD.
</div>

<details class="card compact-panel" open>
    <summary><div class="summary-main"><div class="summary-title">Reglas comerciales por concepto</div><div class="summary-subtitle">Utilidad antes de IVA para cada componente operativo.</div></div></summary>
    <div class="panel-body">
        <form method="POST" action="{{ route('crm.pricing.concept-rules.store') }}">@csrf
            <div class="form-grid">
                <div><label>Nombre</label><input name="name" required></div>
                <div><label>Carrier</label><input name="carrier" value="ESTAFETA" required></div>
                <div><label>Servicio</label><select name="service"><option value="all">Todos</option><option value="terrestre">Terrestre</option><option value="diasig">Día siguiente</option></select></div>
                <div><label>Segmento</label><select name="segment"><option value="all">Todos</option><option value="anonymous">Anónimo</option><option value="b2c">B2C</option><option value="b2b">B2B</option><option value="api">API</option></select></div>
                <div><label>Plan</label><input name="plan" placeholder="Opcional"></div>
                <div><label>Tipo de paquete</label><select name="package_type"><option value="all">Todos</option><option value="caja">Caja</option><option value="sobre">Sobre</option></select></div>
                <div><label>Concepto</label><select name="concept"><option value="base">Base</option><option value="area_extendida">Área extendida</option><option value="kg_extra">Kg extra</option><option value="seguro">Seguro</option><option value="otros">Otros</option></select></div>
                <div><label>Tipo de ajuste</label><select name="adjustment_type"><option value="porcentaje">Porcentaje</option><option value="monto_fijo">Monto fijo</option><option value="sin_margen">Sin margen</option></select></div>
                <div><label>Valor</label><input type="number" step="0.0001" min="0" name="value" value="0" required></div>
                <div><label>Prioridad</label><input type="number" min="0" name="priority" value="100" required></div>
                <div><label>Vigencia desde</label><input type="datetime-local" name="starts_at"></div>
                <div><label>Vigencia hasta</label><input type="datetime-local" name="ends_at"></div>
            </div>
            <button class="btn" type="submit" style="margin-top:14px">Crear regla por concepto</button>
        </form>
        <div class="table-wrap" style="margin-top:20px"><table><thead><tr><th>Nombre</th><th>Carrier</th><th>Servicio</th><th>Segmento</th><th>Plan</th><th>Paquete</th><th>Concepto</th><th>Ajuste</th><th>Valor</th><th>Prioridad</th><th>Vigencia</th><th>Estatus</th><th>Acción</th></tr></thead><tbody>
        @forelse($conceptRules as $rule)<tr><td>{{ $rule->name }}</td><td>{{ $rule->carrier }}</td><td>{{ $rule->service }}</td><td>{{ $rule->segment }}</td><td>{{ $rule->plan ?: '-' }}</td><td>{{ $rule->package_type }}</td><td>{{ $rule->concept }}</td><td>{{ $rule->adjustment_type }}</td><td>{{ $rule->value }}</td><td>{{ $rule->priority }}</td><td>{{ $rule->starts_at?->format('d/m/Y') ?? '-' }} / {{ $rule->ends_at?->format('d/m/Y') ?? '-' }}</td><td>{{ $rule->active ? 'Activa' : 'Inactiva' }}</td><td><form method="POST" action="{{ route('crm.pricing.concept-rules.toggle', $rule) }}">@csrf<button class="btn btn-sm" type="submit">{{ $rule->active ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @empty<tr><td colspan="13">No hay reglas comerciales por concepto.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</details>

<div class="compact-actions">
    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Nueva regla base</div>
                <div class="summary-subtitle">Margen, cargo fijo y precio mínimo.</div>
            </div>
        </summary>
        <div class="panel-body">
    <form method="POST"
          action="{{ route('crm.pricing.rules.store') }}">
        @csrf

        <div class="form-grid">
            <div>
                <label>Nombre</label>
                <input name="name"
                       placeholder="Ej. B2C DHL caja"
                       required>
            </div>

            <div>
                <label>Carrier</label>
                <input name="carrier"
                       placeholder="Ej. DHL"
                       required>
            </div>

            <div>
                <label>Segmento</label>
                <select name="customer_segment" required>
                    <option value="anonymous">Anonymous</option>
                    <option value="b2c">B2C</option>
                    <option value="b2b">B2B</option>
                    <option value="api">API</option>
                </select>
            </div>

            <div>
                <label>Plan</label>
                <select name="plan">
                    <option value="">Sin plan</option>
                    <option value="STARTER">STARTER</option>
                    <option value="BUSINESS">BUSINESS</option>
                    <option value="ENTERPRISE">ENTERPRISE</option>
                </select>
            </div>

            <div>
                <label>Paquete</label>
                <select name="package_type" required>
                    <option value="sobre">Sobre</option>
                    <option value="caja">Caja</option>
                    <option value="all">Todos</option>
                </select>
            </div>

            <div>
                <label>Margen %</label>
                <input type="number"
                       step="0.01"
                       name="margin_percentage"
                       value="40"
                       min="0"
                       required>
            </div>

            <div>
                <label>Cargo fijo</label>
                <input type="number"
                       step="0.01"
                       name="fixed_fee"
                       value="0"
                       min="0">
            </div>

            <div>
                <label>Precio mínimo</label>
                <input type="number"
                       step="0.01"
                       name="min_price"
                       min="0">
            </div>
        </div>

        <div style="margin-top:14px;">
            <button class="btn" type="submit">
                Crear regla base
            </button>
        </div>
    </form>
        </div>
    </details>

    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Nuevo ajuste global</div>
                <div class="summary-subtitle">Temporadas, cargos y descuentos generales.</div>
            </div>
        </summary>
        <div class="panel-body">
    <form method="POST"
          action="{{ route('crm.pricing.adjustments.store') }}">
        @csrf

        <div class="form-grid">
            <div>
                <label>Nombre</label>
                <input name="name"
                       placeholder="Ej. Temporada alta +10%"
                       required>
            </div>

            <div>
                <label>Carrier</label>
                <input name="carrier"
                       placeholder="Ej. ESTAFETA"
                       required>
            </div>

            <div>
                <label>Segmento</label>
                <select name="customer_segment" required>
                    <option value="all">Todos</option>
                    <option value="anonymous">Anonymous</option>
                    <option value="b2c">B2C</option>
                    <option value="b2b">B2B</option>
                    <option value="api">API</option>
                </select>
            </div>

            <div>
                <label>Paquete</label>
                <select name="package_type" required>
                    <option value="all">Todos</option>
                    <option value="sobre">Sobre</option>
                    <option value="caja">Caja</option>
                </select>
            </div>

            <div>
                <label>Tipo</label>
                <select name="adjustment_type" required>
                    <option value="surcharge_percentage">Cargo %</option>
                    <option value="surcharge_fixed">Cargo fijo</option>
                    <option value="discount_percentage">Descuento %</option>
                    <option value="discount_fixed">Descuento fijo</option>
                </select>
            </div>

            <div>
                <label>Valor</label>
                <input type="number"
                       step="0.01"
                       name="adjustment_value"
                       value="10"
                       min="0"
                       required>
            </div>

            <div>
                <label>Máximo de usos</label>
                <input type="number"
                       name="max_uses"
                       min="1">
            </div>

            <div>
                <label>Inicio</label>
                <input type="date" name="starts_at">
            </div>

            <div>
                <label>Fin</label>
                <input type="date" name="ends_at">
            </div>
        </div>

        <div style="margin-top:14px;">
            <button class="btn" type="submit">
                Crear ajuste
            </button>
        </div>
    </form>
        </div>
    </details>

    <details class="card compact-panel">
        <summary>
            <div class="summary-main">
                <div class="summary-title">Nueva promoción</div>
                <div class="summary-subtitle">Descuento controlado por cliente o usuario.</div>
            </div>
        </summary>
        <div class="panel-body">
    <form method="POST"
          action="{{ route('crm.pricing.client-rules.store') }}">
        @csrf

        <div class="form-grid">
            <div>
                <label>Nombre</label>
                <input name="name"
                       placeholder="Ej. Primeras 20 guías"
                       required>
            </div>

            <div>
                <label>CRM Client ID</label>
                <input type="number" name="crm_client_id">
            </div>

            <div>
                <label>API Client ID</label>
                <input type="number" name="api_client_id">
            </div>

            <div>
                <label>User ID</label>
                <input type="number" name="user_id">
            </div>

            <div>
                <label>Segmento</label>
                <select name="customer_segment">
                    <option value="">Cualquiera</option>
                    <option value="b2c">B2C</option>
                    <option value="b2b">B2B</option>
                    <option value="api">API</option>
                </select>
            </div>

            <div>
                <label>Paquete</label>
                <select name="package_type" required>
                    <option value="all">Todos</option>
                    <option value="sobre">Sobre</option>
                    <option value="caja">Caja</option>
                </select>
            </div>

            <div>
                <label>Tipo de descuento</label>
                <select name="discount_type" required>
                    <option value="fixed">Monto fijo</option>
                    <option value="percentage">Porcentaje</option>
                </select>
            </div>

            <div>
                <label>Valor</label>
                <input type="number"
                       step="0.01"
                       name="discount_value"
                       value="10"
                       min="0"
                       required>
            </div>

            <div>
                <label>Máximo de usos</label>
                <input type="number"
                       name="max_uses"
                       min="1">
            </div>

            <div>
                <label>Inicio</label>
                <input type="date" name="starts_at">
            </div>

            <div>
                <label>Fin</label>
                <input type="date" name="ends_at">
            </div>
        </div>

        <div style="margin-top:14px;">
            <button class="btn" type="submit">
                Crear promoción
            </button>
        </div>
    </form>
        </div>
    </details>
</div>

<details class="card compact-panel table-panel" open>
    <summary>
        <div class="summary-main">
            <div class="summary-title">Reglas base de ganancia</div>
            <div class="panel-heading-count">{{ $pricingRules->count() }} registro(s)</div>
        </div>
    </summary>
    <div class="panel-body">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Carrier</th>
                    <th>Segmento</th>
                    <th>Plan</th>
                    <th>Paquete</th>
                    <th>Margen</th>
                    <th>Cargo fijo</th>
                    <th>Precio mínimo</th>
                    <th>Estatus</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pricingRules as $rule)
                    <tr>
                        <td>{{ $rule->id }}</td>
                        <td>{{ $rule->name }}</td>
                        <td>{{ $rule->carrier }}</td>
                        <td>{{ $rule->customer_segment }}</td>
                        <td>{{ $rule->plan ?? '-' }}</td>
                        <td>{{ $rule->package_type }}</td>
                        <td>{{ $rule->margin_percentage }}%</td>
                        <td>${{ number_format((float) $rule->fixed_fee, 2) }}</td>
                        <td>
                            {{ $rule->min_price !== null
                                ? '$' . number_format((float) $rule->min_price, 2)
                                : '-' }}
                        </td>
                        <td>
                            <span class="badge {{ $rule->active ? 'on' : 'off' }}">
                                {{ $rule->active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('crm.pricing.rules.toggle', $rule) }}">
                                @csrf
                                <button class="btn btn-sm {{ $rule->active ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $rule->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">
                            No hay reglas base configuradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</details>

<details class="card compact-panel table-panel">
    <summary>
        <div class="summary-main">
            <div class="summary-title">Ajustes globales y de temporada</div>
            <div class="panel-heading-count">{{ $adjustments->count() }} registro(s)</div>
        </div>
    </summary>
    <div class="panel-body">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Carrier</th>
                    <th>Segmento</th>
                    <th>Paquete</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Vigencia</th>
                    <th>Usos</th>
                    <th>Estatus</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adjustment)
                    <tr>
                        <td>{{ $adjustment->id }}</td>
                        <td>{{ $adjustment->name }}</td>
                        <td>{{ $adjustment->carrier }}</td>
                        <td>{{ $adjustment->customer_segment }}</td>
                        <td>{{ $adjustment->package_type }}</td>
                        <td>{{ $adjustment->adjustment_type }}</td>
                        <td>{{ $adjustment->adjustment_value }}</td>
                        <td class="nowrap">
                            {{ $adjustment->starts_at?->format('d/m/Y') ?? '-' }}
                            /
                            {{ $adjustment->ends_at?->format('d/m/Y') ?? '-' }}
                        </td>
                        <td>
                            {{ $adjustment->used_count }}
                            /
                            {{ $adjustment->max_uses ?? '∞' }}
                        </td>
                        <td>
                            <span class="badge {{ $adjustment->active ? 'on' : 'off' }}">
                                {{ $adjustment->active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('crm.pricing.adjustments.toggle', $adjustment) }}">
                                @csrf
                                <button class="btn btn-sm {{ $adjustment->active ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $adjustment->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">
                            No hay ajustes configurados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</details>

<details class="card compact-panel table-panel">
    <summary>
        <div class="summary-main">
            <div class="summary-title">Promociones por cliente</div>
            <div class="panel-heading-count">{{ $clientRules->count() }} registro(s)</div>
        </div>
    </summary>
    <div class="panel-body">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>CRM Client</th>
                    <th>API Client</th>
                    <th>User</th>
                    <th>Segmento</th>
                    <th>Paquete</th>
                    <th>Descuento</th>
                    <th>Usos</th>
                    <th>Estatus</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientRules as $clientRule)
                    <tr>
                        <td>{{ $clientRule->id }}</td>
                        <td>{{ $clientRule->name }}</td>
                        <td>{{ $clientRule->crm_client_id ?? '-' }}</td>
                        <td>{{ $clientRule->api_client_id ?? '-' }}</td>
                        <td>{{ $clientRule->user_id ?? '-' }}</td>
                        <td>{{ $clientRule->customer_segment ?? '-' }}</td>
                        <td>{{ $clientRule->package_type }}</td>
                        <td>
                            {{ $clientRule->discount_type }}
                            /
                            {{ $clientRule->discount_value }}
                        </td>
                        <td>
                            {{ $clientRule->used_count }}
                            /
                            {{ $clientRule->max_uses ?? '∞' }}
                        </td>
                        <td>
                            <span class="badge {{ $clientRule->active ? 'on' : 'off' }}">
                                {{ $clientRule->active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('crm.pricing.client-rules.toggle', $clientRule) }}">
                                @csrf
                                <button class="btn btn-sm {{ $clientRule->active ? 'btn-red' : 'btn-green' }}"
                                        type="submit">
                                    {{ $clientRule->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">
                            No hay promociones por cliente.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</details>
