@extends('negocios.layouts.app')

@section('title', 'Detalle de guía | ZIGO Negocios')

@section('content')
    <h1 class="title">Detalle de guía</h1>
    <div class="subtitle">Información logística de {{ $guia->tracking_number }}.</div>

    <div class="top-actions">
        <a class="btn btn-secondary" href="{{ route('negocios.guias.index') }}">Volver a Mis guías</a>
        @if(!empty($guia->documento))<a class="btn" href="{{ route('negocios.guias.etiqueta', $guia->id) }}">Descargar etiqueta</a>@endif
    </div>

    <section class="section">
        @php
            $pickupFecha = $guia->pickup_fecha;
            $pickupFechaTexto = trim((string) $pickupFecha);
            $pickupFechaNoDisponible = $pickupFechaTexto === ''
                || (preg_match('/^(\d{4})-\d{2}-\d{2}/', $pickupFechaTexto, $pickupFechaPartes)
                    && (int) $pickupFechaPartes[1] < 2000);

            $quienRecibio = $guia->quien_recibio;
            $quienRecibioTexto = trim((string) $quienRecibio);
            $quienRecibioNoRegistrado = $quienRecibioTexto === ''
                || stripos($quienRecibioTexto, 'nombre no registrado') !== false;

            $dimensiones = $guia->dimensiones;
            $dimensionesTexto = trim((string) $dimensiones);
            $dimensionesNoRegistradas = $dimensionesTexto === '' || $dimensionesTexto === 'LxWxH';
        @endphp

        <div class="detail-grid">
            <div class="detail-item"><div class="label">Número de guía</div><div>{{ $guia->tracking_number }}</div></div>
            <div class="detail-item"><div class="label">Número de solicitud</div><div>{{ $guia->numero_solicitud ?? '—' }}</div></div>
            <div class="detail-item"><div class="label">Fecha de creación</div><div>{{ optional($guia->created_at)->format('d/m/Y H:i') }}</div></div>
            <div class="detail-item"><div class="label">Estado logístico</div><div>{{ $relations->rastreo_estatus_nombre ?? 'No disponible' }}</div></div>
            <div class="detail-item"><div class="label">Última actualización</div><div>{{ $guia->ultima_fecha ?? 'No disponible' }}</div></div>
            <div class="detail-item"><div class="label">Fecha de recolección</div><div>{{ $pickupFechaNoDisponible ? 'No disponible' : $pickupFecha }}</div></div>
            <div class="detail-item"><div class="label">Quién recibió</div><div>{{ $quienRecibioNoRegistrado ? 'No registrado' : $quienRecibio }}</div></div>
            <div class="detail-item"><div class="label">Paquetería</div><div>{{ $relations->paqueteria_nombre ?? 'No disponible' }}</div></div>
            <div class="detail-item"><div class="label">Servicio</div><div>{{ $relations->servicio_nombre ?? 'No disponible' }}</div></div>
            <div class="detail-item"><div class="label">Piezas</div><div>{{ $guia->piezas }}</div></div>
            <div class="detail-item"><div class="label">Peso</div><div>{{ $guia->peso }} kg</div></div>
            <div class="detail-item"><div class="label">Dimensiones</div><div>{{ $dimensionesNoRegistradas ? 'No registradas' : $dimensiones }}</div></div>
            <div class="detail-item"><div class="label">Contenido</div><div>{{ $guia->contenido ?: 'No disponible' }}</div></div>
            <div class="detail-item"><div class="label">Valor del envío</div><div>${{ number_format((float) $guia->valor_envio, 2) }}</div></div>
            <div class="detail-item"><div class="label">Importe</div><div>${{ number_format((float) $guia->precio, 2) }}</div></div>
        </div>

        <div class="address-grid">
            <div class="card"><h2>Origen</h2><p><strong>{{ $relations->origen_nombre ?? 'No disponible' }}</strong></p><p>{{ $relations->origen_contacto ?? '' }}</p><p class="muted">{{ collect([$relations->origen_direccion ?? null, $relations->origen_direccion2 ?? null, $relations->origen_colonia ?? null, $relations->origen_ciudad ?? null, $relations->origen_estado ?? null, $relations->origen_cp ?? null])->filter()->implode(', ') ?: 'Datos de origen no disponibles' }}</p></div>
            <div class="card"><h2>Destino</h2><p><strong>{{ $relations->destino_nombre ?? 'No disponible' }}</strong></p><p>{{ $relations->destino_contacto ?? '' }}</p><p class="muted">{{ collect([$relations->destino_direccion ?? null, $relations->destino_direccion2 ?? null, $relations->destino_colonia ?? null, $relations->destino_ciudad ?? null, $relations->destino_estado ?? null, $relations->destino_cp ?? null])->filter()->implode(', ') ?: 'Datos de destino no disponibles' }}</p></div>
        </div>
    </section>
@endsection
