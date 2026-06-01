<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout | EnviosOK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #111827;
        }

        .header {
            background: linear-gradient(90deg, #2563eb, #4338ca);
            padding: 18px 24px;
        }

        .header-inner {
            max-width: 1180px;
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
            max-width: 1180px;
            margin: 36px auto;
            padding: 0 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            margin-bottom: 20px;
        }

        h1 {
            color: #1d4ed8;
            margin-top: 0;
        }

        h2 {
            margin-top: 0;
            font-size: 22px;
            color: #111827;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .full {
            grid-column: 1 / -1;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .total {
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            font-size: 24px;
            font-weight: 900;
        }

        .btn {
            width: 100%;
            border: none;
            background: #facc15;
            color: #111827;
            padding: 15px;
            border-radius: 10px;
            font-weight: 900;
            cursor: pointer;
            font-size: 16px;
            margin-top: 18px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <a href="/">
            <img src="{{ asset('img/Envios_OK_variante_C4x.png') }}" alt="EnviosOK">
        </a>

        <nav>
            <a href="{{ url('/') }}">Inicio</a>
            <a href="{{ url('/login') }}">Iniciar sesión</a>
        </nav>
    </div>
</header>

<main class="container">
    <h1>Completa los datos de tu guía</h1>

    <form method="POST" action="{{ route('b2c.checkout.procesar', $cotizacion->id) }}">
        @csrf

        <div class="grid">
            <div>
                <section class="card">
                    <h2>Remitente</h2>

                    <div class="form-grid">
                        <div>
                            <label>Nombre completo</label>
                            <input type="text" name="remitente_nombre" required>
                        </div>

                        <div>
                            <label>Teléfono</label>
                            <input type="text" name="remitente_telefono" required>
                        </div>

                        <div class="full">
                            <label>Correo electrónico</label>
                            <input type="email" name="remitente_email" required>
                        </div>

                        <div class="full">
                            <label>Dirección origen</label>
                            <input type="text" name="remitente_direccion" placeholder="Calle, número exterior/interior" required>
                        </div>

                        <div class="full">
                            <label>Colonia origen</label>
                            <input type="text" value="{{ $cotizacion->colonia_origen }}" readonly>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Destinatario</h2>

                    <div class="form-grid">
                        <div>
                            <label>Nombre completo</label>
                            <input type="text" name="destinatario_nombre" required>
                        </div>

                        <div>
                            <label>Teléfono</label>
                            <input type="text" name="destinatario_telefono" required>
                        </div>

                        <div class="full">
                            <label>Correo electrónico</label>
                            <input type="email" name="destinatario_email">
                        </div>

                        <div class="full">
                            <label>Dirección destino</label>
                            <input type="text" name="destinatario_direccion" placeholder="Calle, número exterior/interior" required>
                        </div>

                        <div class="full">
                            <label>Colonia destino</label>
                            <input type="text" value="{{ $cotizacion->colonia_destino }}" readonly>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Contenido del paquete</h2>

                    <div class="form-grid">
                        <div class="full">
                            <label>Descripción del contenido</label>
                            <input type="text" name="contenido" placeholder="Ej. ropa, documentos, accesorios" required>
                        </div>

                        <div>
                            <label>Valor declarado</label>
                            <input type="number" name="valor_declarado" min="0" step="0.01" value="0">
                        </div>

                        <div>
                            <label>Referencia opcional</label>
                            <input type="text" name="referencia">
                        </div>
                    </div>
                </section>
            </div>

            <aside class="card">
                <h2>Resumen</h2>

                <div class="summary-row">
                    <span>Mensajería</span>
                    <strong>{{ $cotizacion->logistico }}</strong>
                </div>

                <div class="summary-row">
                    <span>Servicio</span>
                    <strong>{{ $cotizacion->servicio }}</strong>
                </div>

                <div class="summary-row">
                    <span>Peso</span>
                    <strong>{{ $cotizacion->peso }} kg</strong>
                </div>

                <div class="summary-row">
                    <span>Medidas</span>
                    <strong>{{ $cotizacion->medidas ?? 'N/A' }}</strong>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <span>${{ number_format($cotizacion->precio, 2) }} MXN</span>
                </div>

                <p class="muted">
                    El pago se realizará en línea. Una vez confirmado, se generará la guía correspondiente.
                </p>

                <button class="btn" type="submit">Pagar y generar guía</button>
            </aside>
        </div>
    </form>
</main>

</body>
</html>