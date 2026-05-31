<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnviosOK | Cotiza y genera tus guías</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #ffffff;
        }

        .top {
            background: linear-gradient(90deg, #2563eb, #3b82f6, #4338ca);
            color: white;
        }

        .nav {
            max-width: 1180px;
            margin: auto;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav img {
            height: 42px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            margin-left: 22px;
            font-size: 15px;
            font-weight: 600;
        }

        .quote-box {
            max-width: 1180px;
            margin: auto;
            padding: 18px 24px 34px;
        }

        .quote-title {
            text-align: center;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .quote-form {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .field input,
        .field select {
            width: 100%;
            height: 38px;
            border-radius: 6px;
            border: none;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .btn-yellow {
            height: 38px;
            border: none;
            border-radius: 6px;
            background: #facc15;
            color: #1f2937;
            font-weight: 800;
            cursor: pointer;
        }

        .hero {
            max-width: 1180px;
            margin: auto;
            padding: 70px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero h1 {
            font-size: 46px;
            line-height: 1.1;
            margin: 0 0 22px;
            color: white;
        }

        .hero p {
            font-size: 18px;
            line-height: 1.7;
            color: white;
        }

        .hero-card {
            background: white;
            border-radius: 32px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,.18);
        }

        .hero-card img {
            width: 100%;
            border-radius: 28px;
        }

        .section {
            max-width: 1180px;
            margin: auto;
            padding: 70px 24px;
        }

        .section h2 {
            font-size: 34px;
            margin-bottom: 18px;
            text-align: center;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-top: 35px;
        }

        .card {
            padding: 28px;
            border-radius: 20px;
            background: #f8fafc;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
        }

        .card h3 {
            margin-top: 0;
            color: #2563eb;
        }

        .logos {
            display: flex;
            justify-content: center;
            gap: 45px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 35px;
        }

        .logos img {
            max-height: 55px;
            max-width: 160px;
        }

        .cta {
            background: #facc15;
            padding: 55px 24px;
            text-align: center;
        }

        .cta h2 {
            font-size: 34px;
            margin: 0 0 12px;
        }

        .cta a {
            display: inline-block;
            margin-top: 22px;
            padding: 14px 26px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 800;
        }

        .whatsapp {
            position: fixed;
            right: 24px;
            bottom: 24px;
            background: #22c55e;
            color: white;
            padding: 14px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }

        footer {
            padding: 28px;
            text-align: center;
            background: #111827;
            color: white;
        }

        @media (max-width: 900px) {
            .quote-form,
            .hero,
            .cards {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 34px;
            }

            .nav {
                flex-direction: column;
                gap: 16px;
            }

            .nav a {
                margin: 0 8px;
            }
        }
    </style>
</head>
<body>

    <div class="top">
        <header class="nav">
            <a href="/">
                <img src="{{ asset('img/Envios_OK_variante_C4x.png') }}" alt="EnviosOK">
            </a>

            <nav>
                <a href="#nosotros">Nosotros</a>
                <a href="#paqueterias">Paquetería</a>
                <a href="#faq">FAQ'S</a>
                <a href="{{ url('/login') }}">Iniciar sesión</a>
                <a href="{{ url('/register') }}">Registro</a>
            </nav>
        </header>

        <section class="quote-box">
            <div class="quote-title">Cotiza gratis tu envío</div>

            <form class="quote-form" method="GET" action="#">
                <div class="field">
                    <label>Código postal origen</label>
                    <input type="text" name="cp_origen" placeholder="Código postal origen">
                </div>

                <div class="field">
                    <label>Código postal destino</label>
                    <input type="text" name="cp_destino" placeholder="Código postal destino">
                </div>

                <div class="field">
                    <label>Tipo de envío</label>
                    <select name="tipo_envio">
                        <option value="caja">Caja</option>
                        <option value="sobre">Sobre</option>
                    </select>
                </div>

                <div class="field">
                    <label>Peso (kg)</label>
                    <input type="number" name="peso" placeholder="Kg(s)">
                </div>

                <div class="field">
                    <label>Tamaño de caja (cm)</label>
                    <input type="text" name="medidas" placeholder="Alto x Largo x Ancho">
                </div>

                <button class="btn-yellow" type="submit">Cotizar envío</button>
            </form>
        </section>

        <section class="hero">
            <div>
                <p>En EnviosOK podrás cotizar envíos nacionales en sencillos pasos.</p>
                <h1>Envía paquetes de forma segura y rápida</h1>
                <p>
                    Somos una plataforma de autoservicio digital para cotizar, pagar y generar guías
                    de envío con aliados logísticos nacionales. Ideal para personas, emprendedores y empresas.
                </p>
            </div>

            <div class="hero-card">
                <img src="{{ asset('img/enviosok.jpeg') }}" alt="Envía paquetes con EnviosOK">
            </div>
        </section>
    </div>

    <section class="section" id="nosotros">
        <h2>Todo para tus envíos en un solo lugar</h2>

        <div class="cards">
            <div class="card">
                <h3>B2C sin registro</h3>
                <p>Cotiza, paga en línea y genera tu guía sin crear cuenta.</p>
            </div>

            <div class="card">
                <h3>Perfil personal</h3>
                <p>Guarda direcciones, consulta historial y descarga tus guías cuando lo necesites.</p>
            </div>

            <div class="card">
                <h3>Empresas B2B</h3>
                <p>Acceso privado con usuarios, saldos, reportes, tarifas y guías masivas.</p>
            </div>
        </div>
    </section>

    <section class="section" id="paqueterias">
        <h2>Paqueterías integradas</h2>

        <div class="logos">
            <img src="{{ asset('img/fedex.png') }}" alt="FedEx">
            <img src="{{ asset('img/estafeta.png') }}" alt="Estafeta">
            <img src="{{ asset('img/dhl.png') }}" alt="DHL">
            <img src="{{ asset('img/ups.png') }}" alt="UPS">
        </div>
    </section>

    <section class="cta">
        <h2>Comienza a vender y generar guías hoy</h2>
        <p>Compra una guía como usuario final o solicita acceso empresarial.</p>
        <a href="{{ url('/register') }}">Crear cuenta</a>
    </section>

    <footer>
        EnviosOK © {{ date('Y') }}. Plataforma de envíos B2C y B2B.
    </footer>

    <a class="whatsapp" href="#" target="_blank">¡¡Estamos aquí para ayudarte!!</a>

</body>
</html>