<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Soporte | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #0b1220;
            background: #f6f8fc;
        }

        .page {
            max-width: 1080px;
            margin: 0 auto;
            padding: 56px 24px;
        }

        .back {
            display: inline-block;
            margin-bottom: 28px;
            color: #4361ee;
            font-weight: 800;
            text-decoration: none;
        }

        .hero {
            background: #ffffff;
            border-radius: 28px;
            padding: 50px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .08);
        }

        .hero h1 {
            margin: 0 0 16px;
            font-size: 44px;
        }

        .hero p {
            margin: 0;
            max-width: 760px;
            color: #475569;
            font-size: 18px;
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
            margin-top: 30px;
        }

        .card {
            background: #ffffff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        .card p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }

        .cta {
            margin-top: 32px;
            background: linear-gradient(135deg, #4361ee, #4b3fd1);
            color: #ffffff;
            border-radius: 24px;
            padding: 34px;
        }

        .cta h2 {
            margin: 0 0 12px;
            font-size: 30px;
        }

        .cta p {
            margin: 0 0 20px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 15px 24px;
            border-radius: 14px;
            background: #22c55e;
            color: #ffffff;
            font-weight: 900;
            text-decoration: none;
        }

        @media (max-width: 800px) {
            .hero {
                padding: 34px 24px;
            }

            .hero h1 {
                font-size: 34px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <a href="{{ url('/') }}" class="back">← Volver al inicio</a>

    <section class="hero">
        <h1>Soporte ZIGO</h1>
        <p>
            Te ayudamos con dudas sobre cotizaciones, pagos, generación de guías,
            rastreo de paquetes, incidencias de entrega y seguimiento de solicitudes empresariales.
        </p>
    </section>

    <section class="grid">
        <div class="card">
            <h3>Ayuda con guías</h3>
            <p>
                Soporte para generación, descarga, datos del remitente, datos del destinatario
                o problemas al crear una guía.
            </p>
        </div>

        <div class="card">
            <h3>Rastreo e incidencias</h3>
            <p>
                Revisión de paquetes sin movimiento, retrasos, devoluciones, entregas no reconocidas
                o incidencias reportadas por la paquetería.
            </p>
        </div>

        <div class="card">
            <h3>Pagos y saldo</h3>
            <p>
                Aclaraciones sobre pagos realizados, saldo prepago, cargos, comprobantes
                o problemas al confirmar una operación.
            </p>
        </div>

        <div class="card">
            <h3>Empresas e integraciones</h3>
            <p>
                Atención para cuentas empresariales, volumen recurrente, API Hub,
                reglas comerciales y operación logística digital.
            </p>
        </div>
    </section>

    <section class="cta">
        <h2>¿Necesitas ayuda?</h2>
        <p>
            Ten a la mano tu número de guía, correo registrado, datos del envío
            y una breve descripción del problema para poder atenderte más rápido.
        </p>

        <a href="https://wa.me/5210000000000" class="btn" target="_blank">
            Contactar por WhatsApp
        </a>
    </section>
</div>
</body>
</html>