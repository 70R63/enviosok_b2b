<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nosotros | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #111827;
            background: #f8fafc;
        }

        .page-header {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            padding: 50px 24px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 42px;
            margin: 0 0 12px;
        }

        .page-header p {
            max-width: 820px;
            margin: auto;
            font-size: 18px;
            line-height: 1.6;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            padding: 50px 24px;
        }

        .section {
            background: white;
            border-radius: 18px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
        }

        .section h2 {
            color: #1d4ed8;
            margin-top: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
            color: #2563eb;
            font-weight: 900;
            text-decoration: none;
        }

        @media(max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1>Nosotros</h1>
    <p>
        En ZIGO conectamos tecnología, logística y operación para facilitar la forma en que personas,
        emprendedores y empresas gestionan sus envíos.
    </p>
</header>

<main class="container">
    <section class="section">
        <h2>¿Quiénes somos?</h2>
        <p>
            ZIGO es una plataforma digital enfocada en simplificar la cotización, pago, generación de guías
            y seguimiento de envíos. Nuestro objetivo es ofrecer una experiencia clara, práctica y accesible
            para usuarios finales y negocios que necesitan operar sus envíos de forma más eficiente.
        </p>
    </section>

    <section class="section">
        <h2>¿Qué hacemos?</h2>
        <div class="grid">
            <div class="card">
                <h3>Cotización digital</h3>
                <p>Permitimos consultar opciones de envío con base en origen, destino, peso y dimensiones.</p>
            </div>

            <div class="card">
                <h3>Guías de envío</h3>
                <p>Facilitamos el proceso para pagar y generar guías de envío desde una plataforma centralizada.</p>
            </div>

            <div class="card">
                <h3>Soluciones empresariales</h3>
                <p>Diseñamos una base operativa para clientes con mayor volumen, control y necesidades de integración.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Nuestra visión</h2>
        <p>
            Queremos convertirnos en una plataforma logística flexible, tecnológica y escalable, capaz de acompañar
            desde envíos ocasionales hasta operaciones empresariales con mayor demanda.
        </p>

        <a class="back" href="{{ url('/') }}">← Volver al inicio</a>
    </section>
</main>

</body>
</html>