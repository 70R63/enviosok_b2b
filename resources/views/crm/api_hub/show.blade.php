<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle API Hub - CRM ZIGO</title>
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
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-weight:800;font-size:14px}
        .value{font-size:30px;font-weight:900;margin-top:8px}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#dbeafe;color:#1e40af}
        .badge-orange{background:#ffedd5;color:#9a3412}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-red{background:#dc2626}
        .btn-gray{background:#334155}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        th{background:#f8fafc;font-weight:900}
        input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        label{font-weight:900;display:block;margin-bottom:6px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:16px;align-items:end}
        .success{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .muted{color:#64748b;font-size:13px}
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Detalle API Hub</div>
        <div class="subtitle">Administración del cliente API, plan, límite mensual y consumos.</div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if(session('new_api_key'))
            <div class="success" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">
                <strong>API Key generada:</strong>
                <div style="margin-top:8px;font-family:monospace;font-size:15px;background:white;padding:12px;border-radius:8px;border:1px solid #fed7aa">
                    {{ session('new_api_key') }}
                </div>
                <div style="margin-top:8px">
                    Cópiala ahora. Por seguridad no volverá a mostrarse.
                </div>
            </div>
        @endif

        <p>
            <a class="btn btn-gray" href="{{ route('crm.api-hub.index') }}">← Volver a API Hub</a>
            <a
                class="btn"
                href="{{ route('crm.api-hub.products', $apiClient) }}"
            >Administrar productos</a>
        </p>

        <div class="grid">
            <div class="card">
                <div class="label">Cliente API</div>
                <div class="value">{{ $apiClient->name }}</div>
                <div class="muted">{{ $apiClient->email }}</div>
            </div>

            <div class="card">
                <div class="label">Plan</div>
                <div class="value">{{ $apiClient->plan }}</div>
                <div class="muted">Límite: {{ number_format($apiClient->monthly_limit) }}</div>
            </div>

            <div class="card">
                <div class="label">Consumo mensual</div>
                <div class="value">{{ number_format($currentMonthUsage) }}</div>
                <div class="muted">No incluye ping técnico</div>
            </div>

            <div class="card">
                <div class="label">Bloqueos 429</div>
                <div class="value">{{ number_format($blockedMonthUsage) }}</div>
                <div class="muted">Límite excedido</div>
            </div>
        </div>

        <div class="panel" style="margin-bottom:24px">
            <h2>Cliente comercial relacionado</h2>

            @if($apiClient->crmClient)
                <p><strong>Nombre:</strong> {{ $apiClient->crmClient->name }}</p>
                <p><strong>Empresa:</strong> {{ $apiClient->crmClient->company_name ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $apiClient->crmClient->email ?? '-' }}</p>
                <p><strong>Tipo:</strong> <span class="badge badge-blue">{{ $apiClient->crmClient->client_type_label }}</span></p>
            @else
                <p><span class="badge badge-orange">Este cliente API no está relacionado con un cliente CRM.</span></p>
            @endif
        </div>

        <div class="panel" style="margin-bottom:24px">
            <h2>Plan y límite mensual</h2>

            <form method="POST" action="{{ route('crm.api-hub.update-plan', $apiClient) }}">
                @csrf

                <div class="form-grid">
                    <div>
                        <label>Plan</label>
                        <select name="plan" required>
                            <option value="FREE" @selected($apiClient->plan === 'FREE')>FREE</option>
                            <option value="STARTER" @selected($apiClient->plan === 'STARTER')>STARTER</option>
                            <option value="BUSINESS" @selected($apiClient->plan === 'BUSINESS')>BUSINESS</option>
                            <option value="ENTERPRISE" @selected($apiClient->plan === 'ENTERPRISE')>ENTERPRISE</option>
                        </select>
                    </div>

                    <div>
                        <label>Límite mensual</label>
                        <input type="number" name="monthly_limit" value="{{ $apiClient->monthly_limit }}" min="1" required>
                    </div>

                    <div>
                        <button class="btn" type="submit">Actualizar plan</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="panel" style="margin-bottom:24px">
            <h2>Estado</h2>

            @if($apiClient->active)
                <p><span class="badge badge-green">Activo</span></p>
            @else
                <p><span class="badge badge-red">Inactivo</span></p>
            @endif

            <form method="POST" action="{{ route('crm.api-hub.toggle-active', $apiClient) }}"
                  onsubmit="return confirm('¿Confirmas cambiar el estado de este cliente API?');">
                @csrf

                @if($apiClient->active)
                    <button class="btn btn-red" type="submit">Suspender cliente API</button>
                @else
                    <button class="btn" type="submit">Reactivar cliente API</button>
                @endif
            </form>
        </div>

        <div class="panel" style="margin-bottom:24px">
            <h2>API Keys</h2>

            <form method="POST" action="{{ route('crm.api-hub.keys.create', $apiClient) }}" style="margin-bottom:18px">
                @csrf

                <div class="form-grid">
                    <div>
                        <label>Nombre de la llave</label>
                        <input type="text" name="name" value="Llave {{ now()->format('YmdHis') }}" required>
                    </div>

                    <div>
                        <label>Ambiente</label>
                        <select name="environment" required>
                            <option value="sandbox">sandbox</option>
                            <option value="production">production</option>
                        </select>
                    </div>

                    <div>
                        <button class="btn" type="submit">+ Crear API Key</button>
                    </div>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Prefijo</th>
                        <th>Ambiente</th>
                        <th>Último uso</th>
                        <th>Estado</th>
                        <th>Creada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($apiClient->keys as $key)
                        <tr>
                            <td>{{ $key->id }}</td>
                            <td>{{ $key->name }}</td>
                            <td>{{ $key->key_prefix }}...</td>
                            <td>{{ $key->environment ?? '-' }}</td>
                            <td>{{ $key->last_used_at ?? 'Sin uso' }}</td>
                            <td>
                                @if($key->active)
                                    <span class="badge badge-green">Activa</span>
                                @else
                                    <span class="badge badge-red">Inactiva</span>
                                @endif
                            </td>
                            <td>{{ $key->created_at }}</td>
                            <td>
                                <form method="POST"
                                    action="{{ route('crm.api-hub.keys.toggle', [$apiClient, $key]) }}"
                                    onsubmit="return confirm('¿Confirmas cambiar el estado de esta API Key?');">
                                    @csrf

                                    @if($key->active)
                                        <button class="btn btn-red" type="submit">Revocar</button>
                                    @else
                                        <button class="btn" type="submit">Reactivar</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No hay API Keys registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Últimos consumos</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Endpoint</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th>Tiempo</th>
                        <th>IP</th>
                        <th>Error</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestLogs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->endpoint }}</td>
                            <td>{{ $log->method }}</td>
                            <td>
                                @if($log->status_code >= 400)
                                    <span class="badge badge-red">{{ $log->status_code }}</span>
                                @else
                                    <span class="badge badge-green">{{ $log->status_code }}</span>
                                @endif
                            </td>
                            <td>{{ $log->response_time_ms }} ms</td>
                            <td>{{ $log->ip }}</td>
                            <td>{{ $log->error_message ?? '-' }}</td>
                            <td>{{ $log->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No hay consumos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>