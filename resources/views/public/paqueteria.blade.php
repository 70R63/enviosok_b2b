<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Paquetería | ZIGO</title>
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
            max-width: 850px;
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

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .step {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
        }

        .step strong {
            display: inline-block;
            background: #2563eb;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            text-align: center;
            line-height: 32px;
            margin-bottom: 10px;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
            color: #2563eb;
            font-weight: 900;
            text-decoration: none;
        }

        @media(max-width: 800px) {
            .steps {
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
    <h1>Paquetería y soluciones de envío</h1>
    <p>
        En ZIGO facilitamos el proceso para cotizar, pagar y generar guías de envío,
        integrando tecnología con servicios logísticos disponibles.
    </p>
</header>

<main class="container">
    <section class="section">
        <h2>¿Qué ofrecemos?</h2>
        <p>
            Nuestra plataforma permite gestionar envíos de forma digital, iniciando desde una cotización sencilla
            hasta la generación de una guía. El objetivo es que el usuario tenga una experiencia clara, rápida
            y centralizada.
        </p>
    </section>

    <section class="section">
        <h2>¿Cómo funciona?</h2>

        <div class="steps">
            <div class="step">
                <strong>1</strong>
                <h3>Captura datos</h3>
                <p>Ingresa código postal origen, destino, tipo de envío, peso y dimensiones.</p>
            </div>

            <div class="step">
                <strong>2</strong>
                <h3>Consulta opciones</h3>
                <p>La plataforma calcula opciones disponibles de acuerdo con la cobertura y configuración vigente.</p>
            </div>

            <div class="step">
                <strong>3</strong>
                <h3>Genera tu guía</h3>
                <p>Después del pago, puedes generar la guía correspondiente y continuar con el seguimiento.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Soluciones para diferentes necesidades</h2>
        <p>
            ZIGO está pensado para usuarios finales, emprendedores y empresas que requieren una forma más práctica
            de operar sus envíos. La plataforma también está preparada para crecer hacia modelos empresariales,
            control de saldos, reportes, clientes B2B e integraciones mediante API Hub.
        </p>

        <a class="back" href="{{ url('/') }}">← Volver al inicio</a>
    </section>
</main>

</body>
</html>