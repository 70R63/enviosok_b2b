<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>API Hub - CRM ZIGO</title>
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
        .card{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-weight:800;font-size:14px}
        .value{font-size:34px;font-weight:900;margin-top:8px}
        .panel{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;margin-bottom:18px}
        input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-gray{background:#334155}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        th{background:#f8fafc;font-weight:900}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#dbeafe;color:#1e40af}
        .badge-orange{background:#ffedd5;color:#9a3412}
        .muted{color:#64748b;font-size:13px}
        .progress{height:9px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:6px}
        .progress-bar{height:9px;background:#4361ee}
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">API Hub</div>
        <div class="subtitle">Supervisión interna de clientes API, planes, límites y consumos.</div>

        <div class="grid">
            <div class="card">
                <div class="label">Clientes API</div>
                <div class="value">{{ $totalApiClients }}</div>
            </div>

            <div class="card">
                <div class="label">Clientes activos</div>
                <div class="value">{{ $activeApiClients }}</div>
            </div>

            <div class="card">
                <div class="label">Consumos del mes</div>
                <div class="value">{{ $monthlyUsageTotal }}</div>
            </div>

            <div class="card">
                <div class="label">Bloqueos 429</div>
                <div class="value">{{ $monthlyBlockedTotal }}</div>
            </div>
        </div>

        <div class="panel">
            <form method="GET" action="{{ route('crm.api-hub.index') }}" class="filters">
                <input type="text"
                       name="search"
                       placeholder="Buscar cliente, empresa o email"
                       value="{{ request('search') }}">

                <select name="plan">
                    <option value="">Todos los planes</option>
                    <option value="FREE" @selected(request('plan') === 'FREE')>FREE</option>
                    <option value="STARTER" @selected(request('plan') === 'STARTER')>STARTER</option>
                    <option value="BUSINESS" @selected(request('plan') === 'BUSINESS')>BUSINESS</option>
                    <option value="ENTERPRISE" @selected(request('plan') === 'ENTERPRISE')>ENTERPRISE</option>
                </select>

                <select name="active">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('active') === '1')>Activos</option>
                    <option value="0" @selected(request('active') === '0')>Inactivos</option>
                </select>

                <button class="btn btn-gray" type="submit">Filtrar</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Cliente API</th>
                        <th>Cliente CRM</th>
                        <th>Plan</th>
                        <th>Consumo mensual</th>
                        <th>API Keys</th>
                        <th>Último uso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($apiClients as $client)
                        @php
                            $percentage = $client->monthly_limit > 0
                                ? min(100, round(($client->current_month_usage / $client->monthly_limit) * 100))
                                : 0;
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $client->name }}</strong>
                                <div class="muted">{{ $client->company_name ?? '-' }}</div>
                                <div class="muted">{{ $client->email ?? '-' }}</div>
                            </td>

                            <td>
                                @if($client->crmClient)
                                    <strong>{{ $client->crmClient->name }}</strong>
                                    <div class="muted">{{ $client->crmClient->company_name ?? '-' }}</div>
                                    <span class="badge badge-blue">{{ $client->crmClient->client_type_label }}</span>
                                @else
                                    <span class="badge badge-orange">Sin relación CRM</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge badge-blue">{{ $client->plan }}</span>
                                <div class="muted">Límite: {{ number_format($client->monthly_limit) }}</div>
                            </td>

                            <td>
                                <strong>{{ number_format($client->current_month_usage) }}</strong>
                                <span class="muted">/ {{ number_format($client->monthly_limit) }}</span>

                                <div class="progress">
                                    <div class="progress-bar" style="width: {{ $percentage }}%"></div>
                                </div>

                                <div class="muted">{{ $percentage }}% usado</div>

                                @if($client->blocked_month_usage > 0)
                                    <div>
                                        <span class="badge badge-orange">
                                            {{ $client->blocked_month_usage }} bloqueos 429
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td>
                                <strong>{{ $client->active_keys_count }}</strong>
                                <div class="muted">activas</div>
                            </td>

                            <td>
                                {{ $client->last_used_at ?? 'Sin uso' }}
                            </td>

                            <td>
                                @if($client->active)
                                    <span class="badge badge-green">Activo</span>
                                @else
                                    <span class="badge badge-red">Inactivo</span>
                                @endif
                            </td>

                            <td>
                                <a class="btn" href="{{ route('crm.api-hub.show', $client) }}">Ver detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No hay clientes API registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:18px">
                {{ $apiClients->links() }}
            </div>
        </div>
    </main>
</div>

</body>
</html>