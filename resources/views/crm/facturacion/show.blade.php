@extends('crm.layout')

@section('title', 'Solicitud de facturación #' . $invoiceRequest->id . ' | CRM ZIGO')

@section('content')
    @php
        $user = $invoiceRequest->user;
        $clientName = collect([
            $user?->name,
            $user?->apellido_paterno,
            $user?->apellido_materno,
        ])->filter()->implode(' ');

        $paymentStatusLabel = match(strtolower((string) $invoiceRequest->payment_status)) {
            'approved' => 'Pago aprobado',
            'saldo_prepago' => 'Pagado con saldo',
            'pending', 'in_process' => 'Pago pendiente',
            'rejected' => 'Pago rechazado',
            'cancelled', 'cancelled_by_user' => 'Pago cancelado',
            default => $invoiceRequest->payment_status ?: 'Sin estado de pago',
        };

        $regimenLabel = $regimenes[$invoiceRequest->regimen_fiscal] ?? 'Descripción no disponible';
        $usoCfdiLabel = $usosCfdi[$invoiceRequest->uso_cfdi] ?? 'Descripción no disponible';
    @endphp

    <div class="page-header">
        <div>
            <div class="title">Solicitud de facturación #{{ $invoiceRequest->id }}</div>
            <div class="subtitle">
                Revisión administrativa de la solicitud CFDI.
            </div>
        </div>

        <a class="btn btn-gray"
           href="{{ route('crm.facturacion.index') }}">
            Volver al listado
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif


    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="detail-grid">
        <section class="card">
            <div class="section-header">
                <h2>Pago</h2>
                <span class="badge {{ $invoiceRequest->status_badge_class }}">
                    {{ $invoiceRequest->status_label }}
                </span>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span>Cotización</span>
                    <strong>#{{ $invoiceRequest->cotizacion_id }}</strong>
                </div>

                <div class="info-item">
                    <span>Cliente</span>
                    <strong>{{ $clientName !== '' ? $clientName : 'Usuario #' . $invoiceRequest->user_id }}</strong>
                    <small>{{ $user?->email ?? '-' }}</small>
                </div>

                <div class="info-item">
                    <span>Referencia de pago</span>
                    <strong>{{ $invoiceRequest->payment_reference ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Método de pago</span>
                    <strong>{{ $invoiceRequest->payment_method ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Estado del pago</span>
                    <strong>{{ $paymentStatusLabel }}</strong>
                </div>

                <div class="info-item">
                    <span>Monto</span>
                    <strong>${{ number_format((float) $invoiceRequest->monto, 2) }}</strong>
                </div>

                <div class="info-item">
                    <span>Fecha de solicitud</span>
                    <strong>{{ optional($invoiceRequest->solicitada_at)->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Estado del envío</span>
                    <strong>{{ $invoiceRequest->cotizacion?->estatus_label ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-header">
                <h2>Datos fiscales congelados</h2>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span>RFC</span>
                    <strong>{{ $invoiceRequest->rfc }}</strong>
                </div>

                <div class="info-item">
                    <span>Razón social</span>
                    <strong>{{ $invoiceRequest->razon_social }}</strong>
                </div>

                <div class="info-item">
                    <span>Código postal fiscal</span>
                    <strong>{{ $invoiceRequest->codigo_postal_fiscal }}</strong>
                </div>

                <div class="info-item">
                    <span>Régimen fiscal</span>
                    <strong>{{ $invoiceRequest->regimen_fiscal }}</strong>
                    <small>{{ $regimenLabel }}</small>
                </div>

                <div class="info-item">
                    <span>Uso de CFDI</span>
                    <strong>{{ $invoiceRequest->uso_cfdi }}</strong>
                    <small>{{ $usoCfdiLabel }}</small>
                </div>

                <div class="info-item">
                    <span>Correo de facturación</span>
                    <strong>{{ $invoiceRequest->email_facturacion }}</strong>
                </div>

                <div class="info-item info-item-wide">
                    <span>Dirección fiscal</span>
                    <strong>{{ $invoiceRequest->direccion_fiscal ?: 'No capturada' }}</strong>
                </div>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="section-header">
            <h2>Gestión</h2>
        </div>

        @php
            $manager = $invoiceRequest->managedBy;
            $managerName = collect([
                $manager?->name,
                $manager?->apellido_paterno,
                $manager?->apellido_materno,
            ])->filter()->implode(' ');
        @endphp

        <div class="info-grid management-meta">
            <div class="info-item">
                <span>Responsable administrativo</span>
                <strong>
                    {{ $managerName !== '' ? $managerName : 'Sin responsable asignado' }}
                </strong>
                @if($manager?->email)
                    <small>{{ $manager->email }}</small>
                @endif
            </div>

            <div class="info-item">
                <span>Fecha de atención</span>
                <strong>{{ optional($invoiceRequest->attended_at)->format('d/m/Y H:i:s') ?? 'Sin iniciar' }}</strong>
            </div>
        </div>

        @if($invoiceRequest->puedeIniciarAtencion())
            <div class="alert alert-info">
                La solicitud todavía no ha sido tomada por el área administrativa.
            </div>

            <form method="POST"
                  action="{{ route('crm.facturacion.iniciar-atencion', $invoiceRequest) }}"
                  class="management-primary-action">
                @csrf
                <button class="btn"
                        type="submit"
                        onclick="return confirm('¿Marcar esta solicitud como En proceso?')">
                    Iniciar atención
                </button>
            </form>
        @elseif($invoiceRequest->status === 'EN_PROCESO')
            <div class="alert alert-info">
                La solicitud está en proceso y ya puede continuar con la generación manual del CFDI.
            </div>
        @elseif($invoiceRequest->status === 'RECHAZADA')
            <div class="alert alert-error">
                <strong>Motivo del rechazo:</strong>
                {{ $invoiceRequest->rejection_reason ?: 'No se registró un motivo.' }}
                <div class="resolution-date">
                    Fecha: {{ optional($invoiceRequest->rejected_at)->format('d/m/Y H:i:s') ?? '-' }}
                </div>
            </div>
        @elseif($invoiceRequest->status === 'CANCELADA')
            <div class="alert alert-muted">
                <strong>Motivo de cancelación:</strong>
                {{ $invoiceRequest->cancellation_reason ?: 'No se registró un motivo.' }}
                <div class="resolution-date">
                    Fecha: {{ optional($invoiceRequest->cancelled_at)->format('d/m/Y H:i:s') ?? '-' }}
                </div>
            </div>
        @else
            <div class="alert alert-info">
                La solicitud se encuentra en estado {{ $invoiceRequest->status_label }}.
            </div>
        @endif

        <div class="management-grid">
            <div class="management-panel">
                <h3>Notas internas</h3>
                <p>
                    Solo son visibles para el personal del CRM. No aparecen en el portal del cliente.
                </p>

                <form method="POST"
                      action="{{ route('crm.facturacion.gestion.update', $invoiceRequest) }}">
                    @csrf
                    <textarea name="internal_notes"
                              rows="6"
                              maxlength="4000"
                              placeholder="Registra seguimiento, proveedor utilizado o referencias internas.">{{ old('internal_notes', $invoiceRequest->internal_notes) }}</textarea>

                    <button class="btn btn-gray management-submit"
                            type="submit">
                        Guardar notas
                    </button>
                </form>
            </div>

            @if($invoiceRequest->puedeRechazarse())
                <div class="management-panel management-panel-danger">
                    <h3>Rechazar solicitud</h3>
                    <p>
                        Utiliza esta opción cuando los datos o condiciones impiden emitir el CFDI.
                        El motivo será visible para el cliente.
                    </p>

                    <form method="POST"
                          action="{{ route('crm.facturacion.rechazar', $invoiceRequest) }}">
                        @csrf
                        <select name="rejection_category">
                            <option value="RFC_INVALIDO">RFC inválido</option><option value="CONSTANCIA_ILEGIBLE">Constancia fiscal ilegible</option><option value="DATOS_INCOMPLETOS">Datos fiscales incompletos</option><option value="REGIMEN_INCORRECTO">Régimen fiscal incorrecto</option><option value="USO_CFDI_INCORRECTO">Uso CFDI incorrecto</option><option value="OTRO">Otro</option>
                        </select>
                        <textarea name="rejection_reason"
                                  rows="6"
                                  minlength="10"
                                  maxlength="2000"
                                  required
                                  placeholder="Describe claramente por qué no puede emitirse la factura.">{{ old('rejection_reason') }}</textarea>

                        <button class="btn btn-danger management-submit"
                                type="submit"
                                onclick="return confirm('¿Rechazar esta solicitud? Esta acción cerrará su atención.')">
                            Rechazar solicitud
                        </button>
                    </form>
                </div>
            @endif

            @if($invoiceRequest->puedeCancelarse())
                <div class="management-panel management-panel-warning">
                    <h3>Cancelar solicitud</h3>
                    <p>
                        Utiliza esta opción para una cancelación administrativa o solicitada por el cliente.
                        El motivo será visible en el portal.
                    </p>

                    <form method="POST"
                          action="{{ route('crm.facturacion.cancelar', $invoiceRequest) }}">
                        @csrf
                        <textarea name="cancellation_reason"
                                  rows="6"
                                  minlength="10"
                                  maxlength="2000"
                                  required
                                  placeholder="Indica la causa de la cancelación.">{{ old('cancellation_reason') }}</textarea>

                        <button class="btn btn-warning management-submit"
                                type="submit"
                                onclick="return confirm('¿Cancelar esta solicitud? Esta acción cerrará su atención.')">
                            Cancelar solicitud
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="section-header">
            <h2>Documentos fiscales</h2>
        </div>

        @if($invoiceRequest->puedeCargarDocumentos())
            <p class="management-copy">
                Carga el ZIP entregado por el proveedor de facturación. Debe contener exactamente
                un PDF y un XML timbrado de CFDI 4.0. El RFC receptor y el total se compararán
                contra esta solicitud antes de guardar los archivos.
            </p>

            @if($invoiceRequest->error_message)
                <div class="alert alert-error">
                    Último rechazo de documentos: {{ $invoiceRequest->error_message }}
                </div>
            @endif

            <form method="POST"
                  enctype="multipart/form-data"
                  action="{{ route('crm.facturacion.documentos.store', $invoiceRequest) }}"
                  class="upload-form">
                @csrf

                <div>
                    <label class="field-label" for="cfdi_zip">
                        ZIP fiscal
                    </label>
                    <input id="cfdi_zip"
                           name="cfdi_zip"
                           type="file"
                           accept=".zip,application/zip,application/x-zip-compressed"
                           required>
                    <div class="field-help">
                        Tamaño máximo: 15 MB. No se permiten carpetas inseguras, ejecutables,
                        archivos adicionales ni ZIP con contraseña.
                    </div>
                </div>

                <button class="btn"
                        type="submit"
                        onclick="return confirm('¿Validar y registrar este CFDI? La solicitud quedará Facturada si el XML es correcto.')">
                    Validar y cargar CFDI
                </button>
            </form>
        @elseif($invoiceRequest->status === 'FACTURADA')
            <div class="alert alert-success">
                Los documentos fueron validados y se encuentran guardados en almacenamiento privado.
            </div>

            <div class="info-grid cfdi-grid">
                <div class="info-item info-item-wide">
                    <span>UUID fiscal</span>
                    <strong>{{ $invoiceRequest->cfdi_uuid ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>RFC emisor</span>
                    <strong>{{ $invoiceRequest->cfdi_rfc_emisor ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>RFC receptor</span>
                    <strong>{{ $invoiceRequest->cfdi_rfc_receptor ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Nombre receptor</span>
                    <strong>{{ $invoiceRequest->cfdi_nombre_receptor ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Total CFDI</span>
                    <strong>
                        {{ $invoiceRequest->cfdi_total !== null ? '$' . number_format((float) $invoiceRequest->cfdi_total, 2) : '-' }}
                    </strong>
                </div>

                <div class="info-item">
                    <span>Fecha de emisión</span>
                    <strong>{{ optional($invoiceRequest->cfdi_fecha_emision)->format('d/m/Y H:i:s') ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Fecha de timbrado</span>
                    <strong>{{ optional($invoiceRequest->cfdi_fecha_timbrado)->format('d/m/Y H:i:s') ?? '-' }}</strong>
                </div>

                <div class="info-item">
                    <span>Fecha registrada en ZIGO</span>
                    <strong>{{ optional($invoiceRequest->facturada_at)->format('d/m/Y H:i:s') ?? '-' }}</strong>
                </div>
            </div>

            @if($invoiceRequest->puedeDescargarDocumentos())
                <div class="document-actions">
                    <a class="btn"
                       href="{{ route('crm.facturacion.documentos.download', [$invoiceRequest, 'pdf']) }}">
                        Descargar PDF
                    </a>

                    <a class="btn btn-gray"
                       href="{{ route('crm.facturacion.documentos.download', [$invoiceRequest, 'xml']) }}">
                        Descargar XML
                    </a>

                    <a class="btn document-zip"
                       href="{{ route('crm.facturacion.documentos.download', [$invoiceRequest, 'zip']) }}">
                        Descargar ZIP
                    </a>
                </div>
            @else
                <div class="alert alert-error documents-note">
                    La solicitud está Facturada, pero las rutas privadas de los documentos no están completas.
                </div>
            @endif
        @else
            <p class="management-copy">
                Primero debe iniciarse la atención de la solicitud para habilitar la carga del CFDI.
            </p>
        @endif
    </section>
@endsection

@push('styles')
    <style>
        .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:24px}
        .page-header .subtitle{margin-bottom:0}
        .detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
        .section-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
        .section-header h2{font-size:21px;margin:0}
        .info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .info-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;min-width:0}
        .info-item-wide{grid-column:1 / -1}
        .info-item span{display:block;color:#64748b;font-size:12px;font-weight:800;margin-bottom:6px;text-transform:uppercase;letter-spacing:.03em}
        .info-item strong{display:block;overflow-wrap:anywhere}
        .info-item small{display:block;color:#64748b;margin-top:5px;line-height:1.35}
        .management-copy{color:#475569;margin:0 0 16px;line-height:1.5}
        .upload-form{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:16px}
        .field-label{display:block;font-weight:900;margin-bottom:8px}
        .field-help{color:#64748b;font-size:13px;line-height:1.4;margin-top:7px}
        .documents-note{margin-top:16px;margin-bottom:0}
        .cfdi-grid{margin-top:16px}
        .document-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px}
        .document-zip{background:#16a34a}
        .management-meta{margin-bottom:16px}
        .management-primary-action{margin-bottom:18px}
        .management-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:18px}
        .management-panel{border:1px solid #e2e8f0;border-radius:14px;padding:18px;background:#f8fafc}
        .management-panel h3{font-size:17px;margin:0 0 8px}
        .management-panel p{color:#64748b;font-size:13px;line-height:1.45;margin:0 0 14px}
        .management-panel textarea{resize:vertical;min-height:132px}
        .management-panel-danger{border-color:#fecaca;background:#fff7f7}
        .management-panel-warning{border-color:#fde68a;background:#fffdf3}
        .management-submit{margin-top:12px;width:100%}
        .btn-danger{background:#dc2626}
        .btn-warning{background:#d97706}
        .alert-muted{background:#e2e8f0;color:#334155}
        .resolution-date{font-size:13px;margin-top:6px}
        @media(max-width:1100px){.detail-grid{grid-template-columns:1fr}.management-grid{grid-template-columns:1fr}}
        @media(max-width:760px){.page-header{display:block}.page-header .btn{margin-top:16px}.info-grid{grid-template-columns:1fr}.info-item-wide{grid-column:auto}.upload-form{grid-template-columns:1fr}.upload-form .btn{width:100%}}
    </style>
@endpush
