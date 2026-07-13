<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>API Hub | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #0b1220;
            background: #f6f8fc;
        }

        .page {
            max-width: 1120px;
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
            background: linear-gradient(135deg, #4361ee, #4b3fd1);
            color: #ffffff;
            border-radius: 28px;
            padding: 56px;
            box-shadow: 0 24px 70px rgba(67, 97, 238, .22);
        }

        .hero h1 {
            font-size: 46px;
            margin: 0 0 18px;
            line-height: 1.08;
        }

        .hero p {
            max-width: 760px;
            font-size: 18px;
            line-height: 1.6;
            margin: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-top: 34px;
        }

        .card {
            background: #ffffff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 21px;
        }

        .card p {
            margin: 0;
            color: #475569;
            line-height: 1.6;
        }

        .section {
            margin-top: 42px;
            background: #ffffff;
            border-radius: 24px;
            padding: 34px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .07);
        }

        .section h2 {
            margin: 0 0 16px;
            font-size: 30px;
        }

        .section ul {
            margin: 0;
            padding-left: 22px;
            color: #334155;
            line-height: 1.8;
        }

        .cta {
            margin-top: 34px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 15px 24px;
            border-radius: 14px;
            font-weight: 900;
            text-decoration: none;
        }

        .btn-primary {
            background: #f97316;
            color: #ffffff;
        }

        .btn-secondary {
            background: #ffffff;
            color: #4361ee;
        }

        @media (max-width: 900px) {
            .hero {
                padding: 36px 24px;
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
        <h1>API Hub ZIGO</h1>
        <p>
            Integra tus sistemas con ZIGO para consultar códigos postales, validar cobertura,
            cotizar envíos, generar guías digitales y consultar rastreos desde una conexión API.
        </p>

        <div class="cta">
            <a href="{{ route('landing.empresas') }}" class="btn btn-primary">Solicitar integración</a>
            <a href="{{ url('/') }}" class="btn btn-secondary">Cotizar como usuario</a>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h3>Códigos postales</h3>
            <p>
                Consulta estados, municipios, ciudades, colonias y cobertura logística desde tu sistema.
            </p>
        </div>

        <div class="card">
            <h3>Cotización logística</h3>
            <p>
                Centraliza tarifas, reglas comerciales y opciones de envío para tus clientes o sucursales.
            </p>
        </div>

        <div class="card">
            <h3>Guías y rastreo</h3>
            <p>
                Automatiza la generación de guías y consulta el estado de tus envíos desde una sola plataforma.
            </p>
        </div>
    </section>

    <section class="section">
        <h2>¿Para quién es?</h2>
        <ul>
            <li>Tiendas en línea que necesitan automatizar sus envíos.</li>
            <li>Empresas con alto volumen de guías mensuales.</li>
            <li>Sistemas ERP, CRM o e-commerce que requieren integración logística.</li>
            <li>Desarrolladores que buscan conectar servicios de paquetería sin construir todo desde cero.</li>
        </ul>
    </section>

    <section class="section">
        <h2>Servicios disponibles</h2>
        <ul>
            <li>Consulta de código postal y colonias.</li>
            <li>Validación de cobertura por zona.</li>
            <li>Cotización de envíos nacionales.</li>
            <li>Generación de guías digitales.</li>
            <li>Consulta de rastreo.</li>
            <li>Control de consumo por cliente API.</li>
        </ul>
    </section>
</div>
</body>
</html>