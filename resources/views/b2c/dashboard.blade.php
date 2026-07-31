<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi cuenta - ZIGO</title>
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
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .financial-summary{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:30px}
        .financial-card{display:flex;align-items:center;justify-content:space-between;gap:20px;background:white;border-radius:18px;padding:20px 24px;box-shadow:0 10px 24px rgba(0,0,0,.08);text-decoration:none;color:#111827;border:1px solid #e2e8f0}
        .financial-card:hover{border-color:#93c5fd;transform:translateY(-1px)}
        .financial-title{font-size:14px;color:#64748b;font-weight:800}
        .financial-value{font-size:30px;font-weight:900;margin-top:7px}
        .financial-value.balance{color:#16a34a}
        .financial-value.debt{color:#dc2626}
        .financial-value.clear{color:#334155}
        .financial-link{color:#2563eb;font-size:13px;font-weight:900;white-space:nowrap}
        .label{color:#64748b;font-size:14px}
        .value{font-size:32px;font-weight:900;margin-top:8px}
        .actions{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
        .action{background:white;border-radius:18px;padding:28px;text-decoration:none;color:#111827;box-shadow:0 10px 24px rgba(0,0,0,.08);font-weight:900}
        .action span{display:block;color:#64748b;font-weight:500;margin-top:8px}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900;color:#334155}
        input,select{padding:14px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px}
        .field{position:relative}
        .field label{display:block;font-size:13px;font-weight:800;margin-bottom:6px;color:#334155}
        .autocomplete-wrap{position:relative}
        .suggestions{position:absolute;top:74px;left:0;right:0;background:white;color:#111827;border-radius:8px;box-shadow:0 12px 28px rgba(0,0,0,.18);z-index:100;overflow:hidden}
        .suggestion-item{padding:12px;cursor:pointer;border-bottom:1px solid #e5e7eb}
        .suggestion-item:hover{background:#f1f5f9}

        .cotizador-box{
            background:#3867f6;
            color:white;
            border-radius:14px;
            padding:24px;
            margin-bottom:30px;
            box-shadow:0 10px 24px rgba(0,0,0,.12)
        }

        .cotizador-box h2{
            text-align:center;
            margin:0 0 18px 0;
            font-size:20px;
            color:white
        }

        .cotizador-grid{
            display:grid;
            grid-template-columns:1.4fr 1.4fr 1fr .8fr 1fr .8fr;
            gap:14px;
            align-items:end
        }

        .field{
            position:relative
        }

        .field label{
            display:block;
            font-size:13px;
            font-weight:800;
            margin-bottom:6px;
            color:white
        }

        .field input,
        .field select{
            width:100%;
            box-sizing:border-box;
            border:none;
            border-radius:9px;
            padding:13px;
            font-size:14px
        }

        .cotizador-btn{
            width:100%;
            background:#f97316;
            color:white;
            border:none;
            border-radius:9px;
            padding:13px 20px;
            font-weight:900;
            font-size:16px;
            cursor:pointer
        }

        .autocomplete-wrap{
            position:relative
        }

        .suggestions{
            position:absolute;
            top:66px;
            left:0;
            right:0;
            background:white;
            color:#111827;
            border-radius:8px;
            box-shadow:0 12px 28px rgba(0,0,0,.18);
            z-index:9999;
            overflow:hidden
        }

        .suggestion-item{
            padding:12px;
            cursor:pointer;
            border-bottom:1px solid #e5e7eb
        }

        .suggestion-item:hover{
            background:#f1f5f9
        }

        .opciones-cotizacion{
            margin-bottom:30px
        }

        .opcion-row{
            display:grid;
            grid-template-columns:1.5fr 1fr .7fr .7fr;
            gap:20px;
            align-items:center;
            padding:18px;
            border:2px solid #e5e7eb;
            border-radius:16px;
            margin-top:15px;
            background:white
        }

        .opcion-row:first-of-type{
            border-color:#22c55e
        }

        .precio-opcion{
            font-size:24px;
            font-weight:900;
            color:#111827
        }

        .comprar-btn{
            background:#2563eb;
            color:white;
            border:none;
            border-radius:12px;
            padding:13px 20px;
            font-weight:900;
            cursor:pointer
        }

        @media(max-width:1100px){
            .cards{grid-template-columns:repeat(2,1fr)}
            .financial-summary{grid-template-columns:1fr}
        }

        @media(max-width:760px){
            .layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .content{padding:20px}
            .cards{grid-template-columns:1fr}
            .financial-card{align-items:flex-start}
        }
    </style>

    <link
        rel="stylesheet"
        href="{{ asset('css/zigo-cotizador.css') }}"
    >
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
        <div class="title">Hola, {{ auth()->user()->name }}</div>
        <div class="subtitle">Panel B2C para administrar tus envíos.</div>

        <div class="cards">
            <div class="card">
                <div class="label">Cotizaciones</div>
                <div class="value">{{ $totalCotizaciones }}</div>
            </div>
            <div class="card">
                <div class="label">Pagadas</div>
                <div class="value">{{ $totalPagadas }}</div>
            </div>
            <div class="card">
                <div class="label">Guías generadas</div>
                <div class="value">{{ $totalGuias }}</div>
            </div>
            <div class="card">
                <div class="label">Con error</div>
                <div class="value">{{ $totalErrores }}</div>
            </div>
        </div>

        <section class="financial-summary">
            <a
                class="financial-card"
                href="{{ route('b2c.prepago') }}"
            >
                <div>
                    <div class="financial-title">
                        Saldo disponible
                    </div>
                    <div class="financial-value balance">
                        ${{ number_format(
                            (float) $saldoResumen->saldo,
                            2
                        ) }} MXN
                    </div>
                </div>
                <span class="financial-link">
                    Ver movimientos
                </span>
            </a>

            <a
                class="financial-card"
                href="{{ route('b2c.adeudos.index') }}"
            >
                <div>
                    <div class="financial-title">
                        Adeudo pendiente
                    </div>

                    @if((float) $adeudoPendiente > 0)
                        <div class="financial-value debt">
                            ${{ number_format(
                                (float) $adeudoPendiente,
                                2
                            ) }} MXN
                        </div>
                    @else
                        <div class="financial-value clear">
                            Sin adeudos pendientes
                        </div>
                    @endif
                </div>
                <span class="financial-link">
                    Ver adeudos
                </span>
            </a>
        </section>

        @include('b2c.partials.cotizador', [
            'cotizadorAction' => route('b2c.cotizador-rapido'),
            'cotizadorPublico' => false,
            'cotizacionActual' => $cotizacionActual ?? null,
            'limpiarRoute' => $cotizacionActual
                ? route('b2c.cotizador-rapido.limpiar')
                : null,
        ])

        @if(session('cotizacion_id') && session('opciones'))
            <div class="card opciones-cotizacion">
                <h2>Opciones disponibles</h2>

                @foreach(session('opciones') as $opcion)
                    <form method="POST" action="/b2c/cotizacion/{{ session('cotizacion_id') }}/seleccionar" class="opcion-row">
                        @csrf

                        <input type="hidden" name="logistico" value="{{ $opcion['logistico'] }}">
                        <input type="hidden" name="servicio" value="{{ $opcion['servicio'] }}">
                        <input type="hidden" name="precio" value="{{ $opcion['precio'] }}">

                        <div>
                            <strong>{{ $opcion['logistico'] }}</strong>
                            <div>{{ $opcion['servicio'] }}</div>
                        </div>

                        <div>{{ $opcion['entrega'] }}</div>

                        <div class="precio-opcion">
                            ${{ number_format($opcion['precio'], 2) }}
                        </div>

                        <button type="submit" class="comprar-btn">Comprar</button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="actions">
            <a class="action" href="{{ route('b2c.nuevo-envio') }}">
                Nuevo envío
                <span>Captura origen, destino y paquete.</span>
            </a>

            <a class="action" href="{{ url('/rastreo') }}">
                Rastrear envío
                <span>Consulta el estado de tu paquete.</span>
            </a>

            <a class="action" href="{{ route('b2c.mis-envios') }}">
                Mis envíos
                <span>Consulta tus cotizaciones y guías.</span>
            </a>
        </div>

        <div class="card" style="margin-top:30px">
            <h2>Últimos envíos</h2>

            @if($ultimosEnvios->isEmpty())
                <p>Aún no tienes envíos registrados.</p>
            @else
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Paquetería</th>
                            <th>Servicio</th>
                            <th>Precio</th>
                            <th>Estatus</th>
                            <th>Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ultimosEnvios as $envio)
                            <tr>
                                <td>{{ $envio->id }}</td>
                                <td>{{ $envio->logistico ?? '-' }}</td>
                                <td>{{ $envio->servicio ?? '-' }}</td>
                                <td>${{ number_format($envio->precio ?? 0, 2) }}</td>
                                <td>{{ $envio->estatus_label }}</td>
                                <td>{{ $envio->tracking_number ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
            </main>
        </div>
<script src="{{ asset('js/zigo-cotizador.js') }}"></script>
</body>
</html>