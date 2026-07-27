<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Adeudos B2C - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}.menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}.menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}.content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}.card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}.label{color:#64748b;font-weight:800;font-size:13px}.value{font-size:30px;font-weight:900;margin-top:7px}.filters{display:grid;grid-template-columns:1fr 240px auto;gap:14px;align-items:end}label{font-weight:900;display:block;margin-bottom:6px}input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}.btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}.btn-gray{background:#334155}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}.badge-red{background:#fee2e2;color:#991b1b}.badge-green{background:#dcfce7;color:#166534}.muted{color:#64748b;font-size:13px}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')
    <main class="content">
        <div class="title">Adeudos B2C</div>
        <div class="subtitle">Control de ajustes de Xperta relacionados con guías y usuarios B2C.</div>

        <section class="grid">
            <div class="card"><div class="label">Adeudos pendientes</div><div class="value">{{ number_format($pendingCount) }}</div></div>
            <div class="card"><div class="label">Importe pendiente</div><div class="value">${{ number_format((float) $pendingTotal, 2) }}</div></div>
        </section>

        <section class="panel">
            <form method="GET" action="{{ route('crm.adeudos.index') }}" class="filters">
                <div><label>Buscar</label><input name="search" value="{{ $search }}" placeholder="Tracking, usuario, referencia o cotización"></div>
                <div>
                    <label>Estado</label>
                    <select name="estatus">
                        @foreach(['TODOS','PENDIENTE','PAGO_INICIADO','PAGADO_SALDO','PAGADO_MERCADOPAGO','CANCELADO','CONDONADO'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', $option) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn" type="submit">Consultar</button>
            </form>
        </section>

        <section class="panel">
            <table>
                <thead><tr><th>ID</th><th>Guía</th><th>Usuario</th><th>Referencia Xperta</th><th>Concepto</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse($adeudos as $adeudo)
                        <tr>
                            <td>#{{ $adeudo->id }}</td>
                            <td>{{ $adeudo->tracking_number ?: '-' }}<div class="muted">Cotización #{{ $adeudo->cotizacion_id }}</div></td>
                            <td><strong>{{ $adeudo->user->name ?? '-' }}</strong><div class="muted">{{ $adeudo->user->email ?? '-' }}</div></td>
                            <td>{{ $adeudo->referencia_xperta }}</td>
                            <td>{{ $adeudo->concepto }}</td>
                            <td>${{ number_format((float) $adeudo->monto, 2) }}</td>
                            <td>
                                <span class="badge {{ in_array($adeudo->estatus, ['PENDIENTE','PAGO_INICIADO']) ? 'badge-red' : 'badge-green' }}">{{ $adeudo->estatus_label }}</span>
                            </td>
                            <td><a class="btn btn-gray" href="{{ route('crm.adeudos.show', $adeudo) }}">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No se encontraron adeudos.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top:18px">{{ $adeudos->links() }}</div>
        </section>
    </main>
</div>
</body>
</html>
