<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout | ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{
            display:grid;
            grid-template-columns:260px 1fr;
            min-height:100vh;
        }

        body.guest .layout{
            display:block;
        }

        body.guest .content{
            max-width:1200px;
            margin:0 auto;
        }
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px;}
        .zigo-logo{
            text-align:center;
            padding:8px;
        }

        .zigo-img{
            width:180px;
            max-width:100%;
            display:block;
            margin:0 auto;
            animation:zigoEntrance 1s ease-out;
            transition:all .35s ease;
        }

        .zigo-logo:hover .zigo-img{
            transform:scale(1.03);
            filter:
                drop-shadow(0 0 8px rgba(0,255,255,.45))
                drop-shadow(0 0 14px rgba(0,128,255,.35));
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

        @keyframes zigoEntrance{
            from{
                opacity:0;
                transform:translateX(-35px);
            }
            to{
                opacity:1;
                transform:translateX(0);
            }
        }
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:28px 34px}
        .title{font-size:32px;font-weight:900;margin-bottom:6px;color:#111827}
        .subtitle{color:#64748b;margin-bottom:22px}
        .grid{
            display:grid;
            grid-template-columns:minmax(0,1fr) minmax(0,1fr) 330px;
            gap:18px;
            align-items:start;
        }

        .summary-card{
            grid-column:3;
            grid-row:1 / span 2;
        }

        .package-card{
            grid-column:1 / span 2;
        }

        body.guest .content{
            max-width:1320px;
            margin:0 auto;
            padding:28px 24px;
        }

        @media(max-width:1150px){
            .grid{
                grid-template-columns:1fr;
            }

            .summary-card,
            .package-card{
                grid-column:auto;
                grid-row:auto;
            }
        }
        .card{background:white;border-radius:16px;padding:20px;box-shadow:0 8px 20px rgba(0,0,0,.06);margin-bottom:16px}
        h2{margin-top:0}
        .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:11px 14px}
        .full{grid-column:1 / -1}
        label{display:block;font-weight:800;font-size:13px;margin-bottom:5px}
        input{width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 11px;box-sizing:border-box;font-size:14px}
        input[readonly]{background:#f8fafc;color:#475569}
        .summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:15px}
        .total{border-top:1px solid #e5e7eb;padding-top:16px;font-size:24px;font-weight:900}
        .btn{width:100%;border:none;background:#f97316;color:white;padding:15px;border-radius:12px;font-weight:900;cursor:pointer;font-size:16px;margin-top:18px}
        .muted{color:#64748b;font-size:13px}
        @media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{display:none}.grid,.form-grid{grid-template-columns:1fr}}

        .saldo-box {
            margin-top: 20px;
            padding: 18px;
            border: 1px solid #dbe2ea;
            border-radius: 14px;
            background: #f8fafc;
        }

        .saldo-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .saldo-line span {
            font-size: 15px;
            font-weight: 600;
            color: #334155;
        }

        .saldo-line strong {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
        }

        .saldo-btn {
            width: 100%;
            margin-top: 14px;
            background: #16a34a !important;
            color: white !important;
            font-weight: 900;
            font-size: 16px;
            height: 48px;
            border-radius: 12px;
        }

        .saldo-error {
            margin-top: 12px;
            color: #991b1b;
            font-weight: 800;
        }

        .saldo-recargar {
            display: block;
            margin-top: 12px;
            text-align: center;
            background: #2563eb;
            color: white;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 900;
        }

        .summary-row{
            display:grid;
            grid-template-columns:95px 1fr;
            gap:10px;
            align-items:start;
            margin-bottom:12px;
        }

        .summary-row span{
            color:#111827;
        }

        .summary-row strong{
            text-align:right;
            line-height:1.25;
        }

        .summary-item {
            display:flex;
            justify-content:space-between;
            gap:12px;
            margin-bottom:12px;
        }

        body.guest .content{
            max-width:1200px;
            margin:0 auto;
            padding:40px 24px;
        }

        .card h2{
            font-size:23px;
            margin-bottom:16px;
        }

        .summary-row{
            margin-bottom:9px;
            font-size:14px;
        }

        .total{
            font-size:22px;
        }

        .btn{
            padding:13px;
            font-size:15px;
        }

        .saldo-box{
            margin-top:16px;
            padding:15px;
        }

        .saldo-line strong{
            font-size:20px;
        }

        body.guest .content{
            max-width:1180px;
            margin:0 auto;
            padding:30px 24px;
        }

        body.guest .title{
            font-size:32px;
        }

        body.guest .card{
            padding:20px;
        }

        @media(max-width:900px){
            .layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .grid,.form-grid{grid-template-columns:1fr}
            .content{padding:22px 16px}
        }

        .grid{
            display:grid !important;
            grid-template-columns:minmax(0,1fr) minmax(0,1fr) 330px !important;
            gap:18px !important;
            align-items:start !important;
        }

        .summary-card{
            grid-column:3 !important;
            grid-row:1 / 3 !important;
            align-self:start !important;
        }

        .package-card{
            grid-column:1 / 3 !important;
            grid-row:2 !important;
        }

        @media(max-width:1150px){
            .grid{
                grid-template-columns:1fr !important;
            }

            .summary-card,
            .package-card{
                grid-column:auto !important;
                grid-row:auto !important;
            }
        }

    </style>
</head>

@php
    $isPublicCheckout = $cotizacion->referencia === 'LANDING_PUBLICA' && empty($cotizacion->user_id);
@endphp

<body class="{{ auth()->check() && !$isPublicCheckout ? 'auth' : 'guest' }}">

<div class="layout">
    @if(auth()->check() && !$isPublicCheckout)
    <aside class="sidebar">
        <div class="logo zigo-logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">

            <div class="zigo-tagline">
                Tecnología • Logística • Conexión
            </div>
        </div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="#">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a href="#">Adeudos</a>
            <a href="#">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    @endif

    <main class="content">
        <div class="title">Completa los datos de tu guía</div>
        <div class="subtitle">Captura remitente, destinatario y contenido del paquete.</div>

        <form method="POST" action="/b2c/checkout/{{ $cotizacion->id }}">
            @csrf

            <div class="grid">
                    <section class="card">
                        <h2>Remitente</h2>

                        <div class="form-grid">
                            <div>
                                <label>Nombre completo</label>
                                <input type="text" name="remitente_nombre" value="{{ !$isPublicCheckout ? (auth()->user()->name ?? '') : '' }}" required>
                            </div>

                            <div>
                                <label>Teléfono</label>
                                <input type="text" name="remitente_telefono" required>
                            </div>

                            <div class="full">
                                <label>Correo electrónico</label>
                                <input type="email" name="remitente_email" value="{{ !$isPublicCheckout ? (auth()->user()->email ?? '') : '' }}" required>
                            </div>

                            <div class="full">
                                <label>Dirección origen</label>
                                <input type="text" name="remitente_direccion" placeholder="Calle, número exterior/interior" required>

                                <div>
                                    <label>Número exterior</label>
                                    <input type="text" name="remitente_num_ext" required>
                                </div>

                                <div>
                                    <label>Número interior</label>
                                    <input type="text" name="remitente_num_int" placeholder="Opcional">
                                </div>

                                <div>
                                    <label>Ciudad origen</label>
                                    <input type="text" name="ciudad_origen" required>
                                </div>

                                <div>
                                    <label>Estado origen</label>
                                    <input type="text" name="estado_origen" value="MEX" required>
                                </div>
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

                                <div>
                                    <label>Número exterior</label>
                                    <input type="text" name="destinatario_num_ext" required>
                                </div>

                                <div>
                                    <label>Número interior</label>
                                    <input type="text" name="destinatario_num_int" placeholder="Opcional">
                                </div>

                                <div>
                                    <label>Ciudad destino</label>
                                    <input type="text" name="ciudad_destino" required>
                                </div>

                                <div>
                                    <label>Estado destino</label>
                                    <input type="text" name="estado_destino" value="MEX" required>
                                </div>
                            </div>

                            <div class="full">
                                <label>Colonia destino</label>
                                <input type="text" value="{{ $cotizacion->colonia_destino }}" readonly>
                            </div>
                        </div>
                    </section>

                    <section class="card package-card">
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

                <aside class="card summary-card">
                    <h2>Resumen</h2>

                    <div class="summary-row"><span>Mensajería</span><strong>{{ $cotizacion->logistico }}</strong></div>
                    <div class="summary-row"><span>Servicio</span><strong>{{ $cotizacion->servicio }}</strong></div>
                    <div class="summary-row"><span>Origen</span><strong>{{ $cotizacion->cp_origen }}</strong></div>
                    <div class="summary-row"><span>Destino </span><strong>{{ $cotizacion->cp_destino }}</strong></div>
                    <div class="summary-row"><span>Peso</span><strong>{{ $cotizacion->peso }} kg</strong></div>
                    <div class="summary-row"><span>Medidas</span><strong>{{ $cotizacion->medidas ?? 'N/A' }}</strong></div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span>${{ number_format($cotizacion->precio, 2) }} MXN</span>
                    </div>

                    <p class="muted">
                        El pago se realizará en línea. Una vez confirmado, se generará la guía correspondiente.
                    </p>

                    <button class="btn" type="submit">Continuar a pago</button>

                    @if(auth()->check() && !$isPublicCheckout)
                    <div class="saldo-box">

                        <div class="saldo-line">
                            <span>Saldo disponible</span>
                            <strong>${{ number_format($saldo->saldo ?? 0, 2) }}</strong>
                        </div>

                        <div class="saldo-line">
                            <span>Costo guía</span>
                            <strong>${{ number_format($cotizacion->precio ?? 0, 2) }}</strong>
                        </div>

                        @if(($saldo->saldo ?? 0) >= ($cotizacion->precio ?? 0))

                            <button
                                type="submit"
                                form="pagar-saldo-form"
                                class="btn saldo-btn"
                            >
                                Pagar con saldo prepago
                            </button>

                        @else

                            <div class="saldo-error">
                                Saldo insuficiente para pagar esta guía.
                            </div>

                            <a href="{{ route('b2c.prepago') }}" class="saldo-recargar">
                                Recargar saldo
                            </a>

                        @endif

                    </div>
                    @endif
                </aside>
            </div>
        </form>
    </main>
</div>
@auth
<form id="pagar-saldo-form" method="POST" action="{{ route('b2c.pago.saldo', $cotizacion->id) }}">
    @csrf
</form>
@endauth
</body>
</html>