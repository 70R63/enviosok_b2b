<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>

    <style>
        body{
            margin:0;
            padding:40px;
            background:#f3f4f6;
            font-family:Arial, Helvetica, sans-serif;
        }

        .container{
            max-width:1200px;
            margin:auto;
            background:white;
            border-radius:20px;
            padding:40px;
            box-shadow:0 2px 10px rgba(0,0,0,.08);
        }

        .titulo{
            font-size:48px;
            font-weight:bold;
            color:{{ $color }};
            margin-bottom:20px;
        }

        .mensaje{
            font-size:18px;
            color:#64748b;
            margin-bottom:40px;
        }

        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
            margin-bottom:30px;
        }

        .card{
            border:1px solid #dbe2ea;
            border-radius:18px;
            padding:22px;
            min-height:90px;
        }

        .label{
            color:#64748b;
            font-size:14px;
            margin-bottom:10px;
        }

        .value{
            color:#111827;
            font-size:20px;
            font-weight:bold;
        }

        .acciones{
            margin-top:35px;
            display:flex;
            gap:15px;
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

        <div class="card">
            <div class="label">Total pagado</div>
            <div class="value">
               ${{ number_format($cotizacion->precio, 2) }} MXN
            </div>
        </div>

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
        <a href="{{ asset('storage/' . basename($cotizacion->documento)) }}" target="_blank" class="btn-secondary">
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
            <a href="{{ asset('storage/' . $cotizacion->documento) }}" target="_blank" class="btn-primary">
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

        @auth
            <a href="{{ route('b2c.dashboard') }}" class="btn-secondary">
                Ir a mi dashboard
            </a>

            <a href="{{ route('b2c.mis-envios') }}" class="btn-secondary">
                Mis envíos
            </a>
        @endauth

        @guest
            <a href="/" class="btn-secondary">
                Volver al inicio
            </a>
        @endguest

    </div>

</div>

</body>
</html>