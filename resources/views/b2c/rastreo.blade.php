<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rastreo de envío</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .hero{background:#facc15;padding:55px 20px;text-align:center}
        .hero h1{font-size:46px;margin:0 0 20px}
        .search-box{max-width:820px;margin:auto;display:flex;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 24px rgba(0,0,0,.16)}
        .search-box input{flex:1;padding:22px;border:none;font-size:20px;outline:none}
        .search-box button{background:#dc2626;color:#fff;border:none;padding:0 38px;font-size:20px;font-weight:800;cursor:pointer}
        .container{max-width:980px;margin:40px auto;padding:0 20px}
        .card{background:#fff;border-radius:18px;padding:30px;box-shadow:0 12px 30px rgba(0,0,0,.08)}
        .status{font-size:30px;font-weight:800;margin-bottom:10px}
        .muted{color:#64748b}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:25px}
        .box{background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
        .label{font-size:13px;color:#64748b;margin-bottom:6px}
        .value{font-size:18px;font-weight:800}
        .not-found{color:#991b1b;background:#fee2e2;border-radius:12px;padding:18px;font-weight:700}
        @media(max-width:768px){.search-box{flex-direction:column}.search-box button{padding:18px}.grid{grid-template-columns:1fr}.hero h1{font-size:34px}}
    </style>
</head>
<body>

<div class="hero">
    <h1>Rastrea tu envío</h1>

    <form class="search-box" method="POST" action="{{ route('b2c.rastreo.buscar') }}">
        @csrf
        <input
            type="text"
            name="tracking_number"
            placeholder="Ingresa tu número de rastreo"
            value="{{ $tracking ?? '' }}"
            required
        >
        <button type="submit">Rastrear</button>
    </form>
</div>

<div class="container">

    @if ($errors->any())
        <div class="not-found">
            Ingresa un número de rastreo válido.
        </div>
    @endif

    @isset($tracking)
        @if($cotizacion)
            <div class="card">
                <div class="status">Estado del envío</div>
                <div class="muted">Resultado para tracking: <strong>{{ $tracking }}</strong></div>

                <div style="display:flex; gap:12px; margin-top:25px;">
    <a href="{{ url('/') }}"
       style="background:#facc15; color:#111827; padding:14px 22px; border-radius:12px; font-weight:800; text-decoration:none;">
        Nueva cotización
    </a>

    <a href="{{ url('/rastreo') }}"
       style="background:#e5e7eb; color:#111827; padding:14px 22px; border-radius:12px; font-weight:800; text-decoration:none;">
        Rastrear otro envío
    </a>
</div>

                <div class="grid">
                    <div class="box">
                        <div class="label">Cotización</div>
                        <div class="value">#{{ $cotizacion->id }}</div>
                    </div>

                    <div class="box">
                        <div class="label">Estatus guía</div>
                        <div class="value">{{ $cotizacion->guia_estatus ?? 'Pendiente de actualización' }}</div>
                    </div>

                    <div class="box">
                        <div class="label">Mensajería</div>
                        <div class="value">{{ $cotizacion->logistico ?? 'No disponible' }}</div>
                    </div>

                    <div class="box">
                        <div class="label">Tracking</div>
                        <div class="value">{{ $cotizacion->tracking_number ?? 'No disponible' }}</div>
                    </div>

                    <div class="box">
                        <div class="label">Origen</div>
                        <div class="value">{{ $cotizacion->cp_origen }} - {{ $cotizacion->colonia_origen }}</div>
                    </div>

                    <div class="box">
                        <div class="label">Destino</div>
                        <div class="value">{{ $cotizacion->cp_destino }} - {{ $cotizacion->colonia_destino }}</div>
                    </div>
                </div>

                @if(strtoupper($cotizacion->guia_estatus ?? '') === 'ERROR_PROVEEDOR')
    <div style="margin-top:25px; padding:16px; background:#fee2e2; color:#991b1b; border-radius:12px; font-weight:800;">
        No fue posible generar la guía con el proveedor. El pago está registrado, pero la guía requiere reintento.
    </div>
@endif

                <div style="margin-top:30px;">
    <h3 style="font-size:24px; margin-bottom:18px;">Progreso del envío</h3>

    @php
        $estadoGuia = strtoupper($cotizacion->guia_estatus ?? 'PENDIENTE');

if ($estadoGuia === 'ERROR_PROVEEDOR') {
    $indiceActual = -1;
}

        $pasos = [
            'GENERADA' => 'Guía generada',
            'RECOLECTADA' => 'Recolectado',
            'EN_TRANSITO' => 'En tránsito',
            'ENTREGADA' => 'Entregado',
        ];

        $orden = array_keys($pasos);
        $indiceActual = array_search($estadoGuia, $orden);
        if ($indiceActual === false) {
            $indiceActual = $estadoGuia === 'GENERADA' ? 0 : -1;
        }
    @endphp

    <div style="display:flex; flex-direction:column; gap:0;">
        @foreach($pasos as $key => $label)
            @php
                $index = array_search($key, $orden);
                $activo = $index <= $indiceActual;
            @endphp

            <div style="display:flex; align-items:flex-start; gap:14px;">
                <div style="display:flex; flex-direction:column; align-items:center;">
                    <div style="
                        width:24px;
                        height:24px;
                        border-radius:50%;
                        background:{{ $activo ? '#16a34a' : '#d1d5db' }};
                        color:white;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:14px;
                        font-weight:bold;">
                        {{ $activo ? '✓' : '' }}
                    </div>

                    @if(!$loop->last)
                        <div style="
                            width:3px;
                            height:42px;
                            background:{{ $activo ? '#16a34a' : '#d1d5db' }};">
                        </div>
                    @endif
                </div>

                <div style="padding-top:2px;">
                    <div style="font-weight:800; font-size:17px; color:{{ $activo ? '#111827' : '#64748b' }};">
                        {{ $label }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

            </div>
        @else
            <div class="not-found">
                No encontramos información para el número de rastreo: {{ $tracking }}
            </div>
        @endif
    @endisset

</div>

</body>
</html>