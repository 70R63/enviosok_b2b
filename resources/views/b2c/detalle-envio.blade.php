<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de envío - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:35px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-size:13px;font-weight:800;margin-bottom:6px}
        .value{font-size:18px;font-weight:900;margin-bottom:16px}
        .btn{display:inline-block;padding:12px 16px;border-radius:10px;text-decoration:none;font-weight:900;margin-right:8px}
        .primary{background:#2563eb;color:white}
        .success{background:#16a34a;color:white}
        .warning{background:#f59e0b;color:white;border:none;cursor:pointer}
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
            <a href="{{ route('b2c.dashboard') }}#cotizador">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="#">Mis direcciones</a>
            <a href="#">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Detalle del envío #{{ $cotizacion->id }}</div>
        <div class="subtitle">Consulta la información completa de tu envío.</div>

        <div class="grid">
            <div class="card">
                <h2>Resumen</h2>

                <div class="label">Paquetería</div>
                <div class="value">{{ $cotizacion->logistico ?? '-' }}</div>

                <div class="label">Servicio</div>
                <div class="value">{{ $cotizacion->servicio ?? '-' }}</div>

                <div class="label">Precio</div>
                <div class="value">${{ number_format($cotizacion->precio ?? 0, 2) }} MXN</div>

                <div class="label">Estatus</div>
                <div class="value">{{ $cotizacion->estatus ?? '-' }}</div>

                <div class="label">Estado guía</div>
                <div class="value">{{ $cotizacion->guia_estatus ?? 'SIN_GUIA' }}</div>
            </div>

            <div class="card">
                <h2>Guía</h2>

                <div class="label">Tracking</div>
                <div class="value">{{ $cotizacion->tracking_number ?? 'Sin tracking' }}</div>

                <div class="label">Documento</div>
                <div class="value">{{ $cotizacion->documento ?? 'Sin documento' }}</div>

                @if($cotizacion->documento)
                    <a href="{{ asset('storage/' . basename($cotizacion->documento)) }}" target="_blank" class="btn success">
                        Descargar guía
                    </a>
                @endif

                @if(in_array($cotizacion->estatus, ['PAGADA', 'ERROR_GENERACION_GUIA']) && !$cotizacion->tracking_number)
                    <form method="POST" action="{{ route('b2c.guia.generar', $cotizacion->id) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn warning">
                            Generar guía
                        </button>
                    </form>
                @endif
            </div>

            <div class="card">
                <h2>Remitente</h2>

                <div class="label">Nombre</div>
                <div class="value">{{ $cotizacion->remitente_nombre ?? '-' }}</div>

                <div class="label">Teléfono</div>
                <div class="value">{{ $cotizacion->remitente_telefono ?? '-' }}</div>

                <div class="label">Correo</div>
                <div class="value">{{ $cotizacion->remitente_email ?? '-' }}</div>

                <div class="label">Dirección</div>
                <div class="value">
                    {{ $cotizacion->remitente_direccion ?? '-' }}
                    {{ $cotizacion->remitente_num_ext ?? '' }}
                    {{ $cotizacion->remitente_num_int ? 'Int. '.$cotizacion->remitente_num_int : '' }}
                </div>

                <div class="label">Ubicación</div>
                <div class="value">
                    {{ $cotizacion->cp_origen }} -
                    {{ $cotizacion->colonia_origen }} -
                    {{ $cotizacion->ciudad_origen }} -
                    {{ $cotizacion->estado_origen }}
                </div>
            </div>

            <div class="card">
                <h2>Destinatario</h2>

                <div class="label">Nombre</div>
                <div class="value">{{ $cotizacion->destinatario_nombre ?? '-' }}</div>

                <div class="label">Teléfono</div>
                <div class="value">{{ $cotizacion->destinatario_telefono ?? '-' }}</div>

                <div class="label">Correo</div>
                <div class="value">{{ $cotizacion->destinatario_email ?? '-' }}</div>

                <div class="label">Dirección</div>
                <div class="value">
                    {{ $cotizacion->destinatario_direccion ?? '-' }}
                    {{ $cotizacion->destinatario_num_ext ?? '' }}
                    {{ $cotizacion->destinatario_num_int ? 'Int. '.$cotizacion->destinatario_num_int : '' }}
                </div>

                <div class="label">Ubicación</div>
                <div class="value">
                    {{ $cotizacion->cp_destino }} -
                    {{ $cotizacion->colonia_destino }} -
                    {{ $cotizacion->ciudad_destino }} -
                    {{ $cotizacion->estado_destino }}
                </div>
            </div>

            <div class="card">
                <h2>Pago</h2>

                <div class="label">ID de pago</div>
                <div class="value">{{ $cotizacion->payment_id ?? 'No disponible' }}</div>

                <div class="label">Estatus Mercado Pago</div>
                <div class="value">{{ $cotizacion->payment_status ?? 'No disponible' }}</div>

                <div class="label">Referencia externa</div>
                <div class="value">{{ $cotizacion->payment_external_reference ?? 'No disponible' }}</div>
            </div>

            <div class="card">
                <h2>Contenido</h2>

                <div class="label">Contenido</div>
                <div class="value">{{ $cotizacion->contenido ?? '-' }}</div>

                <div class="label">Valor declarado</div>
                <div class="value">${{ number_format($cotizacion->valor_declarado ?? 0, 2) }}</div>

                <div class="label">Referencia</div>
                <div class="value">{{ $cotizacion->referencia ?? '-' }}</div>
            </div>
        </div>

        <div style="margin-top:24px">
            <a href="{{ route('b2c.mis-envios') }}" class="btn primary">Volver a Mis envíos</a>
        </div>
    </main>
</div>

</body>
</html>