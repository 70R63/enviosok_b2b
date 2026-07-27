<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Adeudos - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:#2563eb;color:white;padding:30px}.logo{margin-bottom:25px}.zigo-logo{text-align:center;padding:8px}.zigo-img{width:180px;max-width:100%;display:block;margin:0 auto}.zigo-tagline{margin-top:8px;font-size:10px;letter-spacing:2px;color:#dce7f7;text-transform:uppercase;font-weight:600;line-height:1.5}.menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}.menu a.active{background:rgba(255,255,255,.28)}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}.content{padding:40px}.title{font-size:36px;font-weight:900;margin-bottom:8px}.subtitle{color:#64748b;margin-bottom:30px}.grid{display:grid;grid-template-columns:1fr 280px;gap:24px}.card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:24px}.amount{font-size:34px;font-weight:900;color:#dc2626}.balance{font-size:30px;font-weight:900;color:#16a34a}.btn{background:#2563eb;color:white;border:none;border-radius:10px;padding:11px 15px;font-weight:900;cursor:pointer}.btn:disabled{background:#94a3b8;cursor:not-allowed}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}.badge-red{background:#fee2e2;color:#991b1b}.badge-green{background:#dcfce7;color:#166534}.alert-ok{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800}.alert-error{background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800}.hint{font-size:13px;color:#64748b;margin-top:7px}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo zigo-logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">
            <div class="zigo-tagline">Tecnología • Logística • Conexión</div>
        </div>
        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a class="active" href="{{ route('b2c.adeudos.index') }}">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-btn" type="submit">Cerrar sesión</button></form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Adeudos</div>
        <div class="subtitle">Ajustes asociados con el peso o costo final reportado para tus guías.</div>

        @if(session('success'))<div class="alert-ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert-error">{{ session('error') }}</div>@endif

        <section class="grid">
            <div class="card">
                <div class="hint">Total pendiente</div>
                <div class="amount">${{ number_format((float) $pendingTotal, 2) }}</div>
                <div class="hint">Los adeudos no bloquean nuevos envíos durante esta etapa.</div>
            </div>
            <div class="card">
                <div class="hint">Saldo prepago</div>
                <div class="balance">${{ number_format((float) $saldo->saldo, 2) }}</div>
                <a href="{{ route('b2c.prepago') }}" class="hint">Recargar saldo</a>
            </div>
        </section>

        <section class="card">
            <table>
                <thead><tr><th>Guía</th><th>Concepto</th><th>Peso</th><th>Importe</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse($adeudos as $adeudo)
                        <tr>
                            <td><strong>{{ $adeudo->tracking_number ?: '-' }}</strong><div class="hint">Cotización #{{ $adeudo->cotizacion_id }}</div></td>
                            <td>{{ $adeudo->concepto }}<div class="hint">Ref. Xperta: {{ $adeudo->referencia_xperta }}</div></td>
                            <td>{{ $adeudo->peso_cotizado !== null ? number_format((float) $adeudo->peso_cotizado, 2) : '-' }} → {{ $adeudo->peso_real !== null ? number_format((float) $adeudo->peso_real, 2) : '-' }} kg</td>
                            <td>${{ number_format((float) $adeudo->monto, 2) }}</td>
                            <td><span class="badge {{ $adeudo->isPending() ? 'badge-red' : 'badge-green' }}">{{ $adeudo->estatus_label }}</span></td>
                            <td>
                                @if($adeudo->isPending())
                                    <form method="POST" action="{{ route('b2c.adeudos.saldo', $adeudo) }}" onsubmit="return confirm('¿Pagar este adeudo con tu saldo prepago?')">
                                        @csrf
                                        <button class="btn" type="submit" @disabled((float) $saldo->saldo < (float) $adeudo->monto)>Pagar con saldo</button>
                                    </form>
                                    @if((float) $saldo->saldo < (float) $adeudo->monto)
                                        <div class="hint">Saldo insuficiente.</div>
                                    @endif
                                @else
                                    <span class="hint">Sin acciones pendientes.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No tienes adeudos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="margin-top:18px">{{ $adeudos->links() }}</div>
        </section>
    </main>
</div>
</body>
</html>
