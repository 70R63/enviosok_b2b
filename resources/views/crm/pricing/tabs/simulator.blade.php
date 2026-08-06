<section class="card">
    <h2>Simulador comercial</h2>

    <div class="note" style="margin-bottom:18px;">
        Este simulador recibe el costo proveedor y aplica margen,
        cargo fijo, ajustes y promociones ZIGO.
    </div>

    <form method="POST"
          action="{{ route('crm.pricing.simulate') }}">
        @csrf

        <div class="form-grid-5">
            <div>
                <label>Carrier</label>
                <select name="carrier" required>
                    @foreach($pricingCarriers as $carrier)
                        <option value="{{ $carrier }}"
                            @selected(old('carrier') === $carrier)>
                            {{ $carrier }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Segmento</label>
                <select name="customer_segment" required>
                    <option value="anonymous"
                        @selected(old('customer_segment') === 'anonymous')>
                        Anonymous
                    </option>
                    <option value="b2c"
                        @selected(old('customer_segment') === 'b2c')>
                        B2C
                    </option>
                    <option value="b2b"
                        @selected(old('customer_segment') === 'b2b')>
                        B2B
                    </option>
                    <option value="api"
                        @selected(old('customer_segment') === 'api')>
                        API
                    </option>
                </select>
            </div>

            <div>
                <label>Plan</label>
                <select name="plan">
                    <option value="">Sin plan</option>
                    <option value="STARTER"
                        @selected(old('plan') === 'STARTER')>
                        STARTER
                    </option>
                    <option value="BUSINESS"
                        @selected(old('plan') === 'BUSINESS')>
                        BUSINESS
                    </option>
                    <option value="ENTERPRISE"
                        @selected(old('plan') === 'ENTERPRISE')>
                        ENTERPRISE
                    </option>
                </select>
            </div>

            <div>
                <label>Paquete</label>
                <select name="package_type" required>
                    <option value="sobre"
                        @selected(old('package_type') === 'sobre')>
                        Sobre
                    </option>
                    <option value="caja"
                        @selected(old('package_type') === 'caja')>
                        Caja
                    </option>
                    <option value="all"
                        @selected(old('package_type') === 'all')>
                        Todos
                    </option>
                </select>
            </div>

            <div>
                <label>Tarifa proveedor</label>
                <input type="number"
                       step="0.01"
                       name="base_price"
                       value="{{ old('base_price', 100) }}"
                       min="1"
                       required>
            </div>
            <div><label>Servicio</label><select name="service"><option value="terrestre">Terrestre</option><option value="diasig">Día siguiente</option></select></div>
            <div><label>Área extendida operativa</label><input type="number" step="0.01" min="0" name="extended_area" value="{{ old('extended_area', 0) }}"></div>
            <div><label>Kg extra operativo</label><input type="number" step="0.01" min="0" name="extra_kg" value="{{ old('extra_kg', 0) }}"></div>
            <div><label>Seguro operativo</label><input type="number" step="0.01" min="0" name="insurance" value="{{ old('insurance', 0) }}"></div>
            <div><label>Otros operativos</label><input type="number" step="0.01" min="0" name="others" value="{{ old('others', 0) }}"></div>
            <div><label>Tasa IVA</label><input type="number" step="0.01" min="0" max="1" name="vat_rate" value="{{ old('vat_rate', 0.16) }}" required></div>

            <div>
                <label>CRM Client ID</label>
                <input type="number"
                       name="crm_client_id"
                       value="{{ old('crm_client_id') }}">
            </div>

            <div>
                <label>API Client ID</label>
                <input type="number"
                       name="api_client_id"
                       value="{{ old('api_client_id') }}">
            </div>

            <div>
                <label>User ID</label>
                <input type="number"
                       name="user_id"
                       value="{{ old('user_id') }}">
            </div>
        </div>

        <div style="margin-top:16px;">
            <button class="btn" type="submit">
                Simular precio
            </button>
        </div>
    </form>
</section>

@if(session('simulation'))
    @php($simulation = session('simulation'))

    <section class="card">
        <h2>Resultado de la simulación</h2>

        <div class="grid-2">
            <div><h3>INTERNO</h3>
                @foreach($simulation['operational_breakdown'] as $concept => $amount)<div class="summary-line"><span>{{ $concept }}</span><strong>${{ number_format($amount, 2) }}</strong></div>@endforeach
                <h4>Reglas aplicadas</h4>
                @forelse($simulation['applied_rules'] as $concept => $rule)<div>{{ $concept }}: {{ $rule['rule_name'] }} · ajuste ${{ number_format($rule['adjustment_amount'], 2) }} · ZIGO ${{ number_format($rule['commercial_amount'], 2) }}</div>@empty<div>Sin reglas por concepto.</div>@endforelse
            </div>
            <div><h3>CLIENTE</h3>
                @foreach(['base'=>'Envío','area_extendida'=>'Cargo por área extendida','kg_extra'=>'Kilogramos adicionales','seguro'=>'Protección del envío','otros'=>'Otros cargos'] as $concept => $label)
                    <div class="summary-line"><span>{{ $label }}</span><strong>${{ number_format($simulation['commercial_breakdown'][$concept], 2) }}</strong></div>
                @endforeach
                <div class="summary-line"><span>Subtotal</span><strong>${{ number_format($simulation['commercial_subtotal'], 2) }}</strong></div>
                <div class="summary-line"><span>IVA</span><strong>${{ number_format($simulation['vat'], 2) }}</strong></div>
                <div class="summary-line"><span>Total</span><strong>${{ number_format($simulation['customer_total'], 2) }}</strong></div>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:18px;">
            <table>
                <tbody>
                    <tr><th>Total proveedor (control)</th><td>${{ number_format($simulation['provider_control_total'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endif
