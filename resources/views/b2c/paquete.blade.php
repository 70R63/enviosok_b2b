<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Información del paquete - ZIGO</title>

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
            grid-template-columns: 220px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: #4169ec;
            color: white;
            padding: 25px 20px;
        }

        .logo {
            margin-bottom: 28px;
            text-align: center;
            font-size: 26px;
            font-weight: 900;
        }

        .menu a,
        .logout-btn {
            display: block;
            width: 100%;
            margin: 11px 0;
            padding: 12px 13px;
            border: none;
            border-radius: 11px;
            background: rgba(255, 255, 255, .13);
            color: white;
            text-align: left;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .content {
            padding: 28px;
        }

        .title {
            margin-bottom: 6px;
            font-size: 31px;
            font-weight: 900;
        }

        .subtitle {
            margin-bottom: 20px;
            color: #64748b;
        }

        .main-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(360px, 410px);
            gap: 22px;
            align-items: start;
        }

        .card {
            margin-bottom: 17px;
            padding: 20px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
        }

        .card h2 {
            margin: 0 0 16px;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-grid.package-grid {
            grid-template-columns:
                minmax(160px, .8fr)
                minmax(150px, .7fr)
                minmax(0, 1.5fr);
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 800;
        }

        input,
        select {
            width: 100%;
            min-height: 42px;
            padding: 10px 11px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            background: white;
            font-size: 14px;
        }

        input:focus,
        select:focus {
            border-color: #4169ec;
            outline: none;
            box-shadow: 0 0 0 3px rgba(65, 105, 236, .12);
        }

        .dimensions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 9px;
        }

        .dimensions-grid.is-disabled {
            opacity: .65;
        }

        .weight-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .weight-item {
            padding: 12px;
            border-radius: 11px;
            background: #f8fafc;
            text-align: center;
        }

        .weight-item span {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .weight-item strong {
            font-size: 17px;
        }

        .warning {
            margin-top: 14px;
            padding: 11px 13px;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 13px;
            line-height: 1.45;
        }

        .clear-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .clear-btn {
            padding: 9px 15px;
            border: none;
            border-radius: 8px;
            background: #ef4444;
            color: white;
            font-weight: 800;
            cursor: pointer;
        }

        .restricted-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            text-align: center;
        }

        .restricted-item {
            min-height: 78px;
            padding: 10px 6px;
            border: 1px solid #fecaca;
            border-radius: 11px;
            background: #fff5f5;
            font-size: 12px;
            font-weight: 800;
        }

        .restricted-item .icon {
            margin-bottom: 5px;
            font-size: 25px;
        }

        .declaration {
            margin-top: 16px;
            padding: 15px;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            background: #eff6ff;
        }

        .declaration label {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            margin: 0;
            line-height: 1.55;
            cursor: pointer;
        }

        .declaration input {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            min-height: auto;
            margin-top: 2px;
        }

        .declaration-text {
            font-size: 13px;
        }

        .error-box {
            margin-bottom: 16px;
            padding: 13px;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
            font-weight: 800;
        }

        #mensajeAceptacion {
            display: none;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #ef4444;
            border-radius: 9px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
            font-weight: 800;
        }

        .submit-row {
            display: flex;
            justify-content: flex-end;
        }

        .submit-btn {
            min-width: 180px;
            min-height: 44px;
            padding: 11px 20px;
            border: none;
            border-radius: 10px;
            background: #4169ec;
            color: white;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
        }

        .submit-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .summary-card {
            position: sticky;
            top: 20px;
        }

        .summary-card p {
            margin: 9px 0;
            font-size: 13px;
            line-height: 1.45;
        }

        .summary-card strong {
            color: #334155;
        }

        .package-rule {
            margin-top: 14px;
            padding: 12px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            line-height: 1.5;
        }

        .insurance-box {
            margin-top: 16px;
            padding: 15px;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            background: #eff6ff;
        }

        .insurance-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            cursor: pointer;
        }

        .insurance-check input {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            min-height: auto;
            margin-top: 2px;
        }

        .insurance-check span {
            display: grid;
            gap: 4px;
        }

        .insurance-check small {
            color: #475569;
            font-weight: 500;
            line-height: 1.4;
        }

        .insurance-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 14px;
        }

        .insurance-summary > div {
            padding: 11px;
            border-radius: 10px;
            background: white;
        }

        .insurance-summary span {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
        }

        .insurance-summary strong {
            font-size: 14px;
        }

        .insurance-summary .insurance-total {
            background: #dbeafe;
        }

        .insurance-error {
            display: none;
            margin-top: 12px;
            padding: 10px;
            border-radius: 9px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
            font-weight: 800;
        }

        @media (max-width: 760px) {
            .insurance-summary {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1100px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .summary-card {
                position: static;
            }

            .restricted-grid {
                grid-template-columns: repeat(4, 1fr);
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
                padding: 26px 30px;
            }

            .title {
                font-size: 25px;
            }

            .form-grid,
            .form-grid.package-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .weight-summary {
                grid-template-columns: 1fr;
            }

            .restricted-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .submit-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

@php
    /*
     * Cuando el usuario apenas viene del formulario
     * de direcciones, ignoramos los valores provisionales
     * de 1 kg y 20x20x20.
     */
    $paqueteSinCapturar =
        $cotizacion->estatus === 'DIRECCION_CAPTURADA';

    $tipoActual = old(
        'tipo_envio',
        $paqueteSinCapturar
            ? 'caja'
            : ($cotizacion->tipo_envio ?: 'caja')
    );

    $medidasActuales = [
        null,
        null,
        null,
    ];

    if (
        !$paqueteSinCapturar
        && $tipoActual === 'caja'
        && !empty($cotizacion->medidas)
    ) {
        $partesMedidas = explode(
            'x',
            $cotizacion->medidas
        );

        $medidasActuales = [
            $partesMedidas[0] ?? null,
            $partesMedidas[1] ?? null,
            $partesMedidas[2] ?? null,
        ];
    }

    $pesoRealActual = old(
        'peso',
        $paqueteSinCapturar
            ? null
            : (
                $cotizacion->peso_real
                ?? $cotizacion->peso
            )
    );

    $aceptaDeclaracion =
        old('acepta_no_prohibidos') == 1;
@endphp

<div class="layout">
    <aside class="sidebar">
        <div class="logo">ZIGO</div>

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

            <a href="{{ route('b2c.adeudos.index') }}">
                Adeudos
            </a>

            <a href="{{ route('b2c.configuracion') }}">
                Configuración
            </a>

            <form
                method="POST"
                action="{{ route('logout') }}"
            >
                @csrf

                <button
                    class="logout-btn"
                    type="submit"
                >
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">
            Información del paquete
        </div>

        <div class="subtitle">
            Captura peso, dimensiones y contenido del envío.
        </div>

        @if(session('error'))
            <div class="error-box">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="error-box">
                <div>
                    Revisa los campos antes de continuar:
                </div>

                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="main-grid">
            <section>
                <form
                    id="paquete_form"
                    method="POST"
                    action="{{ route(
                        'b2c.paquete.guardar',
                        $cotizacion->id
                    ) }}"
                >
                    @csrf

                    <div class="card">
                        <h2>Dimensiones y peso</h2>

                        <div class="form-grid package-grid">
                            <div>
                                <label for="tipo_envio">
                                    Tipo de envío
                                </label>

                                <select
                                    id="tipo_envio"
                                    name="tipo_envio"
                                    required
                                >
                                    <option
                                        value="caja"
                                        @selected(
                                            $tipoActual === 'caja'
                                        )
                                    >
                                        Caja
                                    </option>

                                    <option
                                        value="sobre"
                                        @selected(
                                            $tipoActual === 'sobre'
                                        )
                                    >
                                        Sobre
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="peso">
                                    Peso real kg
                                </label>

                                <input
                                    type="number"
                                    id="peso"
                                    name="peso"
                                    min="0.1"
                                    step="0.01"
                                    placeholder="Ej. 2.5"
                                    value="{{ $pesoRealActual }}"
                                >
                            </div>

                            <div>
                                <label>
                                    Dimensiones cm
                                </label>

                                <div
                                    class="dimensions-grid"
                                    id="contenedor_dimensiones"
                                >
                                    <input
                                        type="number"
                                        id="largo"
                                        name="largo"
                                        min="1"
                                        step="0.01"
                                        placeholder="Largo"
                                        value="{{ old(
                                            'largo',
                                            $medidasActuales[0]
                                        ) }}"
                                    >

                                    <input
                                        type="number"
                                        id="ancho"
                                        name="ancho"
                                        min="1"
                                        step="0.01"
                                        placeholder="Ancho"
                                        value="{{ old(
                                            'ancho',
                                            $medidasActuales[1]
                                        ) }}"
                                    >

                                    <input
                                        type="number"
                                        id="alto"
                                        name="alto"
                                        min="1"
                                        step="0.01"
                                        placeholder="Alto"
                                        value="{{ old(
                                            'alto',
                                            $medidasActuales[2]
                                        ) }}"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="weight-summary">
                            <div class="weight-item">
                                <span>Peso real</span>

                                <strong>
                                    <span id="peso_real_txt">--</span>
                                    kg
                                </strong>
                            </div>

                            <div class="weight-item">
                                <span>Peso volumétrico</span>

                                <strong>
                                    <span id="peso_vol_txt">--</span>
                                    kg
                                </strong>
                            </div>

                            <div class="weight-item">
                                <span>Peso a cotizar</span>

                                <strong>
                                    <span id="peso_cotizar_txt">--</span>
                                    kg
                                </strong>
                            </div>
                        </div>

                        <div class="warning">
                            Si el paquete pesa más o es más grande de
                            lo declarado, podrían aplicar cargos
                            adicionales.
                        </div>

                        <div class="clear-row">
                            <button
                                type="button"
                                id="limpiarDimensiones"
                                class="clear-btn"
                            >
                                Limpiar datos
                            </button>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Contenido y protección</h2>

                        <div class="form-grid">
                            <div>
                                <label for="contenido">
                                    Contenido del envío
                                </label>

                                <input
                                    type="text"
                                    id="contenido"
                                    name="contenido"
                                    maxlength="255"
                                    required
                                    placeholder="Ej. ropa, documentos, accesorios"
                                    value="{{ old(
                                        'contenido',
                                        $cotizacion->contenido
                                    ) }}"
                                >
                            </div>

                            <div>
                                <label for="valor_declarado">
                                    Valor declarado MXN
                                </label>

                                <input
                                    type="number"
                                    id="valor_declarado"
                                    name="valor_declarado"
                                    min="0"
                                    step="0.01"
                                    value="{{ old(
                                        'valor_declarado',
                                        $cotizacion->valor_declarado ?? 0
                                    ) }}"
                                >
                            </div>
                        </div>

                        <div class="insurance-box">
                            <input
                                type="hidden"
                                name="requiere_seguro_envio"
                                value="0"
                            >

                            <label class="insurance-check">
                                <input
                                    type="checkbox"
                                    id="requiere_seguro_envio"
                                    name="requiere_seguro_envio"
                                    value="1"
                                    @checked(
                                        old(
                                            'requiere_seguro_envio',
                                            $cotizacion->requiere_seguro_envio
                                                ?? false
                                        )
                                    )
                                >

                                <span>
                                    <strong>Quiero proteger mi envío</strong>

                                    <small>
                                        La protección cuesta el 2% del valor
                                        declarado más IVA.
                                    </small>
                                </span>
                            </label>

                            <div class="insurance-summary">
                                <div>
                                    <span>Protección 2%</span>

                                    <strong id="seguro_base_preview">
                                        $0.00 MXN
                                    </strong>
                                </div>

                                <div>
                                    <span>IVA de protección</span>

                                    <strong id="seguro_iva_preview">
                                        $0.00 MXN
                                    </strong>
                                </div>

                                <div class="insurance-total">
                                    <span>Total protección</span>

                                    <strong id="seguro_total_preview">
                                        $0.00 MXN
                                    </strong>
                                </div>
                            </div>

                            <div
                                id="insurance_error"
                                class="insurance-error"
                            >
                                Captura un valor declarado mayor a cero
                                para proteger el envío.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Objetos prohibidos para envío</h2>

                        <div class="restricted-grid">
                            @foreach([
                                ['🔫', 'Armas'],
                                ['🔥', 'Encendedores'],
                                ['🧯', 'Inflamables'],
                                ['☢️', 'Materiales tóxicos'],
                                ['🚬', 'Vapes'],
                                ['💥', 'Explosivos'],
                                ['☣️', 'Químicos'],
                                ['🔋', 'Baterías'],
                                ['⛽', 'Gasolina'],
                                ['🔪', 'Arma blanca'],
                                ['💊', 'Drogas'],
                                ['🧴', 'Gas aerosol'],
                            ] as [$icono, $texto])
                                <div class="restricted-item">
                                    <div class="icon">
                                        {{ $icono }}
                                    </div>

                                    <div>
                                        {{ $texto }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="declaration">
                            <label>
                                <input
                                    type="checkbox"
                                    id="acepta_no_prohibidos"
                                    name="acepta_no_prohibidos"
                                    value="1"
                                    @checked(
                                        $aceptaDeclaracion
                                    )
                                >

                                <span class="declaration-text">
                                    <strong>
                                        Declaración del remitente
                                    </strong>

                                    <br><br>

                                    Confirmo que el contenido de este
                                    envío no contiene artículos
                                    prohibidos, restringidos, peligrosos
                                    o ilegales y acepto las políticas de
                                    transporte de ZIGO.

                                    <br><br>

                                    Acepto que proporcionar información
                                    falsa puede ocasionar cancelación,
                                    retención del paquete o cargos
                                    adicionales.
                                </span>
                            </label>
                        </div>

                        <div id="mensajeAceptacion">
                            Debes aceptar la declaración del remitente
                            para cotizar el envío.
                        </div>
                    </div>

                    <div class="submit-row">
                        <button
                            class="submit-btn"
                            id="cotizar_envio_btn"
                            type="submit"
                            disabled
                        >
                            Cotizar envío
                        </button>
                    </div>
                </form>
            </section>

            <aside class="card summary-card">
                <h2>Resumen</h2>

                <p>
                    <strong>Origen:</strong><br>

                    {{ $cotizacion->cp_origen }}
                    -
                    {{ $cotizacion->colonia_origen }}
                </p>

                <p>
                    <strong>Destino:</strong><br>

                    {{ $cotizacion->cp_destino }}
                    -
                    {{ $cotizacion->colonia_destino }}
                </p>

                <div class="package-rule">
                    <strong>Caja:</strong>
                    se cotiza el valor mayor entre peso real
                    y peso volumétrico, redondeado hacia arriba.

                    <br><br>

                    <strong>Sobre:</strong>
                    se registra con 1 kg y sin dimensiones.
                </div>
            </aside>
        </div>
    </main>
</div>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {
        const form =
            document.getElementById(
                'paquete_form'
            );

        const tipoEnvio =
            document.getElementById(
                'tipo_envio'
            );

        const peso =
            document.getElementById(
                'peso'
            );

        const largo =
            document.getElementById(
                'largo'
            );

        const ancho =
            document.getElementById(
                'ancho'
            );

        const alto =
            document.getElementById(
                'alto'
            );

        const contenedorDimensiones =
            document.getElementById(
                'contenedor_dimensiones'
            );

        const pesoRealText =
            document.getElementById(
                'peso_real_txt'
            );

        const pesoVolText =
            document.getElementById(
                'peso_vol_txt'
            );

        const pesoCotizarText =
            document.getElementById(
                'peso_cotizar_txt'
            );

        const limpiar =
            document.getElementById(
                'limpiarDimensiones'
            );

        const aceptacion =
            document.getElementById(
                'acepta_no_prohibidos'
            );

        const mensajeAceptacion =
            document.getElementById(
                'mensajeAceptacion'
            );

        const cotizarBtn =
            document.getElementById(
                'cotizar_envio_btn'
            );

        const valorDeclarado =
            document.getElementById(
                'valor_declarado'
            );

        const requiereSeguro =
            document.getElementById(
                'requiere_seguro_envio'
            );

        const seguroBasePreview =
            document.getElementById(
                'seguro_base_preview'
            );

        const seguroIvaPreview =
            document.getElementById(
                'seguro_iva_preview'
            );

        const seguroTotalPreview =
            document.getElementById(
                'seguro_total_preview'
            );

        const insuranceError =
            document.getElementById(
                'insurance_error'
            );

        function numero(valor) {
            return parseFloat(
                String(valor || '')
                    .replace(',', '.')
            ) || 0;
        }

        function money(valor) {
            return '$'
                + Number(valor || 0).toFixed(2)
                + ' MXN';
        }

        function calcularSeguro() {
            const valor =
                numero(valorDeclarado.value);

            const protegido =
                requiereSeguro.checked;

            let seguroBase = 0;
            let seguroIva = 0;
            let seguroTotal = 0;

            if (
                protegido &&
                valor > 0
            ) {
                seguroBase =
                    valor * 0.02;

                seguroIva =
                    seguroBase * 0.16;

                seguroTotal =
                    seguroBase + seguroIva;
            }

            seguroBasePreview.textContent =
                money(seguroBase);

            seguroIvaPreview.textContent =
                money(seguroIva);

            seguroTotalPreview.textContent =
                money(seguroTotal);

            insuranceError.style.display =
                protegido && valor <= 0
                    ? 'block'
                    : 'none';

            return {
                protegido,
                valor,
                seguroTotal,
            };
        }

        function mostrarPesos(
            pesoReal,
            pesoVolumetrico,
            pesoFacturable
        ) {
            pesoRealText.textContent =
                Number(pesoReal).toFixed(2);

            pesoVolText.textContent =
                Number(
                    pesoVolumetrico
                ).toFixed(2);

            pesoCotizarText.textContent =
                Number(
                    pesoFacturable
                ).toFixed(2);
        }

        function limpiarResumen() {
            pesoRealText.textContent = '--';
            pesoVolText.textContent = '--';
            pesoCotizarText.textContent = '--';
        }

        function calcularPeso() {
            if (
                tipoEnvio.value ===
                'sobre'
            ) {
                peso.value = '1.00';

                mostrarPesos(
                    1,
                    0,
                    1
                );

                return;
            }

            const pesoReal =
                numero(peso.value);

            const largoValor =
                numero(largo.value);

            const anchoValor =
                numero(ancho.value);

            const altoValor =
                numero(alto.value);

            if (
                pesoReal <= 0 ||
                largoValor <= 0 ||
                anchoValor <= 0 ||
                altoValor <= 0
            ) {
                limpiarResumen();
                return;
            }

            const pesoVolumetrico =
                (
                    largoValor
                    * anchoValor
                    * altoValor
                ) / 5000;

            const pesoFacturable =
                Math.ceil(
                    Math.max(
                        pesoReal,
                        pesoVolumetrico
                    )
                );

            mostrarPesos(
                pesoReal,
                pesoVolumetrico,
                pesoFacturable
            );
        }

        function configurarTipoEnvio() {
            const esSobre =
                tipoEnvio.value ===
                'sobre';

            [
                largo,
                ancho,
                alto
            ].forEach(function (campo) {
                campo.disabled = esSobre;
                campo.required = !esSobre;

                if (esSobre) {
                    campo.value = '';
                }
            });

            if (esSobre) {
                peso.value = '1.00';
                peso.readOnly = true;
                peso.required = true;

                contenedorDimensiones
                    .classList
                    .add('is-disabled');

                mostrarPesos(
                    1,
                    0,
                    1
                );

                return;
            }

            peso.readOnly = false;
            peso.required = true;

            contenedorDimensiones
                .classList
                .remove('is-disabled');

            calcularPeso();
        }

                function actualizarBoton() {
            cotizarBtn.disabled =
                !aceptacion.checked;

            if (aceptacion.checked) {
                mensajeAceptacion.style.display =
                    'none';
            }
        }

        /*
         * Cambio entre Caja y Sobre.
         */
        tipoEnvio.addEventListener(
            'change',
            function () {
                configurarTipoEnvio();
                calcularPeso();
            }
        );

        /*
         * Recalcular peso al capturar datos.
         */
        [
            peso,
            largo,
            ancho,
            alto
        ].forEach(function (campo) {
            campo.addEventListener(
                'input',
                calcularPeso
            );

            campo.addEventListener(
                'change',
                calcularPeso
            );
        });

        /*
         * Limpiar peso y dimensiones.
         */
        limpiar.addEventListener(
            'click',
            function () {
                if (
                    tipoEnvio.value ===
                    'sobre'
                ) {
                    return;
                }

                peso.value = '';
                largo.value = '';
                ancho.value = '';
                alto.value = '';

                limpiarResumen();
            }
        );

        /*
         * Activar o desactivar el botón Cotizar.
         */
        aceptacion.addEventListener(
            'change',
            actualizarBoton
        );

        /*
         * Recalcular protección.
         */
        valorDeclarado.addEventListener(
            'input',
            calcularSeguro
        );

        valorDeclarado.addEventListener(
            'change',
            calcularSeguro
        );

        requiereSeguro.addEventListener(
            'change',
            calcularSeguro
        );

        /*
         * Validaciones antes de enviar.
         */
        form.addEventListener(
            'submit',
            function (event) {
                /*
                 * Validar declaración del remitente.
                 */
                if (!aceptacion.checked) {
                    event.preventDefault();

                    mensajeAceptacion.style.display =
                        'block';

                    aceptacion.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });

                    return;
                }

                mensajeAceptacion.style.display =
                    'none';

                /*
                 * Validar protección.
                 */
                const seguro =
                    calcularSeguro();

                if (
                    seguro.protegido &&
                    seguro.valor <= 0
                ) {
                    event.preventDefault();

                    insuranceError.style.display =
                        'block';

                    valorDeclarado.focus();

                    valorDeclarado.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });

                    return;
                }

                /*
                 * Preparar Caja o Sobre.
                 */
                if (
                    tipoEnvio.value ===
                    'sobre'
                ) {
                    peso.value = '1.00';
                } else {
                    calcularPeso();
                }
            }
        );

        /*
         * Estado inicial.
         */
        configurarTipoEnvio();
        calcularPeso();
        actualizarBoton();
        calcularSeguro();
    }
);
</script>

</body>
</html>