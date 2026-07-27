@extends('crm.layout')

@section('title', 'Solicitud API #' . $apiBillingRequest->id . ' | CRM ZIGO')

@section('content')
    @php
        $statusClass = match($apiBillingRequest->status) {
            'FACTURADA' => 'badge-green',
            'RECHAZADA', 'CANCELADA' => 'badge-red',
            'EN_PROCESO' => 'badge-blue',
            default => 'badge-yellow',
        };
        $regimenLabel = $regimenes[$apiBillingRequest->customer_tax_regime] ?? null;
        $usoLabel = $usosCfdi[$apiBillingRequest->customer_cfdi_use] ?? null;
    @endphp

    <div class="api-billing-header">
        <div>
            <div class="title">Solicitud API #{{ $apiBillingRequest->id }}</div>
            <div class="subtitle">
                {{ $apiBillingRequest->external_id }} · {{ strtoupper($apiBillingRequest->environment) }}
            </div>
        </div>
        <a class="btn btn-gray" href="{{ route('crm.api-hub.billing.index') }}">
            Volver al listado
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="api-billing-grid">
        <section class="card">
            <div class="section-title">
                Solicitud y pago
                <span class="badge {{ $statusClass }}">
                    {{ str_replace('_', ' ', ucfirst(strtolower($apiBillingRequest->status))) }}
                </span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Cliente API</span>
                    <strong>{{ $apiBillingRequest->client?->name ?? 'Sin cliente' }}</strong>
                    <small>{{ $apiBillingRequest->client?->company_name }}</small>
                </div>
                <div class="detail-item">
                    <span>Sistema origen</span>
                    <strong>{{ $apiBillingRequest->source_system }}</strong>
                    <small>{{ $apiBillingRequest->external_id }}</small>
                </div>
                <div class="detail-item">
                    <span>API Key</span>
                    <strong>{{ $apiBillingRequest->apiKey?->name ?? 'No disponible' }}</strong>
                    <small>{{ $apiBillingRequest->apiKey?->key_prefix }}</small>
                </div>
                <div class="detail-item">
                    <span>Referencia de pago</span>
                    <strong>{{ $apiBillingRequest->payment_reference }}</strong>
                    <small>{{ $apiBillingRequest->payment_status }}</small>
                </div>
                <div class="detail-item">
                    <span>Método / forma</span>
                    <strong>{{ $apiBillingRequest->payment_method }} / {{ $apiBillingRequest->payment_form }}</strong>
                    <small>{{ optional($apiBillingRequest->payment_date)->format('d/m/Y H:i:s') ?: 'Sin fecha' }}</small>
                </div>
                <div class="detail-item">
                    <span>Total</span>
                    <strong>${{ number_format((float) $apiBillingRequest->total, 2) }} {{ $apiBillingRequest->currency }}</strong>
                    <small>Subtotal ${{ number_format((float) $apiBillingRequest->subtotal, 2) }}</small>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="section-title">Datos fiscales congelados</div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>RFC</span>
                    <strong>{{ $apiBillingRequest->customer_rfc }}</strong>
                </div>
                <div class="detail-item">
                    <span>Razón social</span>
                    <strong>{{ $apiBillingRequest->customer_name }}</strong>
                </div>
                <div class="detail-item">
                    <span>Código postal fiscal</span>
                    <strong>{{ $apiBillingRequest->customer_postal_code }}</strong>
                </div>
                <div class="detail-item">
                    <span>Régimen fiscal</span>
                    <strong>{{ $apiBillingRequest->customer_tax_regime }}</strong>
                    <small>{{ $regimenLabel }}</small>
                </div>
                <div class="detail-item">
                    <span>Uso CFDI</span>
                    <strong>{{ $apiBillingRequest->customer_cfdi_use }}</strong>
                    <small>{{ $usoLabel }}</small>
                </div>
                <div class="detail-item">
                    <span>Correo</span>
                    <strong>{{ $apiBillingRequest->customer_email ?: 'No capturado' }}</strong>
                </div>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="section-title">Conceptos facturables</div>
        <div class="table-wrap">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Línea</th>
                        <th>Categoría</th>
                        <th>Clave SAT</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Descuento</th>
                        <th>Impuesto</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($apiBillingRequest->items as $item)
                        <tr>
                            <td>{{ $item->line_number }}</td>
                            <td>{{ $item->category }}</td>
                            <td>
                                {{ $item->product_service_code }}
                                <div class="muted">{{ $item->unit_code }}</div>
                            </td>
                            <td>{{ $item->description }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                            <td>${{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>${{ number_format((float) $item->discount, 2) }}</td>
                            <td>${{ number_format((float) $item->tax_amount, 2) }}</td>
                            <td><strong>${{ number_format((float) $item->total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-title">Gestión administrativa</div>

        <div class="detail-grid management-summary">
            <div class="detail-item">
                <span>Responsable</span>
                <strong>{{ $apiBillingRequest->manager?->name ?? 'Sin asignar' }}</strong>
                <small>{{ $apiBillingRequest->manager?->email }}</small>
            </div>
            <div class="detail-item">
                <span>Fecha de solicitud</span>
                <strong>{{ optional($apiBillingRequest->requested_at)->format('d/m/Y H:i:s') }}</strong>
            </div>
            <div class="detail-item">
                <span>Inicio de atención</span>
                <strong>{{ optional($apiBillingRequest->processing_at)->format('d/m/Y H:i:s') ?: 'Sin iniciar' }}</strong>
            </div>
        </div>

        @if($apiBillingRequest->rejection_reason)
            <div class="alert alert-error">
                Motivo del rechazo: {{ $apiBillingRequest->rejection_reason }}
            </div>
        @endif

        @if($apiBillingRequest->cancellation_reason)
            <div class="alert alert-warning">
                Motivo de cancelación: {{ $apiBillingRequest->cancellation_reason }}
            </div>
        @endif

        <div class="management-grid">
            <form
                method="POST"
                action="{{ route('crm.api-hub.billing.management.update', $apiBillingRequest) }}"
                class="management-box"
            >
                @csrf
                <h3>Notas internas</h3>
                <p class="muted">No se exponen mediante la API.</p>
                <textarea name="internal_notes" rows="5" maxlength="4000">{{ old('internal_notes', $apiBillingRequest->internal_notes) }}</textarea>
                <button class="btn btn-gray" type="submit">Guardar notas</button>
            </form>

            @if($apiBillingRequest->canStartProcessing())
                <form
                    method="POST"
                    action="{{ route('crm.api-hub.billing.start-processing', $apiBillingRequest) }}"
                    class="management-box"
                    onsubmit="return confirm('¿Iniciar la atención de esta solicitud API?')"
                >
                    @csrf
                    <h3>Iniciar atención</h3>
                    <p class="muted">Asigna la solicitud al administrador actual y cambia a En proceso.</p>
                    <button class="btn" type="submit">Iniciar atención</button>
                </form>
            @endif

            @if($apiBillingRequest->canManage())
                <form
                    method="POST"
                    action="{{ route('crm.api-hub.billing.reject', $apiBillingRequest) }}"
                    class="management-box management-danger"
                    onsubmit="return confirm('¿Rechazar esta solicitud API?')"
                >
                    @csrf
                    <h3>Rechazar solicitud</h3>
                    <textarea name="rejection_reason" rows="5" minlength="10" maxlength="2000" required placeholder="Motivo visible para el sistema consumidor."></textarea>
                    <button class="btn btn-danger" type="submit">Rechazar</button>
                </form>

                <form
                    method="POST"
                    action="{{ route('crm.api-hub.billing.cancel', $apiBillingRequest) }}"
                    class="management-box management-warning"
                    onsubmit="return confirm('¿Cancelar esta solicitud API?')"
                >
                    @csrf
                    <h3>Cancelar solicitud</h3>
                    <textarea name="cancellation_reason" rows="5" minlength="10" maxlength="2000" required placeholder="Motivo visible para el sistema consumidor."></textarea>
                    <button class="btn btn-warning" type="submit">Cancelar</button>
                </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="section-title">Documentos fiscales</div>

        @if($apiBillingRequest->documentsReady())
            <div class="alert alert-success">
                El CFDI fue validado y está disponible mediante API Hub.
            </div>

            <div class="detail-grid">
                <div class="detail-item detail-wide">
                    <span>UUID</span>
                    <strong>{{ $apiBillingRequest->cfdi_uuid }}</strong>
                </div>
                <div class="detail-item">
                    <span>RFC emisor</span>
                    <strong>{{ $apiBillingRequest->cfdi_rfc_emisor }}</strong>
                </div>
                <div class="detail-item">
                    <span>RFC receptor</span>
                    <strong>{{ $apiBillingRequest->cfdi_rfc_receptor }}</strong>
                </div>
                <div class="detail-item">
                    <span>Total CFDI</span>
                    <strong>${{ number_format((float) $apiBillingRequest->cfdi_total, 2) }}</strong>
                </div>
                <div class="detail-item">
                    <span>Fecha de timbrado</span>
                    <strong>{{ optional($apiBillingRequest->cfdi_fecha_timbrado)->format('d/m/Y H:i:s') }}</strong>
                </div>
            </div>

            <div class="document-actions">
                @foreach(['pdf' => 'Descargar PDF', 'xml' => 'Descargar XML', 'zip' => 'Descargar ZIP'] as $format => $label)
                    <a
                        class="btn {{ $format === 'zip' ? 'btn-success' : ($format === 'xml' ? 'btn-gray' : '') }}"
                        href="{{ route('crm.api-hub.billing.documents.download', [$apiBillingRequest, $format]) }}"
                    >{{ $label }}</a>
                @endforeach
            </div>
        @elseif($apiBillingRequest->canUploadDocuments())
            @if($apiBillingRequest->error_message)
                <div class="alert alert-error">
                    Último error: {{ $apiBillingRequest->error_message }}
                </div>
            @endif

            <form
                method="POST"
                enctype="multipart/form-data"
                action="{{ route('crm.api-hub.billing.documents.store', $apiBillingRequest) }}"
                class="document-upload"
            >
                @csrf
                <div>
                    <label for="cfdi_zip"><strong>ZIP fiscal</strong></label>
                    <input id="cfdi_zip" type="file" name="cfdi_zip" accept=".zip,application/zip" required>
                    <div class="muted">Debe contener exactamente un PDF y un XML CFDI 4.0. Máximo 15 MB.</div>
                </div>
                <button class="btn" type="submit">Validar y cargar CFDI</button>
            </form>
        @else
            <div class="alert alert-info">
                La carga del CFDI se habilita cuando la solicitud está En proceso.
            </div>
        @endif
    </section>
@endsection

@push('styles')
<style>
    .api-billing-header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}
    .api-billing-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
    .section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:20px;font-weight:900;margin-bottom:18px}
    .detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .detail-item{border:1px solid #dbe3ef;border-radius:12px;padding:14px;background:#f8fafc;min-width:0}
    .detail-item span{display:block;color:#64748b;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
    .detail-item strong{display:block;margin-top:7px;overflow-wrap:anywhere}
    .detail-item small{display:block;color:#64748b;margin-top:5px}
    .detail-wide{grid-column:1/-1}
    .items-table{min-width:1050px}
    .management-summary{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:16px}
    .management-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .management-box{border:1px solid #dbe3ef;border-radius:14px;padding:16px;background:#f8fafc}
    .management-box h3{margin:0 0 8px}
    .management-box textarea{margin:10px 0}
    .management-danger{border-color:#fecaca;background:#fff7f7}
    .management-warning{border-color:#fde68a;background:#fffbeb}
    .btn-danger{background:#dc2626}
    .btn-warning{background:#d97706}
    .btn-success{background:#16a34a}
    .alert-success{background:#dcfce7;color:#166534}
    .alert-warning{background:#fef3c7;color:#92400e}
    .alert-info{background:#dbeafe;color:#1d4ed8}
    .document-upload{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:end}
    .document-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
    @media(max-width:1050px){
        .api-billing-grid,.management-grid{grid-template-columns:1fr}
        .management-summary{grid-template-columns:1fr 1fr}
    }
    @media(max-width:760px){
        .api-billing-header{display:block}
        .api-billing-header .btn{margin-bottom:18px}
        .detail-grid,.management-summary,.document-upload{grid-template-columns:1fr}
    }
</style>
@endpush
