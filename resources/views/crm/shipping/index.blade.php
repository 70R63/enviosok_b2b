<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Paqueterías - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:26px}
        .logo{font-size:24px;font-weight:900;margin-bottom:26px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:12px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:10px}
        .menu a.active{background:#4f46e5}.logout-btn{width:100%;border:0;text-align:left;cursor:pointer;font-size:15px}
        .content{padding:34px}.title{font-size:36px;font-weight:900;margin-bottom:8px}.subtitle{color:#64748b;margin-bottom:24px}
        .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:22px}
        .card{background:white;border-radius:16px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.08)}
        .label{color:#64748b;font-size:13px;font-weight:800}.value{font-size:23px;font-weight:900;margin-top:8px}
        .badge{display:inline-block;border-radius:999px;padding:5px 10px;font-weight:900;font-size:12px}.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}.warn{background:#ffedd5;color:#9a3412}
        .alert{padding:13px;border-radius:10px;margin-bottom:16px;font-weight:800}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}
        .form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.form-grid .wide{grid-column:span 2}
        label{display:block;font-weight:900;margin-bottom:6px}input,select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #cbd5e1;border-radius:8px}
        .btn{display:inline-block;border:0;border-radius:9px;background:#4f46e5;color:white;font-weight:900;padding:12px 16px;cursor:pointer;margin-top:14px}
        table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f8fafc}
        @media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{display:none}.grid,.form-grid{grid-template-columns:1fr}.form-grid .wide{grid-column:span 1}}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Paqueterías</div>
        <div class="subtitle">Estado técnico del proveedor interno de cotización y guías.</div>

        @if(session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert error">{{ $errors->first() }}</div>
        @endif

        <div class="grid">
            <div class="card">
                <div class="label">Proveedor activo</div>
                <div class="value">{{ $status['active_provider'] }}</div>
            </div>
            <div class="card">
                <div class="label">Xperta</div>
                <div class="value">
                    @if($status['xperta_enabled'])
                        <span class="badge ok">Habilitado</span>
                    @else
                        <span class="badge warn">Deshabilitado</span>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="label">Ambiente</div>
                <div class="value">{{ strtoupper($status['environment']) }}</div>
            </div>
        </div>

        <div class="card" style="margin-bottom:22px">
            <h2>Configuración</h2>
            <table>
                <thead><tr><th>Variable</th><th>Estado</th></tr></thead>
                <tbody>
                @foreach($status['configured'] as $name => $configured)
                    <tr>
                        <td>{{ strtoupper($name) }}</td>
                        <td>
                            @if($configured)
                                <span class="badge ok">Configurada</span>
                            @else
                                <span class="badge bad">Faltante</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p><strong>Servicios:</strong> {{ implode(', ', $status['services']) ?: 'Sin servicios configurados' }}</p>
        </div>

        <div class="card" style="margin-bottom:22px">
            <h2>Probar autenticación</h2>
            <p>Genera un token nuevo y valida las credenciales sin mostrar el token.</p>
            <form method="POST" action="{{ route('crm.shipping.xperta.test-token') }}">
                @csrf
                <button class="btn" type="submit">Probar token Xperta</button>
            </form>
        </div>

        <div class="card" style="margin-bottom:22px">
            <h2>Probar Frequency</h2>
            <p>Consulta cobertura sin cotizar y sin generar guía.</p>
            <form method="POST" action="{{ route('crm.shipping.xperta.test-frequency') }}">
                @csrf
                <div class="form-grid">
                    <div><label>CP origen</label><input name="cp_origen" value="{{ old('cp_origen', '09800') }}" required maxlength="5"></div>
                    <div><label>CP destino</label><input name="cp_destino" value="{{ old('cp_destino', '57820') }}" required maxlength="5"></div>
                </div>
                <button class="btn" type="submit">Consultar Frequency</button>
            </form>

            @if(session('xperta_frequency_result'))
                @php($frequency = session('xperta_frequency_result'))
                <div style="margin-top:22px">
                    <h3>Resultado normalizado</h3>
                    <table><tbody>
                        <tr><th>Disponible</th><td>{{ $frequency['available'] ? 'Sí' : 'No' }}</td></tr>
                        <tr><th>Origen</th><td>{{ $frequency['origin'] }}</td></tr>
                        <tr><th>Destino</th><td>{{ $frequency['destination'] }}</td></tr>
                        <tr><th>Servicios</th><td>{{ implode(', ', $frequency['services']) ?: 'Sin servicios' }}</td></tr>
                        <tr><th>Restricción</th><td>{{ $frequency['restriction'] ?? 'Sin restricción' }}</td></tr>
                        <tr><th>Descripción</th><td>{{ $frequency['restriction_description'] ?? 'Sin descripción' }}</td></tr>
                    </tbody></table>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Probar cotización</h2>
            <p>Esta operación no genera guía y no modifica cotizaciones B2C.</p>

            <form method="POST" action="{{ route('crm.shipping.xperta.test-quote') }}">
                @csrf
                <div class="form-grid">
                    <div><label>CP origen</label><input name="cp_origen" value="{{ old('cp_origen', '09800') }}" required maxlength="5"></div>
                    <div><label>CP destino</label><input name="cp_destino" value="{{ old('cp_destino', '57820') }}" required maxlength="5"></div>
                    <div><label>Servicio</label><select name="servicio"><option value="terrestre">Terrestre</option><option value="diasig">Día siguiente</option></select></div>
                    <div><label>Peso kg</label><input type="number" step="0.01" min="0.1" name="peso" value="{{ old('peso', '12') }}" required></div>
                    <div><label>Largo cm</label><input type="number" step="0.1" min="0.1" name="largo" value="{{ old('largo', '60') }}" required></div>
                    <div><label>Ancho cm</label><input type="number" step="0.1" min="0.1" name="ancho" value="{{ old('ancho', '10') }}" required></div>
                    <div><label>Alto cm</label><input type="number" step="0.1" min="0.1" name="alto" value="{{ old('alto', '10') }}" required></div>
                    <div><label>Valor declarado</label><input type="number" step="0.01" min="0" name="valor_declarado" value="{{ old('valor_declarado', '0') }}"></div>
                </div>
                <button class="btn" type="submit">Consultar Xperta</button>
            </form>

            @if(session('xperta_quote_result'))
                @php($result = session('xperta_quote_result'))
                <div style="margin-top:22px">
                    <h3>Resultado normalizado</h3>
                    <table>
                        <tbody>
                            <tr><th>Paquetería</th><td>{{ $result['logistico'] }}</td></tr>
                            <tr><th>Servicio</th><td>{{ $result['servicio'] }}</td></tr>
                            <tr><th>Costo Xperta</th><td>${{ number_format($result['base_price'], 2) }}</td></tr>
                            <tr><th>Zona extendida</th><td>{{ $result['extended_area'] ? 'Sí' : 'No' }}</td></tr>
                            <tr><th>Cargo zona extendida</th><td>${{ number_format($result['extended_area_amount'], 2) }}</td></tr>
                            <tr><th>Fuente</th><td>{{ $result['provider_source'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
