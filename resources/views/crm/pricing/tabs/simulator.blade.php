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

        <div class="grid-3">
            <div>
                <strong>Tarifa proveedor</strong>
                <div style="font-size:26px;font-weight:900;margin-top:6px;">
                    ${{ number_format((float) $simulation['base_price'], 2) }}
                </div>
            </div>

            <div>
                <strong>Precio final</strong>
                <div style="font-size:26px;font-weight:900;margin-top:6px;">
                    ${{ number_format((float) $simulation['final_price'], 2) }}
                </div>
            </div>

            <div>
                <strong>Utilidad estimada ZIGO</strong>
                <div style="font-size:26px;font-weight:900;margin-top:6px;">
                    ${{ number_format((float) $simulation['profit_amount'], 2) }}
                </div>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:18px;">
            <table>
                <tbody>
                    <tr>
                        <th>Regla base</th>
                        <td>{{ $simulation['pricing_rule_name'] }}</td>
                    </tr>
                    <tr>
                        <th>Margen</th>
                        <td>
                            {{ $simulation['margin_percentage'] }}%
                            /
                            ${{ number_format((float) $simulation['margin_amount'], 2) }}
                        </td>
                    </tr>
                    <tr>
                        <th>Cargo fijo</th>
                        <td>
                            ${{ number_format((float) $simulation['fixed_fee'], 2) }}
                        </td>
                    </tr>
                    <tr>
                        <th>Ajuste</th>
                        <td>
                            {{ $simulation['adjustment_name'] ?? 'Sin ajuste' }}
                            @if(!empty($simulation['adjustment_name']))
                                /
                                ${{ number_format((float) $simulation['adjustment_amount'], 2) }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Promoción cliente</th>
                        <td>
                            {{ $simulation['client_pricing_rule_name'] ?? 'Sin promoción' }}
                            @if(!empty($simulation['client_pricing_rule_name']))
                                /
                                -${{ number_format((float) $simulation['discount_amount'], 2) }}
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endif
