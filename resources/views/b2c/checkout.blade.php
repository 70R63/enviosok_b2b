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
        .insurance-box{
            grid-column:1 / -1;
            margin-top:6px;
            padding:14px;
            border:1px solid #dbeafe;
            border-radius:14px;
            background:#eff6ff;
        }

        .insurance-check{
            display:flex;
            gap:10px;
            align-items:flex-start;
            font-weight:900;
            color:#111827;
            cursor:pointer;
        }

        .insurance-check input{
            width:auto;
            height:auto;
            margin-top:3px;
        }

        .insurance-help{
            margin:8px 0 0 26px;
            font-size:13px;
            color:#475569;
            line-height:1.35;
        }

        .insurance-preview{
            margin:10px 0 0 26px;
            display:grid;
            gap:4px;
            font-size:13px;
            color:#0f172a;
        }

        .insurance-preview strong{
            font-size:14px;
        }

        .insurance-error{
            display:none;
            margin:10px 0 0 26px;
            color:#991b1b;
            background:#fee2e2;
            border-radius:10px;
            padding:10px;
            font-size:13px;
            font-weight:800;
        }

        .summary-row.insurance-summary{
            color:#1d4ed8;
        }
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

        .modal-pago{
            display:none;
            position:fixed;
            inset:0;
            background:rgba(15,23,42,.62);
            z-index:9999;
            align-items:center;
            justify-content:center;
            padding:20px;
        }

        .modal-card{
            width:min(420px, 100%);
            background:#ffffff;
            border-radius:18px;
            padding:28px;
            box-shadow:0 24px 60px rgba(0,0,0,.28);
        }

        .modal-card h2{
            margin:0 0 10px;
        }

        .modal-text{
            color:#64748b;
            margin:0 0 18px;
            line-height:1.5;
        }

        .modal-total{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:14px;
            margin-bottom:16px;
            background:#f8fafc;
            border-radius:12px;
            font-weight:900;
        }

        .modal-payment-btn{
            width:100%;
            border:none;
            border-radius:12px;
            padding:14px 18px;
            margin-top:10px;
            color:#ffffff;
            font-size:15px;
            font-weight:900;
            cursor:pointer;
        }

        .modal-payment-btn.mercado-pago{
            background:#f97316;
        }

        .modal-payment-btn.saldo{
            background:#16a34a;
        }

        .modal-payment-btn:disabled{
            opacity:.45;
            cursor:not-allowed;
        }

        .modal-saldo-info{
            padding:10px 12px;
            margin-top:10px;
            border-radius:10px;
            background:#fef2f2;
            color:#991b1b;
            font-weight:800;
            font-size:13px;
        }

        .modal-cancel{
            display:block;
            width:100%;
            margin-top:14px;
            padding:10px;
            background:transparent;
            border:none;
            color:#475569;
            font-weight:800;
            cursor:pointer;
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

        <form
            id="checkout_form"
            method="POST"
            action="/b2c/checkout/{{ $cotizacion->id }}"
        >
            @csrf

            <input
                type="hidden"
                name="metodo_pago"
                id="metodo_pago"
                value="{{ old('metodo_pago', 'mercado_pago') }}"
            >

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
                                    <input type="text" name="ciudad_origen" value="{{ old('ciudad_origen', $cotizacion->ciudad_origen) }}" required>
                                </div>

                                <div>
                                    <label>Estado origen</label>
                                    <input type="text" name="estado_origen" value="{{ old('estado_origen', $cotizacion->estado_origen ?: 'MEX') }}" required>
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
                                    <input type="text" name="ciudad_destino" value="{{ old('ciudad_destino', $cotizacion->ciudad_destino) }}" required>
                                </div>

                                <div>
                                    <label>Estado destino</label>
                                    <input type="text" name="estado_destino" value="{{ old('estado_destino', $cotizacion->estado_destino ?: 'MEX') }}" required>
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
                                <input
                                    type="number"
                                    name="valor_declarado"
                                    id="valor_declarado"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('valor_declarado', $cotizacion->valor_declarado ?? 0) }}"
                                >
                            </div>

                            <div>
                                <label>Referencia opcional</label>
                                <input type="text" name="referencia" value="{{ old('referencia', $cotizacion->referencia) }}">
                            </div>

                            <div class="insurance-box">
                                <input type="hidden" name="requiere_seguro_envio" value="0">

                                <label class="insurance-check">
                                    <input
                                        type="checkbox"
                                        name="requiere_seguro_envio"
                                        id="requiere_seguro_envio"
                                        value="1"
                                        {{ old('requiere_seguro_envio', $cotizacion->requiere_seguro_envio ?? false) ? 'checked' : '' }}
                                    >

                                    <span>Quiero proteger mi envío</span>
                                </label>

                                <div class="insurance-help">
                                    Agrega protección por el valor declarado. El costo es el 2% del valor declarado + IVA.
                                </div>

                                <div class="insurance-preview">
                                    <div>Protección 2%: <strong id="seguro_base_preview">$0.00 MXN</strong></div>
                                    <div>IVA protección: <strong id="seguro_iva_preview">$0.00 MXN</strong></div>
                                    <div>Total protección: <strong id="seguro_monto_preview">$0.00 MXN</strong></div>
                                    <div>Total con protección: <strong id="total_con_seguro_preview">${{ number_format($cotizacion->precio, 2) }} MXN</strong></div>
                                </div>

                                <div id="insurance_error" class="insurance-error">
                                    Para proteger tu envío, captura un valor declarado mayor a 0.
                                </div>
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

                    @php
                        $precioBaseResumen = (float) ($cotizacion->precio_sin_seguro ?: $cotizacion->precio);
                        $seguroMontoResumen = (float) ($cotizacion->seguro_monto ?? 0);
                        $totalResumen = $precioBaseResumen + $seguroMontoResumen;
                    @endphp

                    <div class="summary-row">
                        <span>Envío</span>
                        <strong id="resumen_envio">${{ number_format($precioBaseResumen, 2) }} MXN</strong>
                    </div>

                    <div class="summary-row insurance-summary">
                        <span>Protección 2%</span>
                        <strong id="resumen_seguro_base">$0.00 MXN</strong>
                    </div>

                    <div class="summary-row insurance-summary">
                        <span>IVA protección</span>
                        <strong id="resumen_seguro_iva">$0.00 MXN</strong>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="resumen_total">${{ number_format($totalResumen, 2) }} MXN</span>
                    </div>

                    <p class="muted">
                        El pago se realizará en línea. Una vez confirmado, se generará la guía correspondiente.
                    </p>

                    @if(auth()->check() && !$isPublicCheckout)
                        <button
                            class="btn"
                            type="button"
                            id="continuar_pago_btn"
                        >
                            Continuar a pago
                        </button>
                    @else
                        <button class="btn" type="submit">
                            Continuar a pago
                        </button>
                    @endif

                    @if(auth()->check() && !$isPublicCheckout)
                    <div class="saldo-box">

                        <div class="saldo-line">
                                <span>Saldo disponible</span>

                                <strong id="saldo_disponible_text">
                                    ${{ number_format($saldo->saldo ?? 0, 2) }}
                                </strong>
                            </div>

                            <div class="saldo-line">
                                <span>Costo de envío</span>

                                <strong id="saldo_costo_envio">
                                    ${{ number_format($precioBaseResumen, 2) }}
                                </strong>
                            </div>

                            <div class="saldo-line">
                                <span>Protección + IVA</span>

                                <strong id="saldo_seguro_total">
                                    $0.00
                                </strong>
                            </div>

                            <div class="saldo-line" style="
                                border-top:1px solid #cbd5e1;
                                padding-top:12px;
                            ">
                                <span>Total a pagar</span>

                                <strong id="saldo_total_pagar">
                                    ${{ number_format($totalResumen, 2) }}
                                </strong>
                            </div>

                            <div
                                id="saldo_estado"
                                class="saldo-error"
                                style="display:none;"
                            ></div>

                            <a
                                href="{{ route('b2c.prepago') }}"
                                class="saldo-recargar"
                                id="saldo_recargar"
                            >
                                Recargar saldo
                            </a>
                        @endif
                    </div>                
                </aside>
            </div>
        </form>
    </main>
