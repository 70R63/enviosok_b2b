<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Opciones disponibles - ZIGO</title>
    <style>
    body{
        margin:0;
        font-family:Arial,sans-serif;
        background:#f4f7fb;
        color:#111827;
    }

    .layout{
        display:grid;
        grid-template-columns:260px 1fr;
        min-height:100vh;
    }

    .sidebar{
        background:#2563eb;
        color:white;
        padding:30px;
    }

    .logo{
        margin-bottom:25px;
        text-align:center;
    }

    .zigo-img{
        width:180px;
        max-width:100%;
        display:block;
        margin:0 auto;
    }

    .zigo-tagline{
        margin-top:8px;
        font-size:10px;
        letter-spacing:2px;
        color:#dce7f7;
        text-transform:uppercase;
        font-weight:600;
        line-height:1.5;
    }

    .menu a,
    .logout-btn{
        display:block;
        color:white;
        text-decoration:none;
        font-weight:800;
        margin:18px 0;
        background:rgba(255,255,255,.12);
        padding:14px;
        border-radius:12px;
    }

    .logout-btn{
        width:100%;
        border:none;
        text-align:left;
        cursor:pointer;
        font-size:16px;
    }

    .content {
        width: 100%;
        max-width: 1540px;
        margin: 0 auto;
        padding: 28px 30px;
    }

    .title{
        font-size:36px;
        font-weight:900;
        margin-bottom:8px;
    }

    .subtitle{
        color:#64748b;
        margin-bottom:30px;
    }

    /* ===== NUEVO LAYOUT ===== */

    .options-layout {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(360px, 410px);
        gap: 22px;
        align-items: start;
    }

    .card {
        background: #fff;
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 8px 22px rgba(15,23,42,.08);
    }

    .side-card{
        position:sticky;
        top:25px;
    }

    h1{
        text-align:center;
        margin-top:0;
        margin-bottom:24px;
        font-size:28px;
    }

    h2{
        margin-top:0;
        font-size:28px;
        font-weight:900;
    }

    .option{
        display:grid;
        grid-template-columns:120px minmax(210px,1.3fr) minmax(220px,1fr) 150px 150px;
        align-items:center;
        gap:18px;
        border:1px solid #e5e7eb;
        border-radius:14px;
        padding:18px;
        margin-bottom:14px;
    }

    .logo-carrier{
        max-width:110px;
        max-height:42px;
    }

    .name{
        font-weight:900;
        font-size:18px;
    }

    .service{
        font-size:16px;
    }

    .provider-note{
        margin-top:6px;
        font-size:12px;
        color:#64748b;
        font-weight:700;
    }

    .extended-area{
        display:inline-block;
        margin-top:7px;
        border-radius:999px;
        padding:4px 9px;
        background:#fff7ed;
        color:#9a3412;
        font-size:12px;
        font-weight:900;
    }

    .quote-facts{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:7px 12px;
        color:#475569;
        font-size:13px;
        line-height:1.35;
    }

    .quote-facts strong{color:#0f172a}

    .quote-modal{
        display:none;
        position:fixed;
        inset:0;
        z-index:10000;
        padding:20px;
        background:rgba(15,23,42,.62);
        align-items:center;
        justify-content:center;
    }

    .quote-modal.is-open{display:flex}

    .quote-modal-card{
        width:min(620px,100%);
        max-height:calc(100vh - 40px);
        overflow:auto;
        background:#fff;
        border-radius:20px;
        padding:26px;
        box-shadow:0 24px 60px rgba(15,23,42,.3);
    }

    .quote-modal-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
        margin:20px 0;
    }

    .quote-modal-item{
        padding:12px;
        border-radius:12px;
        background:#f8fafc;
        color:#475569;
        font-size:13px;
    }

    .quote-modal-item strong{
        display:block;
        margin-top:4px;
        color:#0f172a;
        font-size:15px;
    }

    .quote-modal-price{
        padding:16px;
        border-radius:14px;
        background:#fff7ed;
        color:#9a3412;
        font-size:24px;
        font-weight:900;
        text-align:center;
    }

    .quote-modal-actions{display:flex;gap:10px;margin-top:18px}
    .quote-modal-actions .btn{flex:1}
    .quote-modal-close{background:#e2e8f0;color:#334155}

    .price{
        font-size:28px;
        font-weight:900;
        text-align:right;
    }

    .actions{
        display:flex;
        flex-direction:column;
        gap:6px;
        align-items:stretch;
    }

    .btn{
        width:100%;
        border:none;
        border-radius:12px;
        padding:14px 20px;
        font-size:15px;
        font-weight:900;
        cursor:pointer;
        background:#ea580c;
        color:white;
    }

    .btn-link-saldo{
        background:transparent;
        border:none;
        color:#16a34a;
        font-weight:900;
        cursor:pointer;
        padding:2px 0 0;
        font-size:14px;
        text-decoration:underline;
    }

    .summary{
        margin-top:18px;
        padding:18px;
        border-radius:14px;
        background:#f8fafc;
        font-size:14px;
    }

    .summary p{
        margin:12px 0;
        line-height:1.5;
    }

    .saldo{
        background:#ecfdf5;
        border:1px solid #bbf7d0;
        color:#14532d;
        font-weight:900;
    }

    .modal-pago{
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.45);
        z-index:9999;
        align-items:center;
        justify-content:center;
    }

    .modal-card{
        background:white;
        border-radius:18px;
        padding:28px;
        width:380px;
        box-shadow:0 20px 40px rgba(0,0,0,.25);
    }

    .modal-text{
        color:#64748b;
        margin-bottom:20px;
    }

    .modal-cancel{
        margin-top:14px;
        background:none;
        border:none;
        cursor:pointer;
        font-weight:800;
        color:#475569;
    }

    .btn-saldo{
        background:#16a34a;
        color:white;
    }

    /* ===== RESPONSIVE ===== */

    @media(max-width:900px){

        .layout{
            grid-template-columns:1fr;
        }

        .sidebar{
            display:none;
        }

        .options-layout{
            grid-template-columns:1fr;
        }

        .side-card{
            position:static;
        }

        .option{
            grid-template-columns:1fr;
            text-align:center;
        }

        .price{
            text-align:center;
        }

        .logo-carrier{
            margin:auto;
        }

        .quote-facts{text-align:left}
    }

    @media(max-width:560px){
        .content{padding:20px 14px}
        .card{padding:15px}
        .quote-facts,.quote-modal-grid{grid-template-columns:1fr}
        .quote-modal-actions{flex-direction:column-reverse}
        .title{font-size:29px}
    }
