<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de envío - ZIGO</title>
    <link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px;text-align:center}
        .zigo-img{width:180px;max-width:100%;display:block;margin:0 auto}
        .zigo-tagline{margin-top:8px;font-size:10px;letter-spacing:2px;color:#dce7f7;text-transform:uppercase;font-weight:600;line-height:1.5}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
        .card{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        h2{margin-top:0;margin-bottom:18px;font-size:24px}
        .label{color:#64748b;font-size:12px;font-weight:800;margin-bottom:4px}
        .value{font-size:15px;font-weight:800;margin-bottom:12px;line-height:1.35;word-break:break-word}
        .btn{display:inline-block;padding:11px 15px;border-radius:10px;text-decoration:none;font-weight:900;margin-right:8px}
        .primary{background:#2563eb;color:white}
        .secondary{background:#475569;color:white}
        .success{background:#16a34a;color:white}
        .warning{background:#f59e0b;color:white;border:none;cursor:pointer}
        @media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{display:none}.grid{grid-template-columns:1fr}.content{padding:24px}}

        /* Detalle compacto detalle de envio */
        .detail-grid {
            gap: 14px;
            align-items: start;
        }

        .detail-grid .card {
            min-height: 0 !important;
            height: auto !important;
            padding: 16px 18px;
        }

        .detail-grid .card h2 {
            margin: 0 0 12px;
            font-size: 19px;
        }

        .detail-grid .label {
            margin-top: 8px;
            font-size: 11px;
        }

        .detail-grid .value {
            margin-top: 2px;
            font-size: 14px;
            line-height: 1.35;
        }

        .detail-grid .btn {
            margin-top: 12px;
        }

        .detail-alert {
            margin-bottom: 16px;
            padding: 13px 15px;
            border-radius: 11px;
            font-weight: 800;
        }

        .detail-alert.success {
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            color: #166534;
        }

        .detail-alert.error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .invoice-actions {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        .invoice-note {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .invoice-status {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
        }

        .invoice-status.requested {
            background: #fef3c7;
            color: #92400e;
        }

        .invoice-status.processing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .invoice-status.invoiced {
            background: #dcfce7;
            color: #166534;
        }

        .invoice-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .invoice-status.cancelled {
            background: #e2e8f0;
            color: #334155;
        }

        .invoice-meta {
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .invoice-downloads {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .invoice-downloads .btn {
            margin: 0;
            padding: 8px 10px;
            font-size: 12px;
        }

        .invoice-disabled {
            padding: 11px 13px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
        }

        .invoice-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .62);
        }

        .invoice-modal.open {
            display: flex;
        }

        .invoice-modal-card {
            width: min(620px, 100%);
            max-height: 90vh;
            overflow: auto;
            padding: 24px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .28);
        }

        .invoice-modal-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 16px;
        }

        .invoice-modal-header h2 {
            margin: 0 0 5px;
        }

        .invoice-modal-header p {
            margin: 0;
            color: #64748b;
        }

        .invoice-close {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: #f1f5f9;
            color: #475569;
            font-size: 23px;
            cursor: pointer;
        }

        .invoice-fiscal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .invoice-fiscal-item {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
        }

        .invoice-fiscal-item span {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
        }

        .invoice-fiscal-item strong {
            display: block;
            font-size: 13px;
            line-height: 1.4;
        }

        .invoice-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        @media (max-width: 700px) {
            .invoice-fiscal-grid {
                grid-template-columns: 1fr;
            }

            .invoice-modal-actions {
                flex-direction: column;
            }
        }
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
        <div class="title">Detalle del envío #{{ $cotizacion->id }}</div>
        <div class="subtitle">Consulta la información completa de tu envío.</div>

        @if(session('success'))
            <div class="detail-alert success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="detail-alert error">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid detail-grid">
            <div class="card">
                <h2>Resumen</h2>

                <div class="label">Paquetería</div>
                <div class="value">{{ $cotizacion->logistico ?? '-' }}</div>

                <div class="label">Servicio</div>
                <div class="value">{{ $cotizacion->servicio ?? '-' }}</div>

                @if(array_key_exists('base', (array) ($quoteMetadata['commercial_breakdown'] ?? [])))
                    @include('b2c.partials.commercial-breakdown', ['commercialSnapshot' => $quoteMetadata])
                @else
                    <div class="label">Precio</div>
                    <div class="value">${{ number_format($cotizacion->precio ?? 0, 2) }} MXN</div>
                @endif

                <div class="label">Estatus</div>
                <div class="value">
                    {{ $cotizacion->estatus_label }}
                </div>

                <div class="label">Estado guía</div>
                <div class="value">
                    {{ $cotizacion->guia_estatus_label }}
                </div>

                @if(
                    !$cotizacion->hasAccreditedPayment()
                    && !$cotizacion->hasGeneratedGuide()
                )
                    <a
                        href="{{ route(
                            'b2c.envios.retomar',
                            $cotizacion->id
                        ) }}"
                        class="btn primary"
                    >
                        Retomar proceso
                    </a>
                @endif

                @if($cotizacion->canEditShipment())
                    <a
                        href="{{ route(
                            'b2c.envios.editar',
                            $cotizacion->id
                        ) }}"
                        class="btn secondary"
                    >
                        Editar direcciones
                    </a>
                @endif
            </div>

            <div class="card">
                <h2>Guía</h2>

                <div class="label">Tracking</div>
                <div class="value">{{ $cotizacion->tracking_number ?? 'Sin tracking' }}</div>

                <div class="label">Fecha de generación</div>
                <div class="value">{{ $cotizacion->guia_generated_at ? $cotizacion->guia_generated_at->format('d/m/Y H:i') : 'Pendiente' }}</div>

                <div class="label">Documento</div>
                <div class="value">{{ $cotizacion->documento ? 'Etiqueta disponible' : 'Sin documento' }}</div>

                @if($cotizacion->documento)
                    <a href="{{ strtolower((string) $cotizacion->provider) === 'xperta' ? route('b2c.guia.etiqueta', $cotizacion) : asset('storage/' . basename($cotizacion->documento)) }}" target="_blank" rel="noopener noreferrer" class="btn success">
                        Descargar guía
                    </a>
                @endif

                @php
                    $guiaGenerando = strtoupper(
                        (string) $cotizacion->guia_estatus
                    ) === 'GENERANDO';
                @endphp

                @if(
                    in_array(
                        $cotizacion->estatus,
                        ['PAGADA', 'ERROR_GENERACION_GUIA'],
                        true
                    )
                    && !$cotizacion->tracking_number
                    && !$guiaGenerando
                )
                    <form method="POST" action="{{ route('b2c.guia.generar', $cotizacion->id) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn warning">
                            {{ $cotizacion->estatus === 'ERROR_GENERACION_GUIA'
                                ? 'Reintentar generación'
                                : 'Generar guía'
                            }}
                        </button>
                    </form>
                @elseif($guiaGenerando)
                    <span class="invoice-disabled">
                        Generación de guía en curso
                    </span>
                @endif

                @if((int) $cotizacion->guia_generation_attempts > 0)
                    <div class="label">Intentos de generación</div>
                    <div class="value">
                        {{ $cotizacion->guia_generation_attempts }}
                    </div>
                @endif

                @if($cotizacion->guia_last_error_message && !$cotizacion->hasGeneratedGuide())
                    <div class="label">Último resultado</div>
                    <div class="value">{{ $cotizacion->guia_last_error_message }}</div>
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

                <div class="label">
                    ID de pago
                </div>

                <div class="value">
                    {{
                        $cotizacion->payment_id
                        ?: 'No disponible'
                    }}
                </div>

                <div class="label">
                    Estado del pago
                </div>

                <div class="value">
                    {{
                        $cotizacion
                            ->payment_status_label
                    }}
                </div>

                <div class="label">
                    Referencia externa
                </div>

                <div class="value">
                    {{
                        $cotizacion
                            ->payment_external_reference
                        ?: 'No disponible'
                    }}
                </div>

                <div class="invoice-actions">
                    @if($invoiceRequest)
                        @php
                            $invoiceStatusClass = match($invoiceRequest->status) {
                                'SOLICITADA' => 'requested',
                                'EN_PROCESO' => 'processing',
                                'FACTURADA' => 'invoiced',
                                'RECHAZADA' => 'rejected',
                                'CANCELADA' => 'cancelled',
                                default => 'cancelled',
                            };
                        @endphp

                        <span class="invoice-status {{ $invoiceStatusClass }}">
                            {{ $invoiceRequest->status_label }}
                        </span>

                        @switch($invoiceRequest->status)
                            @case('SOLICITADA')
                                <p class="invoice-note">
                                    Factura solicitada el
                                    {{ optional($invoiceRequest->solicitada_at)->format('d/m/Y H:i') }}.
                                </p>
                                @break

                            @case('EN_PROCESO')
                                <p class="invoice-note">
                                    La factura está en preparación por el área administrativa.
                                </p>
                                @break

                            @case('FACTURADA')
                                <div class="invoice-meta">
                                    <strong>Factura emitida</strong><br>
                                    UUID: {{ $invoiceRequest->cfdi_uuid ?? '-' }}<br>
                                    Fecha de facturación:
                                    {{ optional($invoiceRequest->facturada_at)->format('d/m/Y H:i') ?? '-' }}
                                </div>

                                @if($invoiceRequest->puedeDescargarDocumentos())
                                    <div class="invoice-downloads">
                                        <a class="btn primary"
                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'pdf']) }}">
                                            Descargar PDF
                                        </a>
                                        <a class="btn secondary"
                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'xml']) }}">
                                            Descargar XML
                                        </a>
                                        <a class="btn success"
                                           href="{{ route('b2c.facturas.documentos.download', [$invoiceRequest, 'zip']) }}">
                                            Descargar ZIP
                                        </a>
                                    </div>
                                @else
                                    <p class="invoice-note">
                                        Los documentos todavía no están disponibles para descarga.
                                    </p>
                                @endif
                                @break

                            @case('RECHAZADA')
                                <p class="invoice-note">
                                    {{ $invoiceRequest->rejection_reason ?: 'La solicitud fue rechazada. Consulta a soporte para conocer el motivo.' }}
                                </p>
                                <a class="btn secondary" href="{{ route('b2c.configuracion') }}">Corregir información fiscal</a>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('b2c.invoice.resubmit',$invoiceRequest) }}">@csrf<label>Constancia fiscal corregida (opcional)<input type="file" name="constancia" accept="application/pdf,image/jpeg,image/png"></label><button class="btn primary" type="submit">Reenviar documentación</button></form>
                                @break

                            @case('CANCELADA')
                                <p class="invoice-note">
                                    {{ $invoiceRequest->cancellation_reason ?: 'La solicitud de factura fue cancelada.' }}
                                </p>
                                @break
                        @endswitch

                    @elseif(!$canInvoice)
                        <div class="invoice-disabled">
                            La factura estará disponible
                            cuando el pago sea aprobado.
                        </div>

                    @elseif(
                        !$fiscalProfile
                        || !$fiscalProfile->estaCompleto()
                    )
                        <a
                            href="{{ route(
                                'b2c.configuracion'
                            ) }}#facturacion"
                            class="btn warning"
                        >
                            Registrar datos fiscales
                        </a>

                    @else
                        <button
                            id="openInvoiceModal"
                            type="button"
                            class="btn warning"
                        >
                            Facturar este pago
                        </button>
                    @endif
                </div>
            </div>

            <div class="card">
                <h2>Contenido</h2>

                <div class="label">Contenido</div>
                <div class="value">{{ $cotizacion->contenido ?? '-' }}</div>

                <div class="label">Valor declarado</div>
                <div class="value">${{ number_format($cotizacion->valor_declarado ?? 0, 2) }}</div>

                <div class="label">Referencia</div>
                <div class="value">
                    ZIGO-{{ $cotizacion->id }}
                </div>
            </div>
        </div>

        <div style="margin-top:24px">
            @if(request('origen') === 'pagos')
                <a
                    href="{{ route('b2c.mis-pagos') }}"
                    class="btn primary"
                >
                    Volver a Mis pagos
                </a>
            @else
                <a
                    href="{{ route('b2c.mis-envios') }}"
                    class="btn primary"
                >
                    Volver a Mis envíos
                </a>
            @endif
        </div>
    </main>
