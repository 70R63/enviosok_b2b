<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultados de cotización | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .header {
            background: linear-gradient(90deg, #2563eb, #4338ca);
            padding: 18px 24px;
        }

        .header-inner {
            max-width: 1100px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header img {
            height: 42px;
        }

        .header a {
            color: white;
            text-decoration: none;
            font-weight: 700;
            margin-left: 20px;
        }

        .container {
            max-width: 1050px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .summary {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            margin-bottom: 28px;
        }

        .summary h1 {
            margin-top: 0;
            color: #1d4ed8;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            font-size: 14px;
        }

        .option {
            background: white;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: 160px 1fr 180px 150px;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
        }

        .option img {
            max-width: 130px;
            max-height: 50px;
        }

        .service {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .delivery {
            color: #64748b;
            font-size: 14px;
        }

        .price {
            font-size: 24px;
            font-weight: 900;
            color: #111827;
        }

        .price small {
            display: block;
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .btn {
            background: #facc15;
            color: #111827;
            border: none;
            padding: 13px 18px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 900;
            cursor: pointer;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 700;
        }

        @media (max-width: 850px) {
            .summary-grid,
            .option {
                grid-template-columns: 1fr;
            }

            .option {
                text-align: center;
            }

            .option img {
                margin: auto;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <a href="/">
            <img src="{{ asset('img/Envios_OK_variante_C4x.png') }}" alt="ZIGO">
        </a>

        <nav>
            <a href="{{ url('/') }}">Inicio</a>
            <a href="{{ url('/login') }}">Iniciar sesión</a>
            <a href="{{ url('/register') }}">Registro</a>
        </nav>
    </div>
</header>

<main class="container">

    <section class="summary">
        <h1>Opciones disponibles para tu envío</h1>

        <div class="summary-grid">
            <div><strong>Origen:</strong><br>{{ $cotizacion->cp_origen }}</div>
            <div><strong>Destino:</strong><br>{{ $cotizacion->cp_destino }}</div>
            <div><strong>Tipo:</strong><br>{{ ucfirst($cotizacion->tipo_envio) }}</div>
            <div><strong>Peso:</strong><br>{{ $cotizacion->peso }} kg</div>
            <div><strong>Medidas:</strong><br>{{ $cotizacion->medidas ?? 'N/A' }}</div>
        </div>
    </section>

    @foreach($opciones as $opcion)
        <section class="option">
            <div>
                <img src="{{ asset($opcion['logo']) }}" alt="{{ $opcion['logistico'] }}">
            </div>

            <div>
                <div class="service">{{ $opcion['logistico'] }} - {{ $opcion['servicio'] }}</div>
                <div class="delivery">Entrega estimada: {{ $opcion['entrega'] }}</div>
            </div>

            <div class="price">
                ${{ number_format($opcion['precio'], 2) }} MXN
                <small>Precio final con IVA</small>
            </div>

            <form method="POST" action="{{ route('b2c.cotizacion.seleccionar', $cotizacion->id) }}">
    @csrf
    <input type="hidden" name="logistico" value="{{ $opcion['logistico'] }}">
    <input type="hidden" name="servicio" value="{{ $opcion['servicio'] }}">
    <input type="hidden" name="precio" value="{{ $opcion['precio'] }}">
    <button class="btn" type="submit">Comprar guía</button>
</form>
        </section>
    @endforeach

    <a class="back" href="{{ url('/') }}">← Realizar otra cotización</a>

</main>

</body>
</html>