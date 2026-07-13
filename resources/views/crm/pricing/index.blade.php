<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tarifas - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:24px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
        .form-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
        input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:10px 14px;border-radius:9px;border:none;cursor:pointer}
        .btn-red{background:#dc2626}
        .btn-green{background:#16a34a}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:900}
        .on{background:#dcfce7;color:#166534}
        .off{background:#fee2e2;color:#991b1b}
        .success{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .result{background:#eff6ff;border:1px solid #bfdbfe;padding:16px;border-radius:12px}
        @media(max-width:1000px){
            .layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .grid,.form-grid{grid-template-columns:1fr}
            .content{padding:24px}
        }
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Tarifas ZIGO</div>
        <div class="subtitle">
            Control de márgenes, ajustes por temporada, promociones por cliente y simulación de precios.
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800;">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <h2>Simulador de precio</h2>

            <form method="POST" action="{{ route('crm.pricing.simulate') }}">
                @csrf

                <div class="form-grid">
                    <div>
                        <label>Carrier</label>
                        <select name="carrier">
                            <option value="ESTAFETA" @selected(old('carrier') === 'ESTAFETA')>ESTAFETA</option>
                        </select>
                    </div>

                    <div>
                        <label>Segmento</label>
                        <select name="customer_segment">
                            <option value="anonymous" @selected(old('customer_segment') === 'anonymous')>Anonymous</option>
                            <option value="b2c" @selected(old('customer_segment') === 'b2c')>B2C</option>
                            <option value="b2b" @selected(old('customer_segment') === 'b2b')>B2B</option>
                            <option value="api" @selected(old('customer_segment') === 'api')>API</option>
                        </select>
                    </div>

                    <div>
                        <label>Plan</label>
                        <select name="plan">
                            <option value="">Sin plan</option>
                            <option value="STARTER" @selected(old('plan') === 'STARTER')>STARTER</option>
                            <option value="BUSINESS" @selected(old('plan') === 'BUSINESS')>BUSINESS</option>
                            <option value="ENTERPRISE" @selected(old('plan') === 'ENTERPRISE')>ENTERPRISE</option>
                        </select>
                    </div>

                    <div>
                        <label>Paquete</label>
                        <select name="package_type">
                            <option value="sobre" @selected(old('package_type') === 'sobre')>Sobre</option>
                            <option value="caja" @selected(old('package_type') === 'caja')>Caja</option>
                            <option value="all" @selected(old('package_type') === 'all')>Todos</option>
                        </select>
                    </div>

                    <div>
                        <label>Tarifa proveedor</label>
                        <input type="number" step="0.01" name="base_price" value="{{ old('base_price', 100) }}">
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <button class="btn" type="submit">Simular</button>
                </div>
            </form>

            @if(session('simulation'))
                @php($s = session('simulation'))

                <div class="result" style="margin-top:20px;">
                    <h3>Resultado</h3>

                    <p><strong>Tarifa proveedor:</strong> ${{ number_format($s['base_price'], 2) }}</p>
                    <p><strong>Regla base:</strong> {{ $s['pricing_rule_name'] }}</p>
                    <p><strong>Margen:</strong> {{ $s['margin_percentage'] }}% / ${{ number_format($s['margin_amount'], 2) }}</p>

                    @if($s['adjustment_name'])
                        <p><strong>Ajuste:</strong> {{ $s['adjustment_name'] }} / ${{ number_format($s['adjustment_amount'], 2) }}</p>
                    @endif

                    @if($s['client_pricing_rule_name'])
                        <p><strong>Promoción cliente:</strong> {{ $s['client_pricing_rule_name'] }} / -${{ number_format($s['discount_amount'], 2) }}</p>
                    @endif

                    <h2>Precio final: ${{ number_format($s['final_price'], 2) }}</h2>
                    <p><strong>Utilidad estimada ZIGO:</strong> ${{ number_format($s['profit_amount'], 2) }}</p>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('crm.pricing.rules.store') }}" style="margin-bottom:20px;">
            @csrf

            <div class="form-grid">
                <div>
                    <label>Nombre</label>
                    <input name="name" placeholder="Ej. B2C temporada normal sobre" required>
                </div>

                <div>
                    <label>Carrier</label>
                    <select name="carrier">
                        <option value="ESTAFETA">ESTAFETA</option>
                    </select>
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
                    <input type="number" step="0.01" name="margin_percentage" value="50" required>
                </div>

                <div>
                    <label>Cargo fijo</label>
                    <input type="number" step="0.01" name="fixed_fee" value="0">
                </div>

                <div>
                    <label>Precio mínimo</label>
                    <input type="number" step="0.01" name="min_price">
                </div>
            </div>

            <div style="margin-top:14px;">
                <button class="btn" type="submit">Crear regla base</button>
            </div>
        </form>

        <div class="card">
            <h2>Reglas base de margen</h2>

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
                            <td>${{ number_format($rule->fixed_fee, 2) }}</td>
                            <td>
                                <span class="badge {{ $rule->active ? 'on' : 'off' }}">
                                    {{ $rule->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('crm.pricing.rules.toggle', $rule) }}">
                                    @csrf
                                    <button class="btn {{ $rule->active ? 'btn-red' : 'btn-green' }}" type="submit">
                                        {{ $rule->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10">No hay reglas base configuradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('crm.pricing.adjustments.store') }}" style="margin-bottom:20px;">
            @csrf

            <div class="form-grid">
                <div>
                    <label>Nombre</label>
                    <input name="name" placeholder="Ej. Temporada alta B2C +10%" required>
                </div>

                <div>
                    <label>Carrier</label>
                    <select name="carrier">
                        <option value="ESTAFETA">ESTAFETA</option>
                    </select>
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
                    <input type="number" step="0.01" name="adjustment_value" value="10" required>
                </div>

                <div>
                    <label>Máx. usos</label>
                    <input type="number" name="max_uses">
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
                <button class="btn" type="submit">Crear ajuste global</button>
            </div>
        </form>

        <div class="card">
            <h2>Ajustes globales / temporada</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
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
                            <td>{{ $adjustment->customer_segment }}</td>
                            <td>{{ $adjustment->package_type }}</td>
                            <td>{{ $adjustment->adjustment_type }}</td>
                            <td>{{ $adjustment->adjustment_value }}</td>
                            <td>
                                {{ $adjustment->starts_at ? $adjustment->starts_at->format('d/m/Y') : '-' }}
                                /
                                {{ $adjustment->ends_at ? $adjustment->ends_at->format('d/m/Y') : '-' }}
                            </td>
                            <td>{{ $adjustment->used_count }} / {{ $adjustment->max_uses ?? '∞' }}</td>
                            <td>
                                <span class="badge {{ $adjustment->active ? 'on' : 'off' }}">
                                    {{ $adjustment->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('crm.pricing.adjustments.toggle', $adjustment) }}">
                                    @csrf
                                    <button class="btn {{ $adjustment->active ? 'btn-red' : 'btn-green' }}" type="submit">
                                        {{ $adjustment->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10">No hay ajustes globales configurados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('crm.pricing.client-rules.store') }}" style="margin-bottom:20px;">
            @csrf

            <div class="form-grid">
                <div>
                    <label>Nombre</label>
                    <input name="name" placeholder="Ej. Primeras 20 guías - $10" required>
                </div>

                <div>
                    <label>CRM Client ID</label>
                    <input type="number" name="crm_client_id" placeholder="Ej. 3">
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
                    <label>Tipo descuento</label>
                    <select name="discount_type" required>
                        <option value="fixed">Monto fijo</option>
                        <option value="percentage">Porcentaje</option>
                    </select>
                </div>

                <div>
                    <label>Valor</label>
                    <input type="number" step="0.01" name="discount_value" value="10" required>
                </div>

                <div>
                    <label>Máx. usos</label>
                    <input type="number" name="max_uses" placeholder="Ej. 20">
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
                <button class="btn" type="submit">Crear promoción por cliente</button>
            </div>
        </form>

        <div class="card">
            <h2>Promociones por cliente</h2>

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
                            <td>{{ $clientRule->discount_type }} / {{ $clientRule->discount_value }}</td>
                            <td>{{ $clientRule->used_count }} / {{ $clientRule->max_uses ?? '∞' }}</td>
                            <td>
                                <span class="badge {{ $clientRule->active ? 'on' : 'off' }}">
                                    {{ $clientRule->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('crm.pricing.client-rules.toggle', $clientRule) }}">
                                    @csrf
                                    <button class="btn {{ $clientRule->active ? 'btn-red' : 'btn-green' }}" type="submit">
                                        {{ $clientRule->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11">No hay promociones por cliente configuradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>