</style>
</head>
<body>
@if(session('rate_error'))
    <div role="alert" style="margin:16px;padding:14px;border-radius:10px;background:#fee2e2;color:#991b1b;font-weight:700">
        {{ session('rate_error') }}
    </div>
@endif

<div class="layout">
    <aside class="sidebar">
        <div class="logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">
            <div class="zigo-tagline">Tecnología • Logística • Conexión</div>
        </div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Selecciona tu paquetería</div>
        <div class="subtitle">Elige una opción disponible para continuar al pago.</div>

        <div class="options-layout">
            <div class="card">
                <h1>Opciones disponibles</h1>

                @foreach($opciones as $opcion)
                    <form
                        method="POST"
                        action="{{ route('b2c.seleccionar.nuevo', $cotizacion->id) }}"
                        class="option"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="logistico"
                            value="{{ $opcion['logistico'] }}"
                        >

                        <input
                            type="hidden"
                            name="servicio"
                            value="{{ $opcion['servicio'] }}"
                        >

                        <div>
                            @if($opcion['logistico'] === 'FedEx')
                                <img
                                    class="logo-carrier"
                                    src="{{ asset('img/fedex.png') }}"
                                    alt="FedEx"
                                >
                            @elseif($opcion['logistico'] === 'Estafeta')
                                <img
                                    class="logo-carrier"
                                    src="{{ asset('img/estafeta.png') }}"
                                    alt="Estafeta"
                                >
                            @elseif($opcion['logistico'] === 'DHL')
                                <img
                                    class="logo-carrier"
                                    src="{{ asset('img/dhl.png') }}"
                                    alt="DHL"
                                >
                            @endif
                        </div>

                        <div>
                            <div class="name">
                                {{ $opcion['logistico'] }}
                            </div>

                            <div class="service">
                                {{ $opcion['servicio'] }}
                            </div>

                            @if(($opcion['provider_source'] ?? null) === 'xperta')
                                <div class="provider-note">
                                    Cobertura y tarifa consultadas en línea
                                </div>
                            @endif

                            @if($opcion['is_reexpedition'] ?? $opcion['extended_area'] ?? false)
                                <div class="extended-area">
                                    Zona extendida · cargo incluido
                                </div>
                            @endif
                        </div>

                        <div class="quote-facts">
                            <span><strong>Entrega:</strong> {{ $opcion['estimated_delivery_date'] ?: ($opcion['entrega'] ?? 'Por confirmar') }}</span>
                            <span><strong>Periodicidad:</strong> {{ $opcion['periodicity_name'] ?: 'Según cobertura' }}</span>
                            <span><strong>Días:</strong> {{ implode(', ', $opcion['operating_days'] ?? []) ?: 'Por confirmar' }}</span>
                            <span><strong>Zona:</strong> {{ $opcion['zone_code'] ?: 'No especificada' }}</span>
                        </div>

                        <div class="price">
                            ${{ number_format($opcion['precio'], 2) }}
                        </div>

                        <div class="actions">
                            <button
                                class="btn js-quote-summary"
                                type="button"
                                data-service="{{ $opcion['servicio'] }}"
                                data-origin="{{ $cotizacion->cp_origen }}"
                                data-destination="{{ $cotizacion->cp_destino }}"
                                data-weight="{{ $opcion['weight_billable'] }} kg"
                                data-dimensions="{{ $opcion['dimensions'] }}"
                                data-insurance="{{ $opcion['insurance_enabled'] ? 'Incluido' : 'No incluido' }}"
                                data-extended-area="{{ ($opcion['is_reexpedition'] ?? $opcion['extended_area'] ?? false) ? 'Sí, cargo incluido' : 'No' }}"
                                data-delivery="{{ $opcion['estimated_delivery_date'] ?: ($opcion['entrega'] ?? 'Por confirmar') }}"
                                data-price="${{ number_format($opcion['commercial_price'], 2) }} MXN"
                            >
                                Seleccionar
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>

            <aside class="card side-card">
                <h2>Resumen</h2>

                <div class="summary saldo">
                    Saldo disponible:<br>
                    ${{ number_format($saldo->saldo ?? 0, 2) }} MXN
                </div>

                <div class="summary">
                    <p><strong>Origen:</strong><br>{{ $cotizacion->cp_origen }} - {{ $cotizacion->colonia_origen }}</p>
                    <p><strong>Destino:</strong><br>{{ $cotizacion->cp_destino }} - {{ $cotizacion->colonia_destino }}</p>
                    <p><strong>Peso:</strong> {{ $cotizacion->peso }} kg</p>
                    <p><strong>Medidas:</strong> {{ $cotizacion->medidas }}</p>
                </div>
            </aside>
        </div>
    </main>

    <div class="quote-modal" id="quote-summary-modal" role="dialog" aria-modal="true" aria-labelledby="quote-modal-title">
        <div class="quote-modal-card">
            <h2 id="quote-modal-title">Resumen de tu cotización</h2>
            <p class="modal-text">Verifica los datos antes de continuar.</p>
            <div class="quote-modal-grid" id="quote-modal-details"></div>
            <div class="quote-modal-price" id="quote-modal-price"></div>
            <div class="quote-modal-actions">
                <button class="btn quote-modal-close" type="button" id="quote-modal-close">Regresar</button>
                <button class="btn" type="button" id="quote-modal-confirm">Continuar</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('quote-summary-modal');
            const details = document.getElementById('quote-modal-details');
            const price = document.getElementById('quote-modal-price');
            let selectedForm = null;
            const labels = {
                service: 'Servicio', origin: 'CP origen', destination: 'CP destino',
                weight: 'Peso facturable', dimensions: 'Dimensiones', insurance: 'Seguro',
                extendedArea: 'Área extendida', delivery: 'Fecha estimada'
            };

            document.querySelectorAll('.js-quote-summary').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedForm = button.closest('form');
                    details.replaceChildren();
                    Object.entries(labels).forEach(([key, label]) => {
                        const item = document.createElement('div');
                        item.className = 'quote-modal-item';
                        item.append(document.createTextNode(label));
                        const value = document.createElement('strong');
                        value.textContent = button.dataset[key] || 'No especificado';
                        item.append(value);
                        details.append(item);
                    });
                    price.textContent = button.dataset.price;
                    modal.classList.add('is-open');
                    document.getElementById('quote-modal-confirm').focus();
                });
            });

            const close = () => modal.classList.remove('is-open');
            document.getElementById('quote-modal-close').addEventListener('click', close);
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
            document.getElementById('quote-modal-confirm').addEventListener('click', () => {
                if (selectedForm) selectedForm.requestSubmit();
            });
        })();
    </script>
</body>
</html>
