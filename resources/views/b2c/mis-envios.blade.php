<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis envíos - ZIGO</title>
    <link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">
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
        .actions-wrap{position:relative;display:inline-block}
        .actions-btn{background:#ea580c;color:white;border:none;border-radius:8px;padding:8px 12px;font-weight:900;cursor:pointer;font-size:12px}
        .actions-menu{display:none;position:absolute;right:0;top:34px;background:white;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.15);z-index:20;min-width:170px;padding:8px}
        .actions-wrap:hover .actions-menu{display:block}
        .actions-menu a,.actions-menu button{display:block;width:100%;background:white;border:none;text-align:left;padding:9px 10px;font-size:13px;color:#111827;text-decoration:none;cursor:pointer;border-radius:8px}
        .actions-menu a:hover,.actions-menu button:hover{background:#f1f5f9}
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
                <table class="shipments-table">
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
                                <td data-label="Cotización">{{ $envio->id }}</td>
                                <td data-label="Mensajería">
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
                                <td data-label="Servicio">{{ $envio->servicio ?? '-' }}</td>
                                <td data-label="Origen">
                                    {{ $envio->cp_origen ?? '-' }}
                                    @if($envio->colonia_origen) - {{ $envio->colonia_origen }} @endif
                                    @if($envio->ciudad_origen) - {{ $envio->ciudad_origen }} @endif
                                    @if($envio->estado_origen) - {{ $envio->estado_origen }} @endif
                                </td>

                                <td data-label="Destino">
                                    {{ $envio->cp_destino ?? '-' }}
                                    @if($envio->colonia_destino) - {{ $envio->colonia_destino }} @endif
                                    @if($envio->ciudad_destino) - {{ $envio->ciudad_destino }} @endif
                                    @if($envio->estado_destino) - {{ $envio->estado_destino }} @endif
                                </td>
                                <td data-label="Precio">${{ number_format($envio->precio ?? 0, 2) }}</td>
                                <td data-label="Estatus pago">{{ $envio->payment_status_label }}</td>
                                <td data-label="Estado guía">{{ $envio->guia_estatus_label }}</td>
                                <td data-label="Tracking">{{ $envio->tracking_number ?? '-' }}</td>
                                <td data-label="Acciones">
                                    @php
                                        $estado = strtoupper(
                                            (string) ($envio->estatus ?? '')
                                        );

                                        $estadoGuia = strtoupper(
                                            (string) (
                                                $envio->guia_estatus
                                                ?? 'SIN_GUIA'
                                            )
                                        );

                                        $estadoPago = strtolower(
                                            (string) (
                                                $envio->payment_status
                                                ?? ''
                                            )
                                        );

                                        $estaPagada =
                                            in_array(
                                                $estado,
                                                [
                                                    'PAGADA',
                                                    'GUIA_GENERADA',
                                                ],
                                                true
                                            )
                                            ||
                                            in_array(
                                                $estadoPago,
                                                [
                                                    'approved',
                                                    'saldo_prepago',
                                                ],
                                                true
                                            );

                                        $tieneGuia =
                                            !empty($envio->guia_id)
                                            || !empty($envio->tracking_number)
                                            || !empty($envio->documento)
                                            || $estadoGuia === 'GENERADA';

                                        $tieneDirecciones =
                                            !empty($envio->remitente_nombre)
                                            && !empty($envio->remitente_direccion)
                                            && !empty($envio->destinatario_nombre)
                                            && !empty($envio->destinatario_direccion);

                                        $errorProveedor =
                                            in_array(
                                                $estadoGuia,
                                                [
                                                    'ERROR_PROVEEDOR',
                                                    'ERROR_GENERACION_GUIA',
                                                ],
                                                true
                                            );

                                        $errorPeso =
                                            $estadoGuia ===
                                            'ERROR_VALIDACION_PESO';

                                        $generandoGuia =
                                            $estadoGuia === 'GENERANDO';

                                        $puedeEliminar =
                                            !$estaPagada
                                            && !$tieneGuia;
                                    @endphp

                                    <div class="actions-wrap">
                                        <button
                                            type="button"
                                            class="actions-btn"
                                        >
                                            Acción ▾
                                        </button>

                                        <div class="actions-menu">
                                            <a
                                                href="{{ route(
                                                    'b2c.envios.detalle',
                                                    $envio->id
                                                ) }}"
                                            >
                                                Ver detalle
                                            </a>

                                            @if(
                                                !$estaPagada
                                                && !$tieneGuia
                                            )
                                                <a
                                                    href="{{ route(
                                                        'b2c.envios.retomar',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    Retomar proceso
                                                </a>
                                            @endif

                                            @if($envio->canEditShipment())
                                                <a
                                                    href="{{ route(
                                                        'b2c.envios.editar',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    Editar direcciones
                                                </a>
                                            @endif

                                            {{-- Captura de direcciones terminada --}}
                                            @if(
                                                $estado ===
                                                'DIRECCION_CAPTURADA'
                                            )
                                                <a
                                                    href="{{ route(
                                                        'b2c.paquete',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    Continuar con paquete
                                                </a>
                                            @endif

                                            {{-- Paquete capturado sin servicio --}}
                                            @if(
                                                $estado ===
                                                'PAQUETE_CAPTURADO'
                                            )
                                                <a
                                                    href="{{ route(
                                                        'b2c.opciones',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    Seleccionar servicio
                                                </a>
                                            @endif

                                            {{-- Servicio seleccionado sin pago --}}
                                            @if(
                                                $estado === 'SELECCIONADA'
                                                && !$estaPagada
                                            )
                                                @if($tieneDirecciones)
                                                    <a
                                                        href="{{ route(
                                                            'b2c.confirmar',
                                                            $envio->id
                                                        ) }}"
                                                    >
                                                        Confirmar y pagar
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ route(
                                                            'b2c.checkout',
                                                            $envio->id
                                                        ) }}"
                                                    >
                                                        Completar datos y pagar
                                                    </a>
                                                @endif
                                            @endif

                                            {{-- Pago realizado sin guía --}}
                                            @if(
                                                $estaPagada
                                                && !$tieneGuia
                                                && !$errorPeso
                                                && !$generandoGuia
                                            )
                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'b2c.guia.generar',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    @csrf

                                                    <button type="submit">
                                                        {{ $errorProveedor
                                                            ? 'Reintentar generación'
                                                            : 'Generar guía'
                                                        }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if($generandoGuia)
                                                <span>
                                                    Generación en curso
                                                </span>
                                            @endif

                                            {{-- Errores que requieren revisión --}}
                                            @if(
                                                $estaPagada
                                                && !$tieneGuia
                                                && (
                                                    $errorProveedor
                                                    || $errorPeso
                                                )
                                            )
                                                <a
                                                    href="{{ route(
                                                        'b2c.incidencias'
                                                    ) }}"
                                                >
                                                    Reportar incidencia
                                                </a>
                                            @endif

                                            {{-- Duplicar envío --}}
                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'b2c.envios.duplicar',
                                                    $envio->id
                                                ) }}"
                                            >
                                                @csrf

                                                <button type="submit">
                                                    Duplicar envío
                                                </button>
                                            </form>

                                            {{-- Acciones de una guía generada --}}
                                            @if(!empty($envio->tracking_number))
                                                <a
                                                    href="{{ url(
                                                        '/rastreo?tracking_number='
                                                        . urlencode(
                                                            $envio->tracking_number
                                                        )
                                                    ) }}"
                                                >
                                                    Rastrear
                                                </a>
                                            @endif

                                            @if(!empty($envio->documento))
                                                <a
                                                    href="{{ strtolower((string) $envio->provider) === 'xperta' ? route('b2c.guia.etiqueta', $envio) : asset('storage/' . basename($envio->documento)) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    Descargar guía
                                                </a>
                                            @endif

                                            {{-- Nunca eliminar pagos o guías --}}
                                            @if($puedeEliminar)
                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'b2c.envios.eliminar',
                                                        $envio->id
                                                    ) }}"
                                                >
                                                    @csrf

                                                    <button
                                                        type="submit"
                                                        onclick="
                                                            return confirm(
                                                                '¿Eliminar esta cotización?'
                                                            )
                                                        "
                                                    >
                                                        Eliminar cotización
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
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
