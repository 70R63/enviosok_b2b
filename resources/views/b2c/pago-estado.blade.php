<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">

    <style>
        body{
            margin:0;
            padding:24px;
            background:#f3f4f6;
            font-family:Arial, Helvetica, sans-serif;
        }

        .container{
            max-width:1050px;
            margin:auto;
            background:white;
            border-radius:18px;
            padding:30px;
            box-shadow:0 2px 10px rgba(0,0,0,.08);
        }

        .titulo{
            font-size:38px;
            font-weight:bold;
            color:{{ $color }};
            margin-bottom:12px;
        }

        .mensaje{
            font-size:16px;
            color:#64748b;
            margin-bottom:24px;
        }

        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
            margin-bottom:22px;
        }

        .card{
            border:1px solid #dbe2ea;
            border-radius:15px;
            padding:16px 18px;
            min-height:72px;
        }

        .label{
            color:#64748b;
            font-size:14px;
            margin-bottom:10px;
        }

        .value{
            color:#111827;
            font-size:18px;
            font-weight:bold;
        }

        .acciones{
            margin-top:24px;
            display:flex;
            gap:12px;
        }

        .btn-primary{
            background:#facc15;
            color:#000;
            border:none;
            padding:15px 28px;
            border-radius:16px;
            cursor:pointer;
            font-weight:bold;
            font-size:18px;
        }

        .btn-secondary{
            background:#d1d5db;
            color:#111827;
            text-decoration:none;
            padding:15px 28px;
            border-radius:16px;
            font-weight:bold;
            font-size:18px;
            display:inline-block;
        }

        .alert{
            background:#fee2e2;
            color:#991b1b;
            padding:15px;
            border-radius:10px;
            margin-bottom:25px;
        }

        @media(max-width:768px){
            .grid{
                grid-template-columns:1fr;
            }

            .titulo{
                font-size:42px;
            }
        }

        .btn-primary{
            text-decoration:none;
            display:inline-block;
        }


    </style>
</head>
@php
    $isPublicCheckout =
        $cotizacion->referencia === 'LANDING_PUBLICA'
        && empty($cotizacion->user_id);

    $adeudoIncluido = (float) $cotizacion
        ->checkoutDebtAllocations()
        ->where('estatus', 'APLICADO')
        ->sum('monto');

    $totalPagado = (float) (
        $cotizacion->payment_verified_amount
        ?: (
            (float) $cotizacion->precio
            + $adeudoIncluido
        )
    );
    $quoteMetadata = app(
        \App\Services\ZigoProviderQuoteObservationService::class
    )->selectionMetadata($cotizacion);
    $hasCommercialSnapshot = array_key_exists(
        'base',
        (array) ($quoteMetadata['commercial_breakdown'] ?? [])
    );
@endphp
<body>