</div>

@if(
    $canInvoice
    && $fiscalProfile
    && $fiscalProfile->estaCompleto()
    && !$invoiceRequest
)
    <div
        id="invoiceModal"
        class="invoice-modal"
        aria-hidden="true"
    >
        <div class="invoice-modal-card">
            <div class="invoice-modal-header">
                <div>
                    <h2>Solicitar factura</h2>

                    <p>
                        Confirma que los datos fiscales
                        sean correctos.
                    </p>
                </div>

                <button
                    id="closeInvoiceModal"
                    type="button"
                    class="invoice-close"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>

            <div class="invoice-fiscal-grid">
                <div class="invoice-fiscal-item">
                    <span>RFC</span>

                    <strong>
                        {{ $fiscalProfile->rfc }}
                    </strong>
                </div>

                <div class="invoice-fiscal-item">
                    <span>Código postal fiscal</span>

                    <strong>
                        {{
                            $fiscalProfile
                                ->codigo_postal_fiscal
                        }}
                    </strong>
                </div>

                <div class="invoice-fiscal-item">
                    <span>Razón social</span>

                    <strong>
                        {{
                            $fiscalProfile
                                ->razon_social
                        }}
                    </strong>
                </div>

                <div class="invoice-fiscal-item">
                    <span>Total</span>

                    <strong>
                        ${{
                            number_format(
                                $cotizacion->precio
                                ?? 0,
                                2
                            )
                        }} MXN
                    </strong>
                </div>

                <div class="invoice-fiscal-item">
                    <span>Régimen fiscal</span>

                    <strong>
                        {{
                            $fiscalProfile
                                ->regimen_fiscal
                        }}
                        -
                        {{
                            $regimenesFiscales[
                                $fiscalProfile
                                    ->regimen_fiscal
                            ] ?? ''
                        }}
                    </strong>
                </div>

                <div class="invoice-fiscal-item">
                    <span>Uso de CFDI</span>

                    <strong>
                        {{
                            $fiscalProfile
                                ->uso_cfdi
                        }}
                        -
                        {{
                            $usosCfdi[
                                $fiscalProfile
                                    ->uso_cfdi
                            ] ?? ''
                        }}
                    </strong>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'b2c.envios.facturar',
                    $cotizacion->id
                ) }}"
            >
                @csrf

                <div class="invoice-modal-actions">
                    <a
                        href="{{ route(
                            'b2c.configuracion'
                        ) }}#facturacion"
                        class="btn warning"
                    >
                        Editar datos
                    </a>

                    <button
                        id="cancelInvoiceModal"
                        type="button"
                        class="btn"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn primary"
                    >
                        Confirmar solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {
                const modal =
                    document.getElementById(
                        'invoiceModal'
                    );

                const openButton =
                    document.getElementById(
                        'openInvoiceModal'
                    );

                const closeButton =
                    document.getElementById(
                        'closeInvoiceModal'
                    );

                const cancelButton =
                    document.getElementById(
                        'cancelInvoiceModal'
                    );

                if (
                    !modal
                    || !openButton
                    || !closeButton
                    || !cancelButton
                ) {
                    return;
                }

                const openModal = function () {
                    modal.classList.add('open');

                    modal.setAttribute(
                        'aria-hidden',
                        'false'
                    );
                };

                const closeModal = function () {
                    modal.classList.remove('open');

                    modal.setAttribute(
                        'aria-hidden',
                        'true'
                    );
                };

                openButton.addEventListener(
                    'click',
                    openModal
                );

                closeButton.addEventListener(
                    'click',
                    closeModal
                );

                cancelButton.addEventListener(
                    'click',
                    closeModal
                );

                modal.addEventListener(
                    'click',
                    function (event) {
                        if (event.target === modal) {
                            closeModal();
                        }
                    }
                );

                document.addEventListener(
                    'keydown',
                    function (event) {
                        if (
                            event.key === 'Escape'
                        ) {
                            closeModal();
                        }
                    }
                );
            }
        );
    </script>
@endif
</body>
</html>