</div>

@if(auth()->check() && !$isPublicCheckout)
<div id="modalPago" class="modal-pago">
    <div class="modal-card">
        <h2>¿Cómo deseas pagar?</h2>

        <p class="modal-text">
            Los datos del envío están completos. Selecciona el método de pago para continuar.
        </p>

        <div class="modal-total">
            <span>Total a pagar</span>

            <strong id="modal_total_pagar">
                ${{ number_format($totalResumen, 2) }} MXN
            </strong>
        </div>

        <button
            type="button"
            id="pagar_mercado_pago"
            class="modal-payment-btn mercado-pago"
        >
            Mercado Pago
        </button>

        <button
            type="button"
            id="pagar_saldo"
            class="modal-payment-btn saldo"
        >
            Saldo prepago
        </button>

        <div
            id="modal_saldo_estado"
            class="modal-saldo-info"
            style="display:none;"
        ></div>

        <button
            type="button"
            class="modal-cancel"
            id="cerrar_modal_pago"
        >
            Cancelar
        </button>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const precioBaseEnvio = {{
        (float) ($cotizacion->precio_sin_seguro ?: $cotizacion->precio)
    }};

    const saldoDisponible = {{
        (float) ($saldo->saldo ?? 0)
    }};

    const seguroPorcentaje = 2;
    const seguroIvaPorcentaje = 16;

    const checkoutForm =
        document.getElementById('checkout_form');

    const metodoPagoInput =
        document.getElementById('metodo_pago');

    const valorDeclaradoInput =
        document.getElementById('valor_declarado');

    const requiereSeguroInput =
        document.getElementById('requiere_seguro_envio');

    const insuranceError =
        document.getElementById('insurance_error');

    const seguroPreview =
        document.getElementById('seguro_monto_preview');

    const totalPreview =
        document.getElementById('total_con_seguro_preview');

    const seguroBasePreview =
        document.getElementById('seguro_base_preview');

    const seguroIvaPreview =
        document.getElementById('seguro_iva_preview');

    const resumenSeguroBase =
        document.getElementById('resumen_seguro_base');

    const resumenSeguroIva =
        document.getElementById('resumen_seguro_iva');

    const resumenTotal =
        document.getElementById('resumen_total');

    const saldoCostoEnvio =
        document.getElementById('saldo_costo_envio');

    const saldoSeguroTotal =
        document.getElementById('saldo_seguro_total');

    const saldoTotalPagar =
        document.getElementById('saldo_total_pagar');

    const saldoEstado =
        document.getElementById('saldo_estado');

    const modal =
        document.getElementById('modalPago');

    const modalTotal =
        document.getElementById('modal_total_pagar');

    const modalSaldoEstado =
        document.getElementById('modal_saldo_estado');

    const continuarPagoBtn =
        document.getElementById('continuar_pago_btn');

    const pagarMercadoPagoBtn =
        document.getElementById('pagar_mercado_pago');

    const pagarSaldoBtn =
        document.getElementById('pagar_saldo');

    const cerrarModalBtn =
        document.getElementById('cerrar_modal_pago');

    function money(value) {
        return '$' +
            Number(value || 0).toFixed(2) +
            ' MXN';
    }

    function calcularSeguroVisual() {
        const valorDeclarado =
            parseFloat(
                valorDeclaradoInput?.value || 0
            ) || 0;

        const requiereSeguro =
            requiereSeguroInput?.checked || false;

        let seguroBase = 0;
        let seguroIva = 0;
        let seguroMonto = 0;

        if (
            requiereSeguro &&
            valorDeclarado > 0
        ) {
            seguroBase =
                valorDeclarado *
                (seguroPorcentaje / 100);

            seguroIva =
                seguroBase *
                (seguroIvaPorcentaje / 100);

            seguroMonto =
                seguroBase + seguroIva;
        }

        const total =
            precioBaseEnvio + seguroMonto;

        if (seguroPreview) {
            seguroPreview.textContent =
                money(seguroMonto);
        }

        if (seguroBasePreview) {
            seguroBasePreview.textContent =
                money(seguroBase);
        }

        if (seguroIvaPreview) {
            seguroIvaPreview.textContent =
                money(seguroIva);
        }

        if (resumenSeguroBase) {
            resumenSeguroBase.textContent =
                money(seguroBase);
        }

        if (resumenSeguroIva) {
            resumenSeguroIva.textContent =
                money(seguroIva);
        }

        if (totalPreview) {
            totalPreview.textContent =
                money(total);
        }

        if (resumenTotal) {
            resumenTotal.textContent =
                money(total);
        }

        if (saldoCostoEnvio) {
            saldoCostoEnvio.textContent =
                money(precioBaseEnvio);
        }

        if (saldoSeguroTotal) {
            saldoSeguroTotal.textContent =
                money(seguroMonto);
        }

        if (saldoTotalPagar) {
            saldoTotalPagar.textContent =
                money(total);
        }

        if (modalTotal) {
            modalTotal.textContent =
                money(total);
        }

        if (insuranceError) {
            insuranceError.style.display =
                requiereSeguro &&
                valorDeclarado <= 0
                    ? 'block'
                    : 'none';
        }

        const saldoSuficiente =
            saldoDisponible >= total;

        if (pagarSaldoBtn) {
            pagarSaldoBtn.disabled =
                !saldoSuficiente;
        }

        const mensajeSaldo =
            saldoSuficiente
                ? 'Saldo suficiente para realizar el pago.'
                : 'Saldo insuficiente. Necesitas ' +
                  money(total - saldoDisponible) +
                  ' adicionales.';

        if (saldoEstado) {
            saldoEstado.textContent =
                mensajeSaldo;

            saldoEstado.style.display =
                saldoSuficiente
                    ? 'none'
                    : 'block';
        }

        if (modalSaldoEstado) {
            modalSaldoEstado.textContent =
                mensajeSaldo;

            modalSaldoEstado.style.display =
                saldoSuficiente
                    ? 'none'
                    : 'block';
        }

        return {
            valorDeclarado,
            requiereSeguro,
            seguroMonto,
            total,
            saldoSuficiente
        };
    }

    function formularioValidoParaPagar() {
        const calculo =
            calcularSeguroVisual();

        if (
            checkoutForm &&
            !checkoutForm.reportValidity()
        ) {
            return false;
        }

        if (
            calculo.requiereSeguro &&
            calculo.valorDeclarado <= 0
        ) {
            if (insuranceError) {
                insuranceError.style.display =
                    'block';
            }

            valorDeclaradoInput?.focus();

            return false;
        }

        return true;
    }

    function abrirModalPago() {
        if (!formularioValidoParaPagar()) {
            return;
        }

        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function cerrarModalPago() {
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function enviarPago(metodoPago) {
        if (!checkoutForm || !metodoPagoInput) {
            return;
        }

        metodoPagoInput.value =
            metodoPago;

        cerrarModalPago();

        checkoutForm.requestSubmit();
    }

    if (valorDeclaradoInput) {
        valorDeclaradoInput.addEventListener(
            'input',
            calcularSeguroVisual
        );

        valorDeclaradoInput.addEventListener(
            'change',
            calcularSeguroVisual
        );
    }

    if (requiereSeguroInput) {
        requiereSeguroInput.addEventListener(
            'change',
            calcularSeguroVisual
        );
    }

    if (continuarPagoBtn) {
        continuarPagoBtn.addEventListener(
            'click',
            abrirModalPago
        );
    }

    if (pagarMercadoPagoBtn) {
        pagarMercadoPagoBtn.addEventListener(
            'click',
            function () {
                enviarPago('mercado_pago');
            }
        );
    }

    if (pagarSaldoBtn) {
        pagarSaldoBtn.addEventListener(
            'click',
            function () {
                const calculo =
                    calcularSeguroVisual();

                if (!calculo.saldoSuficiente) {
                    return;
                }

                enviarPago('saldo');
            }
        );
    }

    if (cerrarModalBtn) {
        cerrarModalBtn.addEventListener(
            'click',
            cerrarModalPago
        );
    }

    if (modal) {
        modal.addEventListener(
            'click',
            function (event) {
                if (event.target === modal) {
                    cerrarModalPago();
                }
            }
        );
    }

    calcularSeguroVisual();
});
</script>
</body>
</html>