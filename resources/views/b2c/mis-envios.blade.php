<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis envíos - ZIGO</title>
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
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900}
        .btn{display:inline-block;padding:8px 10px;border-radius:8px;text-decoration:none;font-weight:800;font-size:12px;margin:2px}
        .primary{background:#2563eb;color:white}
        .info{background:#0891b2;color:white}
        .success{background:#16a34a;color:white}
        .warning{background:#f59e0b;color:white}
        .empty{background:#eff6ff;color:#1e40af;padding:16px;border-radius:12px;font-weight:700}
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
            <a href="#">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Mis envíos</div>
        <div class="subtitle">Consulta tus cotizaciones, pagos, guías y tracking.</div>

        @if(session('success'))
            <div style="background:#dcfce7;color:#166534;padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px">
                {{ session('success') }}
            </div>
        @endif

        <div class="card">
            @if($envios->isEmpty())
                <div class="empty">
                    Aún no tienes envíos registrados.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Paquetería</th>
                            <th>Servicio</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Precio</th>
                            <th>Estatus pago</th>
                            <th>Estado guía</th>
                            <th>Tracking</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($envios as $envio)
                            <tr>
                                <td>{{ $envio->id }}</td>
                                <td>
                                    @php
                                        $logo = match($envio->logistico) {
                                            'FedEx' => 'img/fedex-logo.png',
                                            'Estafeta' => 'img/png-transparent-estafeta.png',
                                            'DHL' => 'img/dhl.png',
                                            default => null,
                                        };
                                    @endphp

                                    @if($logo)
                                        <img src="{{ asset($logo) }}" alt="{{ $envio->logistico }}" style="width:90px;height:auto;display:block;">
                                    @else
                                        {{ $envio->logistico ?? '-' }}
                                    @endif
                                </td>
                                <td>{{ $envio->servicio ?? '-' }}</td>
                                <td>
                                    {{ $envio->cp_origen ?? '-' }}
                                    @if($envio->colonia_origen) - {{ $envio->colonia_origen }} @endif
                                    @if($envio->ciudad_origen) - {{ $envio->ciudad_origen }} @endif
                                    @if($envio->estado_origen) - {{ $envio->estado_origen }} @endif
                                </td>

                                <td>
                                    {{ $envio->cp_destino ?? '-' }}
                                    @if($envio->colonia_destino) - {{ $envio->colonia_destino }} @endif
                                    @if($envio->ciudad_destino) - {{ $envio->ciudad_destino }} @endif
                                    @if($envio->estado_destino) - {{ $envio->estado_destino }} @endif
                                </td>
                                <td>${{ number_format($envio->precio ?? 0, 2) }}</td>
                                <td>{{ $envio->payment_status ?? $envio->estatus ?? '-' }}</td>
                                <td>{{ $envio->guia_estatus ?? 'SIN_GUIA' }}</td>
                                <td>{{ $envio->tracking_number ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('b2c.envios.detalle', $envio->id) }}" class="btn primary">
                                        Ver detalle
                                    </a>

                                    @if($envio->tracking_number)
                                        <a href="{{ url('/rastreo?tracking_number=' . $envio->tracking_number) }}" class="btn info">
                                            Rastrear
                                        </a>
                                    @endif

                                    @if($envio->documento)
                                        <a href="{{ asset('storage/' . basename($envio->documento)) }}" target="_blank" class="btn success">
                                            Descargar guía
                                        </a>
                                    @endif

                                    @if(in_array($envio->estatus, ['PAGADA', 'ERROR_GENERACION_GUIA']) && !$envio->tracking_number)
                                        <form method="POST" action="{{ route('b2c.guia.generar', $envio->id) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn warning" style="border:none;cursor:pointer">
                                                Generar guía
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</div>

@if(session('pdf_url'))
<script>
    window.open('{{ session('pdf_url') }}', '_blank');
</script>
@endif

</body>
</html>