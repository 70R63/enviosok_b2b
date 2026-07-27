<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis pagos - ZIGO</title>
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
        .btn{display:inline-block;padding:8px 10px;border-radius:8px;text-decoration:none;font-weight:800;font-size:12px}
        .primary{background:#2563eb;color:white}
        .secondary{background:#475569;color:white}
        .success{background:#16a34a;color:white}
        .empty{background:#eff6ff;color:#1e40af;padding:16px;border-radius:12px;font-weight:700}
        .invoice-cell{min-width:190px}
        .invoice-badge{display:inline-block;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:900}
        .invoice-badge.requested{background:#fef3c7;color:#92400e}
        .invoice-badge.processing{background:#dbeafe;color:#1d4ed8}
        .invoice-badge.invoiced{background:#dcfce7;color:#166534}
        .invoice-badge.rejected{background:#fee2e2;color:#991b1b}
        .invoice-badge.cancelled{background:#e2e8f0;color:#334155}
        .invoice-note{margin-top:6px;color:#64748b;font-size:11px;line-height:1.35}
        .invoice-downloads{display:flex;gap:5px;flex-wrap:wrap;margin-top:8px}
        .invoice-downloads .btn{padding:6px 8px;font-size:10px}
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
        <div class="title">Mis pagos</div>
        <div class="subtitle">Consulta tus pagos realizados con Mercado Pago.</div>

        <div class="card">
            @if($pagos->isEmpty())
                <div class="empty">Aún no tienes pagos registrados.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Cotización</th>
                            <th>Fecha</th>
                            <th>Mensajería</th>
                            <th>Servicio</th>
                            <th>Total</th>
                            <th>ID pago</th>
                            <th>Estado del pago</th>
                            <th>Estado del envío</th>
                            <th>Factura</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pagos as $pago)
                            @php
                                $invoiceRequest = $pago->invoiceRequest;
                                $invoiceBadgeClass = match($invoiceRequest?->status) {
                                    'SOLICITADA' => 'requested',
                                    'EN_PROCESO' => 'processing',
                                    'FACTURADA' => 'invoiced',
                                    'RECHAZADA' => 'rejected',
                                    'CANCELADA' => 'cancelled',
                                    default => 'cancelled',
                                };
                            @endphp

                            <tr>
                                <td>#{{ $pago->id }}</td>
                                <td>{{ optional($pago->updated_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $pago->logistico ?? '-' }}</td>
                                <td>{{ $pago->servicio ?? '-' }}</td>
                                <td>${{ number_format($pago->precio ?? 0, 2) }} MXN</td>
                                <td>{{ $pago->payment_id ?? '-' }}</td>
                                <td>{{ $pago->payment_status_label }}</td>
                                <td>{{ $pago->estatus_label }}</td>
                                <td class="invoice-cell">
                                    @if(!$invoiceRequest)
                                        <span class="invoice-note">Sin solicitud</span>
                                    @else
                                        <span class="invoice-badge {{ $invoiceBadgeClass }}">
                                            {{ $invoiceRequest->status_label }}
                                        </span>

                                        @switch($invoiceRequest->status)
                                            @case('SOLICITADA')
                                                <div class="invoice-note">
                                                    Factura solicitada.
                                                </div>
                                                @break

                                            @case('EN_PROCESO')
                                                <div class="invoice-note">
                                                    Factura en preparación.
                                                </div>
                                                @break

                                            @case('FACTURADA')
                                                <div class="invoice-note">
                                                    UUID: {{ $invoiceRequest->cfdi_uuid ?? '-' }}
                                                </div>

                                                @if($invoiceRequest->puedeDescargarDocumentos())
                                                    <div class="invoice-downloads">
                                                        <a class="btn primary"
                                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'pdf']) }}">
                                                            PDF
                                                        </a>
                                                        <a class="btn secondary"
                                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'xml']) }}">
                                                            XML
                                                        </a>
                                                        <a class="btn success"
                                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'zip']) }}">
                                                            ZIP
                                                        </a>
                                                    </div>
                                                @endif
                                                @break

                                            @case('RECHAZADA')
                                                <div class="invoice-note">
                                                    {{ $invoiceRequest->rejection_reason ?: 'Consulta el detalle para conocer el motivo.' }}
                                                </div>
                                                @break

                                            @case('CANCELADA')
                                                <div class="invoice-note">
                                                    {{ $invoiceRequest->cancellation_reason ?: 'La solicitud fue cancelada.' }}
                                                </div>
                                                @break
                                        @endswitch
                                    @endif
                                </td>
                                <td>
                                    <a
                                        href="{{ route(
                                            'b2c.envios.detalle',
                                            [
                                                'cotizacion' => $pago->id,
                                                'origen' => 'pagos',
                                            ]
                                        ) }}"
                                        class="btn primary"
                                    >
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</div>

</body>
</html>