<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guías B2C - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}
        .panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}
        .filters{display:grid;grid-template-columns:1fr 220px auto;gap:14px;align-items:end}
        label{font-weight:900;display:block;margin-bottom:6px}input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}
        .btn-gray{background:#334155}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}.badge-red{background:#fee2e2;color:#991b1b}.badge-green{background:#dcfce7;color:#166534}.muted{color:#64748b;font-size:13px}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Guías B2C</div>
        <div class="subtitle">
            Guías relacionadas con una cotización y un usuario B2C. Desde aquí se registra el adeudo reportado por Xperta.
        </div>

        <section class="panel">
            <form method="GET" action="{{ route('crm.guias.index') }}" class="filters">
                <div>
                    <label>Buscar</label>
                    <input name="search" value="{{ $search }}" placeholder="Tracking, cotización, usuario o correo">
                </div>
                <div>
                    <label>Adeudo</label>
                    <select name="adeudo">
                        <option value="TODOS" @selected($debtFilter === 'TODOS')>Todos</option>
                        <option value="PENDIENTES" @selected($debtFilter === 'PENDIENTES')>Con adeudo pendiente</option>
                        <option value="SIN_ADEUDOS" @selected($debtFilter === 'SIN_ADEUDOS')>Sin adeudos</option>
                    </select>
                </div>
                <button class="btn" type="submit">Consultar</button>
            </form>
        </section>

        <section class="panel">
            <table>
                <thead>
                    <tr>
                        <th>Guía</th>
                        <th>Cotización</th>
                        <th>Usuario</th>
                        <th>Servicio</th>
                        <th>Peso cotizado</th>
                        <th>Precio cliente</th>
                        <th>Adeudo pendiente</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cotizaciones as $cotizacion)
                        @php
                            $pendingDebt = $cotizacion->adeudos
                                ->whereIn('estatus', ['PENDIENTE', 'PAGO_INICIADO'])
                                ->sum('monto');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $cotizacion->tracking_number ?: 'Sin tracking' }}</strong>
                                <div class="muted">Guía local: {{ $cotizacion->guia_id ?: '-' }}</div>
                            </td>
                            <td>#{{ $cotizacion->id }}</td>
                            <td>
                                <strong>{{ $cotizacion->user->name ?? '-' }}</strong>
                                <div class="muted">{{ $cotizacion->user->email ?? '-' }}</div>
                            </td>
                            <td>{{ $cotizacion->logistico }} / {{ $cotizacion->servicio }}</td>
                            <td>{{ number_format((float) ($cotizacion->peso_facturable ?: $cotizacion->peso), 2) }} kg</td>
                            <td>${{ number_format((float) $cotizacion->precio, 2) }}</td>
                            <td>
                                @if($pendingDebt > 0)
                                    <span class="badge badge-red">${{ number_format($pendingDebt, 2) }}</span>
                                @else
                                    <span class="badge badge-green">Sin adeudo</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-gray" href="{{ route('crm.guias.show', $cotizacion) }}">Ver / Registrar adeudo</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No se encontraron guías B2C relacionadas con usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:18px">{{ $cotizaciones->links() }}</div>
        </section>
    </main>
</div>
</body>
</html>
