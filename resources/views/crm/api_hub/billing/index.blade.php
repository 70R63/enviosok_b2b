@extends('crm.layout')

@section('title', 'Facturación API | CRM ZIGO')

@section('content')
    <div class="title">Facturación API</div>
    <div class="subtitle">
        Solicitudes recibidas desde ZIGO API Hub y sistemas externos.
    </div>

    <div class="summary-grid api-billing-summary">
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
        <form
            method="GET"
            action="{{ route('crm.api-hub.billing.index') }}"
            class="filters api-billing-filters"
        >
            <select name="status">
                <option value="">Todos los estados</option>
                @foreach(['SOLICITADA', 'EN_PROCESO', 'FACTURADA', 'RECHAZADA', 'CANCELADA'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>
                        {{ str_replace('_', ' ', ucfirst(strtolower($status))) }}
                    </option>
                @endforeach
            </select>

            <select name="environment">
                <option value="">Todos los ambientes</option>
                <option value="sandbox" @selected(request('environment') === 'sandbox')>
                    Sandbox
                </option>
                <option value="production" @selected(request('environment') === 'production')>
                    Producción
                </option>
            </select>

            <select name="api_client_id">
                <option value="">Todos los clientes API</option>
                @foreach($apiClients as $apiClient)
                    <option
                        value="{{ $apiClient->id }}"
                        @selected((string) request('api_client_id') === (string) $apiClient->id)
                    >
                        {{ $apiClient->name }}
                    </option>
                @endforeach
            </select>

            <input
                type="text"
                name="search"
                maxlength="150"
                placeholder="External ID, RFC, cliente o referencia"
                value="{{ request('search') }}"
            >

            <button class="btn" type="submit">Filtrar</button>
            <a class="btn btn-gray" href="{{ route('crm.api-hub.billing.index') }}">
                Limpiar
            </a>
        </form>

        @if($errors->any())
            <div class="alert alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Solicitud</th>
                        <th>Cliente API</th>
                        <th>Ambiente</th>
                        <th>External ID</th>
                        <th>Receptor</th>
                        <th>Referencia</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($billingRequests as $billingRequest)
                        @php
                            $badgeClass = match($billingRequest->status) {
                                'FACTURADA' => 'badge-green',
                                'RECHAZADA', 'CANCELADA' => 'badge-red',
                                'EN_PROCESO' => 'badge-blue',
                                default => 'badge-yellow',
                            };
                        @endphp
                        <tr>
                            <td><strong>#{{ $billingRequest->id }}</strong></td>
                            <td>
                                {{ optional($billingRequest->requested_at)->format('d/m/Y H:i') }}
                                <div class="muted">{{ $billingRequest->source_system }}</div>
                            </td>
                            <td>
                                <strong>{{ $billingRequest->client?->name ?? 'Sin cliente' }}</strong>
                                <div class="muted">{{ $billingRequest->client?->company_name }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $billingRequest->environment === 'production' ? 'badge-green' : 'badge-gray' }}">
                                    {{ strtoupper($billingRequest->environment) }}
                                </span>
                            </td>
                            <td>{{ $billingRequest->external_id }}</td>
                            <td>
                                <strong>{{ $billingRequest->customer_name }}</strong>
                                <div class="muted">{{ $billingRequest->customer_rfc }}</div>
                            </td>
                            <td>{{ $billingRequest->payment_reference }}</td>
                            <td>
                                <strong>
                                    ${{ number_format((float) $billingRequest->total, 2) }}
                                    {{ $billingRequest->currency }}
                                </strong>
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }}">
                                    {{ str_replace('_', ' ', ucfirst(strtolower($billingRequest->status))) }}
                                </span>
                            </td>
                            <td>
                                <a
                                    class="btn btn-gray"
                                    href="{{ route('crm.api-hub.billing.show', $billingRequest) }}"
                                >Ver detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">
                                No existen solicitudes API con los filtros capturados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $billingRequests->links() }}
        </div>
    </div>
@endsection

@push('styles')
<style>
    .api-billing-summary{grid-template-columns:repeat(5,minmax(0,1fr))}
    .api-billing-filters{grid-template-columns:repeat(4,minmax(150px,1fr)) 140px 140px}
    @media(max-width:1250px){
        .api-billing-summary{grid-template-columns:repeat(3,minmax(0,1fr))}
        .api-billing-filters{grid-template-columns:repeat(3,minmax(150px,1fr))}
    }
    @media(max-width:760px){
        .api-billing-summary,.api-billing-filters{grid-template-columns:1fr}
    }
</style>
@endpush
