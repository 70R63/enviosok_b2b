@extends('negocios.layouts.app')

@section('title', 'Mis guías | ZIGO Negocios')

@section('content')
    <h1 class="title">Mis guías</h1>
    <div class="subtitle">Consulta las guías generadas por tu empresa.</div>

    @if($errors->any())
        <div class="errors" role="alert">
            <strong>Revisa los filtros:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="section">
        <form method="GET" action="{{ route('negocios.guias.index') }}">
            <div class="filters">
                <div class="field"><label for="tracking_number">Número de guía</label><input id="tracking_number" name="tracking_number" maxlength="100" value="{{ old('tracking_number', $filters['tracking_number'] ?? '') }}"></div>
                <div class="field"><label for="numero_solicitud">Número de solicitud</label><input id="numero_solicitud" name="numero_solicitud" type="number" min="1" value="{{ old('numero_solicitud', $filters['numero_solicitud'] ?? '') }}"></div>
                <div class="field">
                    <label for="rastreo_estatus">Estado logístico</label>
                    <select id="rastreo_estatus" name="rastreo_estatus">
                        <option value="">Todos</option>
                        @foreach($estados as $id => $nombre)
                            <option value="{{ $id }}" @selected((string) old('rastreo_estatus', $filters['rastreo_estatus'] ?? '') === (string) $id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="fecha_inicio">Fecha inicial</label><input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ old('fecha_inicio', $filters['fecha_inicio'] ?? '') }}"></div>
                <div class="field"><label for="fecha_fin">Fecha final</label><input id="fecha_fin" name="fecha_fin" type="date" value="{{ old('fecha_fin', $filters['fecha_fin'] ?? '') }}"></div>
            </div>
            <div class="actions" style="margin-top:16px">
                <button class="btn" type="submit">Buscar</button>
                <a class="btn btn-secondary" href="{{ route('negocios.guias.index') }}">Limpiar</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Número de guía</th><th>Número de solicitud</th><th>Fecha</th><th>Paquetería</th><th>Servicio</th><th>Destinatario</th><th>Estado</th><th>Piezas</th><th>Importe</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($guias as $guia)
                    <tr>
                        <td>{{ $guia->tracking_number }}</td><td>{{ $guia->numero_solicitud ?? '—' }}</td><td>{{ optional($guia->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $guia->paqueteria_nombre ?? 'No disponible' }}</td><td>{{ $guia->servicio_nombre ?? 'No disponible' }}</td><td>{{ $guia->destinatario_nombre ?? $guia->destinatario_contacto ?? 'No disponible' }}</td><td>{{ $guia->rastreo_estatus_nombre ?? 'No disponible' }}</td><td>{{ $guia->piezas }}</td><td>${{ number_format((float) $guia->precio, 2) }}</td>
                        <td class="actions"><a class="btn btn-small" href="{{ route('negocios.guias.show', $guia->id) }}">Ver detalle</a>@if(!empty($guia->documento)) <a class="btn btn-secondary btn-small" href="{{ route('negocios.guias.etiqueta', $guia->id) }}">Etiqueta</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">No se encontraron guías con los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $guias->links() }}</div>
    </section>
@endsection
