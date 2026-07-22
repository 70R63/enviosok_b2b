<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Confirmar envío - ZIGO</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #111827;
        }

        .layout {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: #4169ec;
            color: white;
            padding: 25px 20px;
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo img {
            width: 145px;
            max-width: 100%;
        }

        .tagline {
            margin-top: 14px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            line-height: 1.5;
            text-transform: uppercase;
        }

        .menu a,
        .logout-btn {
            display: block;
            width: 100%;
            margin: 12px 0;
            padding: 13px;
            border: none;
            border-radius: 11px;
            background: rgba(255, 255, 255, .13);
            color: white;
            text-align: left;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .content {
            padding: 30px;
        }

        .page-header {
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 32px;
            font-weight: 900;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px;
            border-radius: 12px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 800;
        }

        .main-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(370px, 420px);
            gap: 22px;
            align-items: start;
        }

        .information-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .card {
            padding: 18px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 8px 22px rgba(15,23,42,.08);
        }

        .card.full {
            grid-column: 1 / -1;
        }

        .card h2 {
            margin: 0 0 15px;
            font-size: 20px;
        }

        .detail {
            display: grid;
            grid-template-columns: 125px minmax(0, 1fr);
            gap: 8px;
            margin: 9px 0;
            font-size: 14px;
            line-height: 1.4;
        }

        .detail strong {
            color: #334155;
        }

        .detail span {
            overflow-wrap: anywhere;
        }

        .summary-card {
            position: sticky;
            top: 20px;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 12px 0;
            font-size: 14px;
        }

        .summary-line strong:last-child {
            text-align: right;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #cbd5e1;
            font-size: 22px;
            font-weight: 900;
        }

        .balance-box {
            margin-top: 18px;
            padding: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 13px;
            background: #f8fafc;
        }

        .balance-state {
            margin-top: 12px;
            font-size: 13px;
            font-weight: 800;
        }

        .balance-state.ok {
            color: #15803d;
        }

        .balance-state.error {
            color: #b91c1c;
        }

        .actions {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 11px 16px;
            border: none;
            border-radius: 11px;
            text-decoration: none;
            font-weight: 900;
            cursor: pointer;
        }

        .btn-primary {
            background: #f97316;
            color: white;
        }

        .btn-secondary {
            background: #4169ec;
            color: white;
        }

        .btn-light {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .65);
        }

        .modal.show {
            display: flex;
        }

        .modal-card {
            width: 460px;
            max-width: 100%;
            padding: 25px;
            border-radius: 18px;
            background: white;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .30);
        }

        .modal-card h2 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .modal-text {
            margin: 0 0 16px;
            color: #64748b;
            line-height: 1.5;
        }

        .modal-total {
            margin-bottom: 18px;
            padding: 15px;
            border-radius: 12px;
            background: #f1f5f9;
            text-align: center;
            font-size: 22px;
            font-weight: 900;
        }

        .payment-form {
            margin-bottom: 10px;
        }

        .payment-btn {
            width: 100%;
            min-height: 48px;
            border: none;
            border-radius: 11px;
            color: white;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
        }

        .payment-btn.mp {
            background: #009ee3;
        }

        .payment-btn.saldo {
            background: #16a34a;
        }

        .payment-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .cancel-btn {
            width: 100%;
            min-height: 43px;
            margin-top: 4px;
            border: none;
            border-radius: 11px;
            background: #e2e8f0;
            color: #334155;
            font-weight: 900;
            cursor: pointer;
        }

        .btn-recharge-balance {
            display: block;
            width: 100%;
            margin-top: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            background: #4361ee;
            color: white;
            text-align: center;
            text-decoration: none;
            font-weight: 900;
            transition: background .2s ease;
        }

        .btn-recharge-balance:hover {
            background: #3151d3;
        }

        @media (max-width: 1050px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }
        }

        @media (max-width: 760px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .content {
                width: 100%;
                max-width: 1540px;
                margin: 0 auto;
                padding: 28px 30px;
            }

            .information-grid {
                grid-template-columns: 1fr;
            }

            .card.full {
                grid-column: auto;
            }

            .detail {
                grid-template-columns: 1fr;
                gap: 3px;
            }

            .page-header h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>

@php
    $seguroTotal = (float) ($cotizacion->seguro_monto ?? 0);

    $precioEnvio = $cotizacion->precio_sin_seguro !== null
        ? (float) $cotizacion->precio_sin_seguro
        : max(
            0,
            (float) $cotizacion->precio - $seguroTotal
        );

    $seguroBase = 0;
    $seguroIva = 0;

    if ($cotizacion->requiere_seguro_envio) {
        $seguroBase = round(
            (float) $cotizacion->valor_declarado
            * ((float) $cotizacion->seguro_porcentaje / 100),
            2
        );

        $seguroIva = round(
            $seguroBase
            * ((float) $cotizacion->seguro_iva_porcentaje / 100),
            2
        );
    }

    $total = (float) $cotizacion->precio;
    $saldoDisponible = (float) ($saldo->saldo ?? 0);
    $saldoSuficiente = $saldoDisponible >= $total;

    $direccionOrigen = trim(
        $cotizacion->remitente_direccion
        . ' #' . $cotizacion->remitente_num_ext
        . (
            $cotizacion->remitente_num_int
                ? ' Int. ' . $cotizacion->remitente_num_int
                : ''
        )
    );

    $direccionDestino = trim(
        $cotizacion->destinatario_direccion
        . ' #' . $cotizacion->destinatario_num_ext
        . (
            $cotizacion->destinatario_num_int
                ? ' Int. ' . $cotizacion->destinatario_num_int
                : ''
        )
    );
@endphp

<div class="layout">
    <aside class="sidebar">
        <div class="logo">
            <img
                src="{{ asset('img/zigo-logo.png') }}"
                alt="ZIGO"
            >

            <div class="tagline">
                Tecnología · Logística · Conexión
            </div>
        </div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">
                Inicio
            </a>

            <a href="{{ route('b2c.nuevo-envio') }}">
                Nuevo envío
            </a>

            <a href="{{ route('b2c.mis-envios') }}">
                Mis envíos
            </a>

            <a href="{{ route('b2c.incidencias') }}">
                Incidencias
            </a>

            <a href="{{ route('b2c.mis-pagos') }}">
                Mis pagos
            </a>

            <a href="{{ route('b2c.mis-direcciones') }}">
                Mis direcciones
            </a>

            <a href="{{ route('b2c.prepago') }}">
                Prepago
            </a>

            <a href="{{ route('b2c.configuracion') }}">
                Configuración
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button class="logout-btn" type="submit">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="content">
        <header class="page-header">
            <h1>Confirma los datos de tu envío</h1>

            <p>
                Revisa la información antes de seleccionar
                el método de pago.
            </p>
        </header>

        @if(session('error'))
            <div class="alert">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert">
                Revisa la información antes de continuar.
            </div>
        @endif

        <div class="main-grid">
            <section class="information-grid">
                <article class="card">
                    <h2>Remitente</h2>

                    <div class="detail">
                        <strong>Nombre</strong>
                        <span>{{ $cotizacion->remitente_nombre }}</span>
                    </div>

                    <div class="detail">
                        <strong>Teléfono</strong>
                        <span>{{ $cotizacion->remitente_telefono }}</span>
                    </div>

                    <div class="detail">
                        <strong>Correo</strong>
                        <span>{{ $cotizacion->remitente_email ?: 'No capturado' }}</span>
                    </div>

                    <div class="detail">
                        <strong>Dirección</strong>
                        <span>{{ $direccionOrigen }}</span>
                    </div>

                    <div class="detail">
                        <strong>Ubicación</strong>

                        <span>
                            {{ $cotizacion->colonia_origen }},
                            {{ $cotizacion->ciudad_origen }},
                            {{ $cotizacion->estado_origen }}.
                            CP {{ $cotizacion->cp_origen }}
                        </span>
                    </div>
                </article>

                <article class="card">
                    <h2>Destinatario</h2>

                    <div class="detail">
                        <strong>Nombre</strong>
                        <span>{{ $cotizacion->destinatario_nombre }}</span>
                    </div>

                    <div class="detail">
                        <strong>Teléfono</strong>
                        <span>{{ $cotizacion->destinatario_telefono }}</span>
                    </div>

                    <div class="detail">
                        <strong>Correo</strong>
                        <span>{{ $cotizacion->destinatario_email ?: 'No capturado' }}</span>
                    </div>

                    <div class="detail">
                        <strong>Dirección</strong>
                        <span>{{ $direccionDestino }}</span>
                    </div>

                    <div class="detail">
                        <strong>Ubicación</strong>

                        <span>
                            {{ $cotizacion->colonia_destino }},
                            {{ $cotizacion->ciudad_destino }},
                            {{ $cotizacion->estado_destino }}.
                            CP {{ $cotizacion->cp_destino }}
                        </span>
                    </div>
                </article>

                <article class="card full">
                    <h2>Paquete y servicio</h2>

                    <div class="detail">
                        <strong>Tipo</strong>
                        <span>{{ ucfirst($cotizacion->tipo_envio) }}</span>
                    </div>

                    <div class="detail">
                        <strong>Peso</strong>
                        <span>{{ number_format((float) $cotizacion->peso, 2) }} kg</span>
                    </div>

                    <div class="detail">
                        <strong>Dimensiones</strong>
                        <span>{{ $cotizacion->medidas ?: 'No aplica' }}</span>
                    </div>

                    <div class="detail">
                        <strong>Contenido</strong>
                        <span>{{ $cotizacion->contenido }}</span>
                    </div>

                    <div class="detail">
                        <strong>Valor declarado</strong>

                        <span>
                            ${{ number_format(
                                (float) $cotizacion->valor_declarado,
                                2
                            ) }} MXN
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Paquetería</strong>

                        <span>
                            {{ $cotizacion->logistico }}
                            · {{ $cotizacion->servicio }}
                        </span>
                    </div>
                </article>
            </section>

            <aside class="card summary-card">
                <h2>Resumen de pago</h2>

                <div class="summary-line">
                    <span>Envío</span>

                    <strong>
                        ${{ number_format($precioEnvio, 2) }} MXN
                    </strong>
                </div>

                <div class="summary-line">
                    <span>Protección 2%</span>

                    <strong>
                        ${{ number_format($seguroBase, 2) }} MXN
                    </strong>
                </div>

                <div class="summary-line">
                    <span>IVA protección</span>

                    <strong>
                        ${{ number_format($seguroIva, 2) }} MXN
                    </strong>
                </div>

                <div class="summary-total">
                    <span>Total</span>

                    <span>
                        ${{ number_format($total, 2) }} MXN
                    </span>
                </div>

                <div class="balance-box">
                    <div class="summary-line">
                        <span>Saldo disponible</span>

                        <strong>
                            ${{ number_format(
                                $saldoDisponible,
                                2
                            ) }} MXN
                        </strong>
                    </div>

                    @if($saldoSuficiente)
                        <div class="balance-state ok">
                            Tu saldo es suficiente para pagar este envío.
                        </div>
                    @else
                        <div class="balance-state error">
                            Saldo insuficiente. Faltan
                            ${{ number_format(
                                $total - $saldoDisponible,
                                2
                            ) }} MXN.
                        </div>

                        <a
                            href="{{ route('b2c.prepago') }}"
                            class="btn-recharge-balance"
                        >
                            Recargar saldo
                        </a>
                    @endif
                </div>

                <div class="actions">
                    <a
                        class="btn btn-light"
                        href="{{ route('b2c.paquete', $cotizacion->id) }}"
                    >
                        Editar paquete
                    </a>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="continuar_pago"
                    >
                        Continuar a pago
                    </button>
                </div>
            </aside>
        </div>
    </main>
</div>

<div class="modal" id="modal_pago">
    <div class="modal-card">
        <h2>¿Cómo deseas pagar?</h2>

        <p class="modal-text">
            Selecciona el método de pago para completar
            tu envío.
        </p>

        <div class="modal-total">
            ${{ number_format($total, 2) }} MXN
        </div>

        <form
            method="POST"
            action="{{ route(
                'b2c.confirmar.procesar',
                $cotizacion->id
            ) }}"
            class="payment-form"
        >
            @csrf

            <input
                type="hidden"
                name="metodo_pago"
                value="mercado_pago"
            >

            <button
                type="submit"
                class="payment-btn mp"
            >
                Pagar con Mercado Pago
            </button>
        </form>

        <form
            method="POST"
            action="{{ route(
                'b2c.confirmar.procesar',
                $cotizacion->id
            ) }}"
            class="payment-form"
        >
            @csrf

            <input
                type="hidden"
                name="metodo_pago"
                value="saldo"
            >

            <button
                type="submit"
                class="payment-btn saldo"
                {{ $saldoSuficiente ? '' : 'disabled' }}
            >
                Pagar con saldo prepago
            </button>
        </form>

        <button
            type="button"
            class="cancel-btn"
            id="cerrar_modal"
        >
            Cancelar
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('modal_pago');
    const abrir = document.getElementById('continuar_pago');
    const cerrar = document.getElementById('cerrar_modal');

    abrir.addEventListener('click', function () {
        modal.classList.add('show');
    });

    cerrar.addEventListener('click', function () {
        modal.classList.remove('show');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
});
</script>

</body>
</html>