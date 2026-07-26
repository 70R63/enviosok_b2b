@extends('crm.layout')

@section('title', 'Facturación B2C | CRM ZIGO')

@section('content')
    <div class="title">Facturación B2C</div>
    <div class="subtitle">
        Solicitudes de CFDI generadas desde el portal B2C de ZIGO.
    </div>

    <div class="summary-grid summary-grid-invoices">
        <div class="summary-card">
            <span>Solicitadas</span>
            <strong>{{ $statusCounts->get('SOLICITADA', 0) }}</strong>
        </div>

        <div class="summary-card">
            <span>En proceso</span>
            <strong>{{ $statusCounts->get('EN_PROCESO', 0) }}</strong>
        </div>

        <div class="summary-card">
            <span>Facturadas</span>
            <strong>{{ $statusCounts->get('FACTURADA', 0) }}</strong>
        </div>

        <div class="summary-card">
            <span>Rechazadas</span>
            <strong>{{ $statusCounts->get('RECHAZADA', 0) }}</strong>
        </div>

        <div class="summary-card">
            <span>Canceladas</span>
            <strong>{{ $statusCounts->get('CANCELADA', 0) }}</strong>
        </div>
    </div>

    <div class="card">
        <form method="GET"
              action="{{ route('crm.facturacion.index') }}"
              class="filters filters-invoices">
            <select name="status">
                <option value="">Todos los estados</option>
                <option value="SOLICITADA" @selected(request('status') === 'SOLICITADA')>
                    Solicitada
                </option>
                <option value="EN_PROCESO" @selected(request('status') === 'EN_PROCESO')>
                    En proceso
                </option>
                <option value="FACTURADA" @selected(request('status') === 'FACTURADA')>
                    Facturada
                </option>
                <option value="RECHAZADA" @selected(request('status') === 'RECHAZADA')>
                    Rechazada
                </option>
                <option value="CANCELADA" @selected(request('status') === 'CANCELADA')>
                    Cancelada
                </option>
            </select>

            <input type="text"
                   name="rfc"
                   maxlength="13"
                   placeholder="RFC"
                   value="{{ request('rfc') }}">

            <input type="number"
                   name="cotizacion"
                   min="1"
                   placeholder="Cotización"
                   value="{{ request('cotizacion') }}">

            <input type="text"
                   name="cliente"
                   placeholder="Cliente o correo"
                   value="{{ request('cliente') }}">

            <input type="date"
                   name="fecha_desde"
                   value="{{ request('fecha_desde') }}">

            <input type="date"
                   name="fecha_hasta"
                   value="{{ request('fecha_hasta') }}">

            <button class="btn" type="submit">Filtrar</button>

            <a class="btn btn-gray"
               href="{{ route('crm.facturacion.index') }}">
                Limpiar
            </a>
        </form>

        @if($errors->any())
            <div class="alert alert-error">
                Revisa los filtros capturados.
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha de solicitud</th>
                        <th>Cliente</th>
                        <th>Cotización</th>
                        <th>Referencia de pago</th>
                        <th>Monto</th>
                        <th>RFC</th>
                        <th>Razón social</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($invoiceRequests as $invoiceRequest)
                        @php
                            $user = $invoiceRequest->user;
                            $clientName = collect([
                                $user?->name,
                                $user?->apellido_paterno,
                                $user?->apellido_materno,
                            ])->filter()->implode(' ');
                        @endphp

                        <tr>
                            <td>
                                <strong>#{{ $invoiceRequest->id }}</strong>
                            </td>

                            <td>
                                {{ optional($invoiceRequest->solicitada_at)->format('d/m/Y H:i') ?? '-' }}
                            </td>

                            <td>
                                <strong>{{ $clientName !== '' ? $clientName : 'Usuario #' . $invoiceRequest->user_id }}</strong>
                                <div class="muted">{{ $user?->email ?? '-' }}</div>
                            </td>

                            <td>#{{ $invoiceRequest->cotizacion_id }}</td>

                            <td>{{ $invoiceRequest->payment_reference ?? '-' }}</td>

                            <td>
                                <strong>${{ number_format((float) $invoiceRequest->monto, 2) }}</strong>
                            </td>

                            <td>{{ $invoiceRequest->rfc }}</td>

                            <td>{{ $invoiceRequest->razon_social }}</td>

                            <td>
                                <span class="badge {{ $invoiceRequest->status_badge_class }}">
                                    {{ $invoiceRequest->status_label }}
                                </span>
                            </td>

                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-small btn-gray"
                                       href="{{ route('crm.facturacion.show', $invoiceRequest) }}">
                                        Ver detalle
                                    </a>

                                    @if($invoiceRequest->puedeIniciarAtencion())
                                        <form method="POST"
                                              action="{{ route('crm.facturacion.iniciar-atencion', $invoiceRequest) }}">
                                            @csrf
                                            <button class="btn btn-small"
                                                    type="submit"
                                                    onclick="return confirm('¿Marcar esta solicitud como En proceso?')">
                                                Atender
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">
                                No hay solicitudes de facturación con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $invoiceRequests->links() }}
        </div>
    </div>
@endsection


@push('styles')
    <style>
        .summary-grid-invoices{grid-template-columns:repeat(5,minmax(0,1fr))}
        @media(max-width:1250px){.summary-grid-invoices{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:760px){.summary-grid-invoices{grid-template-columns:1fr}}
    </style>
@endpush
