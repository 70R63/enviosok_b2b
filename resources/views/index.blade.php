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
		
		.autocomplete-wrap {
    position: relative;
}

.suggestions {
    display: none;
    position: absolute;
    top: 68px;
    left: 0;
    width: 520px;
    max-height: 230px;
    overflow-y: auto;

    background: #ffffff !important;
    color: #111827 !important;

    border-radius: 8px;
    box-shadow: 0 14px 35px rgba(0,0,0,.25);
    z-index: 9999;
}

.suggestion-item {
    padding: 11px 14px;
    font-size: 14px;
    cursor: pointer;
    border-bottom: 1px solid #e5e7eb;
    white-space: normal;
}

.suggestion-item:hover {
    background: #f3f4f6;
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

    .suggestions {
        width: 100%;
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

            <form class="quote-form" method="POST" action="{{ route('b2c.cotizar') }}">
    @csrf
                <div class="field autocomplete-wrap">
    <label>Código postal origen</label>

    <input type="text" id="cp_origen" name="cp_origen" placeholder="Código postal origen" maxlength="120" autocomplete="off">

    <input type="hidden" id="colonia_origen" name="colonia_origen">
    <div id="colonias_origen_list" class="suggestions"></div>

    <small id="cp_origen_msg" style="display:none; color:#fff; margin-top:6px;">
        Valida <a href="https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/Descarga.aspx" target="_blank" style="color:#facc15;">aquí</a> tu código postal
    </small>
</div>

                <div class="field autocomplete-wrap">
    <label>Código postal destino</label>

    <input type="text" id="cp_destino" name="cp_destino" placeholder="Código postal destino" maxlength="120" autocomplete="off">

    <input type="hidden" id="colonia_destino" name="colonia_destino">
    <div id="colonias_destino_list" class="suggestions"></div>

    <small id="cp_destino_msg" style="display:none; color:#fff; margin-top:6px;">
        Valida <a href="https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/Descarga.aspx" target="_blank" style="color:#facc15;">aquí</a> tu código postal
    </small>
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
	
	<script>
    async function cargarColonias(cpInputId, listId, msgId, hiddenColoniaId) {
    const cpInput = document.getElementById(cpInputId);
    const list = document.getElementById(listId);
    const msg = document.getElementById(msgId);
    const hiddenColonia = document.getElementById(hiddenColoniaId);

    const cp = cpInput.value.trim();

    list.innerHTML = '';
    list.style.display = 'none';
    msg.style.display = 'none';
    hiddenColonia.value = '';

    if (cp.length !== 5 || !/^\d{5}$/.test(cp)) {
        return;
    }

    try {
        const response = await fetch(`/b2c/cp/colonias?cp=${encodeURIComponent(cp)}`);
        const json = await response.json();
        const colonias = json?.data || json?.success?.data || [];

        if (!Array.isArray(colonias) || colonias.length === 0) {
            msg.style.display = 'block';
            return;
        }

        colonias.forEach(item => {
            const cp = item.d_codigo || '';
            const colonia = item.d_asenta || '';
            const municipio = item.d_mnpio || '';
            const estado = item.d_estado || '';

            const texto = `${cp} - ${colonia} - ${municipio} - ${estado}`;

            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.textContent = texto;

            div.addEventListener('click', function () {
    cpInput.value = texto;
    hiddenColonia.value = texto;
    list.style.display = 'none';
});

            list.appendChild(div);
        });

        list.style.display = 'block';
    } catch (error) {
        msg.style.display = 'block';
    }
}

document.getElementById('cp_origen').addEventListener('keyup', function () {
    if (/^\d{5}$/.test(this.value.trim())) {
        cargarColonias('cp_origen', 'colonias_origen_list', 'cp_origen_msg', 'colonia_origen');
    }
});

document.getElementById('cp_destino').addEventListener('keyup', function () {
    if (/^\d{5}$/.test(this.value.trim())) {
        cargarColonias('cp_destino', 'colonias_destino_list', 'cp_destino_msg', 'colonia_destino');
    }
});
</script>

</body>
</html>