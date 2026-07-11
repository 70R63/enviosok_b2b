<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZIGO | Cotiza y genera tus guías</title>

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
            padding: 8px 24px 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav img {
            width: auto;
            object-fit: contain;
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
            padding: 4px 24px 30px;
        }

        .quote-title {
            text-align: center;
            font-size: 24px;
            font-weight: 900;
            margin-bottom: 16px;
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
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 32px;
            padding: 40px;
            backdrop-filter: blur(8px);
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

.hero-logo{
    width:100%;
    max-width:500px;
    display:block;
    margin:auto;
    object-fit:contain;
}

.hero-logo{
    animation:zigoFloat 5s ease-in-out infinite;
}

@keyframes zigoFloat{
    0%{transform:translateY(0)}
    50%{transform:translateY(-8px)}
    100%{transform:translateY(0)}
}

.brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
}

.brand-logo {
    height: 92px;
    width: auto;
    object-fit: contain;
    animation: zigoEnter .8s ease-out;
    transition: transform .35s ease, filter .35s ease;
}

.brand:hover .brand-logo {
    transform: translateX(4px) scale(1.03);
    filter:
        drop-shadow(0 0 8px rgba(56,189,248,.8))
        drop-shadow(0 0 14px rgba(37,99,235,.6));
}

.brand-tagline {
    margin-top: -8px;
    font-size: 10px;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: #eaf2ff;
    font-weight: 900;
    line-height: 1.2;
}

.feature-card-link{
    display:block;
    color:inherit;
    text-decoration:none;
    transition:.2s ease;
}

.feature-card-link:hover{
    transform:translateY(-4px);
    box-shadow:0 18px 40px rgba(37,99,235,.18);
}

.feature-card-link span{
    display:inline-block;
    margin-top:14px;
    color:#2563eb;
    font-weight:900;
}

.faq-section{
    padding:80px 24px;
    background:#f8fafc;
}

.faq-section h2{
    text-align:center;
    font-size:38px;
    font-weight:900;
    margin:0 0 36px;
    color:#111827;
}

.faq-grid{
    max-width:1100px;
    margin:0 auto;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
}

.faq-item{
    background:white;
    border-radius:18px;
    padding:24px;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
}

.faq-item h3{
    margin:0 0 10px;
    color:#1d4ed8;
}

.faq-item p{
    margin:0;
    color:#475569;
    line-height:1.6;
}

@media(max-width:700px){
    .faq-grid{
        grid-template-columns:1fr;
    }
}

