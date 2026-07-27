<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prepago - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px;}
        .zigo-logo{
            text-align:center;
            padding:8px;
        }

        .zigo-img{
            width:180px;
            max-width:100%;
            display:block;
            margin:0 auto;
            animation:zigoEntrance 1s ease-out;
            transition:all .35s ease;
        }

        .zigo-logo:hover .zigo-img{
            transform:scale(1.03);
            filter:
                drop-shadow(0 0 8px rgba(0,255,255,.45))
                drop-shadow(0 0 14px rgba(0,128,255,.35));
        }

        .zigo-tagline{
            margin-top:8px;
            font-size:10px;
            letter-spacing:2px;
            color:#dce7f7;
            text-transform:uppercase;
            font-weight:600;
            line-height:1.5;
        }

        @keyframes zigoEntrance{
            from{
                opacity:0;
                transform:translateX(-35px);
            }
            to{
                opacity:1;
                transform:translateX(0);
            }
        }
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .grid{display:grid;grid-template-columns:1fr 280px;gap:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .saldo{font-size:34px;font-weight:900;color:#16a34a}
        input{width:100%;height:46px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;box-sizing:border-box;font-size:16px}
        .btn{background:#2563eb;color:white;border:none;border-radius:10px;padding:14px 20px;font-weight:900;cursor:pointer;margin-top:14px}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc}
        .alert-ok{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800}
        .alert-error{background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800}
        .hint{font-size:13px;color:#64748b;margin-top:8px}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo zigo-logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">

            <div class="zigo-tagline">
                Tecnología • Logística • Conexión
            </div>
        </div>
        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a href="{{ route('b2c.adeudos.index') }}">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Prepago</div>
        <div class="subtitle">Recarga saldo y úsalo para pagar tus próximas guías.</div>

        @if(session('success'))
            <div class="alert-ok">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif

        <div class="grid">
            <section class="card">
                <h2>Recargar saldo</h2>

                <form method="POST" action="{{ route('b2c.prepago.recargar') }}">
                    @csrf

                    <label>Monto a recargar</label>
                    <input
                        type="number"
                        name="monto"
                        min="300"
                        max="20000"
                        step="1"
                        placeholder="Mínimo $300 MXN"
                        required
                    >

                    <div class="hint">
                        Monto mínimo $300 MXN. Máximo $20,000 MXN por recarga.
                    </div>

                    <button type="submit" class="btn">
                        Recargar con Mercado Pago
                    </button>
                </form>
            </section>

            <aside class="card">
                <h2>Saldo actual</h2>
                <div class="saldo">
                    ${{ number_format($saldo->saldo, 2) }}
                </div>
                <div class="hint">Saldo disponible para pagar guías.</div>
            </aside>
        </div>

        <section class="card" style="margin-top:24px">
            <h2>Últimos movimientos</h2>

            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Saldo anterior</th>
                        <th>Saldo nuevo</th>
                        <th>Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $mov)
                        <tr>
                            <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $mov->tipo }}</td>
                            <td>${{ number_format($mov->monto, 2) }}</td>
                            <td>${{ number_format($mov->saldo_anterior, 2) }}</td>
                            <td>${{ number_format($mov->saldo_nuevo, 2) }}</td>
                            <td>{{ $mov->referencia ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Aún no tienes movimientos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>