<div class="container">

    @if(session('error'))
        <div class="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="titulo">
        {{ $titulo }}
    </div>

    <div class="mensaje">
        {{ $mensaje }}
    </div>

    <div class="grid">

        <div class="card">
            <div class="label">Cotización</div>
            <div class="value">#{{ $cotizacion->id }}</div>
        </div>

        <div class="card">
            <div class="label">Estatus</div>
            <div class="value">{{ $cotizacion->estatus }}</div>
        </div>

        <div class="card">
            <div class="label">Mensajería</div>
            <div class="value">{{ $cotizacion->logistico }}</div>
        </div>

        <div class="card">
            <div class="label">Servicio</div>
            <div class="value">{{ $cotizacion->servicio }}</div>
        </div>

        <div class="card">
            <div class="label">Origen</div>
            <div class="value">
                 {{ $cotizacion->cp_origen }} - {{ $cotizacion->colonia_origen }}
            </div>
        </div>

        <div class="card">
            <div class="label">Destino</div>
            <div class="value">
                 {{ $cotizacion->cp_destino }} - {{ $cotizacion->colonia_destino }}
            </div>
        </div>

        <div class="card">
            <div class="label">Peso</div>
            <div class="value">
                {{ number_format($cotizacion->peso,2) }} kg
            </div>
        </div>

        @if(!$hasCommercialSnapshot)
            <div class="card">
                <div class="label">Total pagado</div>
                <div class="value">${{ number_format($totalPagado, 2) }} MXN</div>
            </div>
        @endif

        @if($adeudoIncluido > 0)
            <div class="card">
                <div class="label">Adeudo incluido</div>
                <div class="value">
                    ${{ number_format(
                        $adeudoIncluido,
                        2
                    ) }} MXN
                </div>
            </div>
        @endif

        <div class="card">
            <div class="label">ID de pago</div>
            <div class="value">{{ $cotizacion->payment_id ?? ($cotizacion->payment_status === 'saldo_prepago' ? 'No aplica' : 'No disponible') }}</div>
        </div>

        <div class="card">
            <div class="label">Estatus Mercado Pago</div>
            <div class="value">{{ $cotizacion->payment_status === 'saldo_prepago' ? 'Saldo prepago' : ($cotizacion->payment_status ?? 'No disponible') }}</div>
        </div>

        <div class="card">
            <div class="label">Referencia</div>
            <div class="value">{{ $cotizacion->payment_external_reference ?? 'No disponible' }}</div>
        </div>

<div class="card">
    <div class="label">Collection ID</div>
    <div class="value">{{ $cotizacion->payment_collection_id ?? 'No disponible' }}</div>
</div>

@if($hasCommercialSnapshot)
    <div class="card">
        <div class="label">Desglose comercial</div>
        @include('b2c.partials.commercial-breakdown', ['commercialSnapshot' => $quoteMetadata])
    </div>
@endif

@if($cotizacion->tracking_number)

<div class="card">
    <div class="label">Tracking</div>
    <div class="value">
        {{ $cotizacion->tracking_number }}
    </div>
</div>

@endif

@if($cotizacion->documento)

<div class="card">
    <div class="label">Guía PDF</div>
    <div class="value">
        <a href="{{ strtolower((string) $cotizacion->provider) === 'xperta' ? route('b2c.guia.etiqueta', $cotizacion) : asset('storage/' . basename($cotizacion->documento)) }}" target="_blank" rel="noopener noreferrer" class="btn-secondary">
            Descargar guía
        </a>
    </div>
</div>

@endif

@if($cotizacion->guia_estatus)

<div class="card">
    <div class="label">Estado de guía</div>
    <div class="value">
        {{ $cotizacion->guia_estatus }}
    </div>
</div>

@endif

    </div>

        <div class="acciones">

        @if($cotizacion->documento)
            <a href="{{ strtolower((string) $cotizacion->provider) === 'xperta' ? route('b2c.guia.etiqueta', $cotizacion) : asset('storage/' . $cotizacion->documento) }}" target="_blank" rel="noopener noreferrer" class="btn-primary">
                Descargar guía
            </a>
        @elseif($mostrarGuia && !$cotizacion->tracking_number)
            <form method="POST" action="{{ route('b2c.guia.generar', $cotizacion->id) }}">
                @csrf
                <button type="submit" class="btn-primary">
                    Generar guía
                </button>
            </form>
        @endif

        @if($isPublicCheckout)
            <a href="{{ url('/') }}" class="btn-secondary">
                Volver al inicio
            </a>
        @else
            @auth
                <a href="{{ route('b2c.dashboard') }}" class="btn-secondary">
                    Ir a mi dashboard
                </a>

                <a href="{{ route('b2c.mis-envios') }}" class="btn-secondary">
                    Mis envíos
                </a>
            @endauth

            @guest
                <a href="{{ url('/') }}" class="btn-secondary">
                    Volver al inicio
                </a>
            @endguest
        @endif

    </div>

</div>

</body>
</html>