@keyframes zigoEnter {
    from {
        opacity: 0;
        transform: translateX(-28px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
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

    .nav nav {
    display: flex;
    align-items: center;
    gap: 28px;
}

.nav nav a,
.nav-logout {
    color: #fff;
    font-weight: 800;
    text-decoration: none;
    font-size: 14px;
}

.nav-logout {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}


}
		
    </style>
</head>
<body>

    <div class="top">
        <header class="nav">
            <a href="/" class="brand">
                <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="brand-logo">
                <div class="brand-tagline">
                    Tecnología • Logística • Conexión
                </div>
            </a>

            <nav>
    <a href="#nosotros">Nosotros</a>
    <a href="#paqueterias">Paquetería</a>
    <a href="#faq">FAQ'S</a>

    @auth
        <a href="{{ url('/dashboard') }}">Mi cuenta</a>

        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="nav-logout">
                            Cerrar sesión
                        </button>
                    </form>
                @else
                    <a href="{{ url('/login') }}">Iniciar sesión</a>
                    <a href="{{ route('b2c.register') }}">Registro</a>
                @endauth
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
                    <input type="hidden" id="ciudad_origen" name="ciudad_origen">
                    <input type="hidden" id="estado_origen" name="estado_origen">
                    <div id="colonias_origen_list" class="suggestions"></div>

                    <small id="cp_origen_msg" style="display:none; color:#fff; margin-top:6px;">
                        Valida <a href="https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/Descarga.aspx" target="_blank" style="color:#facc15;">aquí</a> tu código postal
                    </small>
                </div>

                <div class="field autocomplete-wrap">
                    <label>Código postal destino</label>

                    <input type="text" id="cp_destino" name="cp_destino" placeholder="Código postal destino" maxlength="120" autocomplete="off">

                    <input type="hidden" id="colonia_destino" name="colonia_destino">
                    <input type="hidden" id="ciudad_destino" name="ciudad_destino">
                    <input type="hidden" id="estado_destino" name="estado_destino">
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

            @if(isset($cotizacion_id) && isset($opciones))
                <div style="max-width:1180px;margin:25px auto 0;padding:0 24px;">
                    <div style="background:white;color:#111827;border-radius:20px;padding:24px;box-shadow:0 12px 30px rgba(0,0,0,.12);">
                        <h2 style="margin-top:0;color:#111827;text-align:center;">Opciones disponibles</h2>

                        @foreach($opciones as $opcion)
                            <form method="POST" action="{{ route('b2c.seleccionar', $cotizacion_id) }}"
                                style="display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:18px;align-items:center;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-top:12px;">
                                @csrf

                                <input type="hidden" name="logistico" value="{{ $opcion['logistico'] }}">
                                <input type="hidden" name="servicio" value="{{ $opcion['servicio'] }}">
                                <input type="hidden" name="precio" value="{{ $opcion['precio'] }}">

                               <div style="display:flex;align-items:center;gap:15px;">
                                    <img
                                        src="{{ asset($opcion['logo']) }}"
                                        alt="{{ $opcion['logistico'] }}"
                                        style="width:90px;height:auto;object-fit:contain;"
                                    >
                                    <div>
                                        <strong>{{ $opcion['logistico'] }}</strong>
                                        <div>{{ $opcion['servicio'] }}</div>
                                    </div>
                                </div>

                                <div>{{ $opcion['entrega'] }}</div>

                                <div style="font-size:22px;font-weight:900;">
                                    ${{ number_format($opcion['precio'], 2) }}
                                </div>

                                <button type="submit"
                                        style="background:#f97316;color:white;border:none;border-radius:10px;padding:12px 22px;font-weight:900;cursor:pointer;">
                                    Comprar
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section style="padding: 70px 20px; background:#ffffff; text-align:center;">
    <h2 style="font-size:42px; margin-bottom:25px; color:#111827;">
        Rastrea tu envío
    </h2>

    <form method="POST" action="{{ route('b2c.rastreo.buscar') }}"
          style="max-width:820px; margin:auto; display:flex; background:white; border-radius:14px; overflow:hidden; box-shadow:0 10px 24px rgba(0,0,0,.16); border:1px solid #e5e7eb;">
        @csrf

        <input
            type="text"
            name="tracking_number"
            placeholder="Ingresa tu número de rastreo"
            required
            style="flex:1; padding:22px; border:none; font-size:20px; outline:none;"
        >

        <button type="submit"
                style="background:#dc2626; color:white; border:none; padding:0 38px; font-size:20px; font-weight:800; cursor:pointer;">
            Rastrear
        </button>
    </form>
</section>

        <section class="hero">
            <div>
                <p>En ZIGO podrás cotizar envíos nacionales en sencillos pasos.</p>
                <h1>Envía paquetes de forma segura y rápida</h1>
                <p>
                    Somos una plataforma de autoservicio digital para cotizar, pagar y generar guías
                    de envío con aliados logísticos nacionales. Ideal para personas, emprendedores y empresas.
                </p>
            </div>

            <div class="hero-card">
                <img src="{{ asset('img/zigo-logo.png') }}"
                    alt="ZIGO"
                    class="hero-logo">
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

            <a href="{{ route('landing.empresas') }}" class="card feature-card-link">
                <h3>Empresas B2B</h3>
                <p>Acceso privado con usuarios, saldos, reportes, direcciones, tarifas y guías para tu operación.</p>
                <span>Conocer solución empresarial →</span>
            </a>
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

    <section class="faq-section" id="faq">
        <h2>Preguntas frecuentes</h2>
        <div class="faq-grid">
            <div class="faq-item">
                <h3>¿Puedo cotizar sin registrarme?</h3>
                <p>Sí. Puedes cotizar como visitante. Para funciones avanzadas o envíos tipo caja, será necesario crear una cuenta o iniciar sesión.</p>
            </div>

            <div class="faq-item">
                <h3>¿Qué tipo de envío puedo hacer?</h3>
                <p>Actualmente puedes operar envíos sencillos y consultar opciones disponibles según cobertura, tipo de paquete y servicio.</p>
            </div>

            <div class="faq-item">
                <h3>¿ZIGO tiene solución para empresas?</h3>
                <p>Sí. ZIGO Empresas permite centralizar usuarios, direcciones, saldos, reportes y generación de guías.</p>
            </div>

            <div class="faq-item">
                <h3>¿Puedo integrar ZIGO a mi sistema?</h3>
                <p>Sí. API Hub permite integrar servicios como códigos postales, colonias, consumo y futuras funciones logísticas.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Comienza a vender y generar guías hoy</h2>
        <p>Compra una guía como usuario final o solicita acceso empresarial.</p>
        <a href="{{ url('/register') }}">Crear cuenta</a>
    </section>

    <footer>
        ZIGO © {{ date('Y') }}. Plataforma de envíos B2C y B2B.
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
            const colonia = item.d_asenta || item.colonia || '';
            const municipio = item.D_mnpio || item.d_mnpio || item.municipio || item.d_ciudad || '';
            const estado = item.d_estado || item.estado || item.codigo_estado || '';

            const texto = `${cp} - ${colonia} - ${municipio} - ${estado}`;

            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.textContent = texto;

            div.addEventListener('click', function () {
                cpInput.value = texto;          // visible como antes
                hiddenColonia.value = colonia;  // limpio para Estafeta

                if (cpInputId === 'cp_origen') {
                    document.getElementById('ciudad_origen').value = municipio;
                    document.getElementById('estado_origen').value = estado;
                }

                if (cpInputId === 'cp_destino') {
                    document.getElementById('ciudad_destino').value = municipio;
                    document.getElementById('estado_destino').value = estado;
                }

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