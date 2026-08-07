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
        .filters{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:14px;align-items:end}
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
        <p><a class="btn" href="{{ route('crm.guias.export', array_merge(request()->query(), ['scope'=>'filtered'])) }}">Exportar resultados</a> <a class="btn btn-gray" href="{{ route('crm.guias.export',['scope'=>'all']) }}">Exportar todos</a></p>

        <section class="panel">
            <form method="GET" action="{{ route('crm.guias.index') }}" class="filters">
                <div>
                    <label>Buscar</label>
                    <input name="search" value="{{ $search }}" placeholder="Tracking, cotización, usuario o correo">
                </div>
                @foreach(['cliente'=>'Cliente','empresa'=>'Empresa','carrier'=>'Mensajería','servicio'=>'Servicio','estado'=>'Estado','waybill'=>'WayBill','tracking'=>'Tracking','payment_status'=>'Estado de pago','guia_estatus'=>'Estado de guía','fecha_desde'=>'Fecha desde','fecha_hasta'=>'Fecha hasta'] as $field=>$label)
                <div><label>{{ $label }}</label><input name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" @if(str_starts_with($field,'fecha_')) type="date" @endif></div>
                @endforeach
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
            <h2>Pendientes de recuperación</h2>
            <table>
                <thead><tr><th>Cotización</th><th>Usuario</th><th>Pago / servicio</th><th>Error funcional</th><th>Intentos</th><th>WayBill / tracking</th><th>Documento</th><th>Fecha</th><th>Acción permitida</th></tr></thead>
                <tbody>
                @forelse($recoveryQuotes as $item)
                    <tr>
                        <td>#{{ $item->id }}</td><td>{{ $item->user->name ?? 'Invitado' }}<div class="muted">{{ $item->user->email ?? $item->remitente_email ?? '-' }}</div></td>
                        <td>{{ $item->payment_status }}<div class="muted">{{ $item->service_code ?: $item->servicio }}</div></td>
                        <td><span class="badge badge-red">{{ $item->recovery_case->classification }}</span><div class="muted">{{ $item->guia_last_error_message ?: 'Revisión requerida' }}</div></td>
                        <td>{{ $item->guia_generation_attempts }}</td><td>{{ $item->guia_id ?: '-' }}<div class="muted">{{ $item->tracking_number ?: '-' }}</div></td><td>{{ $item->documento ? 'Disponible' : 'Ausente' }}</td><td>{{ $item->guia_last_attempt_at ?: $item->updated_at }}</td>
                        <td>
                            @if(!$item->guia_id && !$item->tracking_number && !in_array($item->recovery_case->classification,['QUOTE_EXPIRED','MAX_ATTEMPTS']))<form method="POST" action="{{ route('crm.guias.recovery.create',$item) }}">@csrf<button class="btn">Reintentar creación</button></form>@endif
                            @if(!$item->guia_id && !$item->tracking_number && is_array($item->guia_response_snapshot))<form method="POST" action="{{ route('crm.guias.recovery.normalize',$item) }}">@csrf<button class="btn btn-gray">Normalizar snapshot</button></form>@endif
                            @if(($item->guia_id || $item->tracking_number) && !$item->documento)<form method="POST" action="{{ route('crm.guias.recovery.pdf',$item) }}">@csrf<button class="btn">Recuperar PDF</button></form>@endif
                            <form method="POST" action="{{ route('crm.guias.recovery.link',$item) }}">@csrf<button class="btn btn-gray">Renovar/enviar enlace</button></form>
                            @if($item->recovery_case->classification==='QUOTE_EXPIRED')<form method="POST" action="{{ route('crm.guias.recovery.requote',$item) }}">@csrf<input name="nuevo_total" type="number" step="0.01" min="0.01" placeholder="Nuevo total" required><button class="btn">Registrar recotización</button></form>@endif
                        </td>
                    </tr>
                @empty<tr><td colspan="9">No hay guías pendientes de recuperación.</td></tr>@endforelse
                </tbody>
            </table>
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
