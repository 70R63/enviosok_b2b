<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración - ZIGO</title>

    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px}
        .zigo-logo{text-align:center;padding:8px}
        .zigo-img{width:180px;max-width:100%;display:block;margin:0 auto}
        .zigo-tagline{margin-top:8px;font-size:10px;letter-spacing:2px;color:#dce7f7;text-transform:uppercase;font-weight:600;line-height:1.5}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .config-grid{display:grid;grid-template-columns:280px 1fr;gap:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .tab{
            display:block;
            width:100%;
            border:none;
            text-align:left;
            cursor:pointer;
            padding:15px;
            border-radius:12px;
            margin-bottom:12px;
            text-decoration:none;
            color:#111827;
            font-weight:900;
            background:#f1f5f9;
        }

        .tab.active{
            background:#2563eb;
            color:white;
        }
        .section-title{font-size:24px;font-weight:900;margin-bottom:8px}
        .muted{color:#64748b;margin-bottom:20px}
        label{display:block;font-weight:800;margin:14px 0 6px}
        input{width:100%;box-sizing:border-box;padding:13px;border:1px solid #cbd5e1;border-radius:12px}
        .btn{margin-top:20px;background:#f97316;color:white;border:none;border-radius:12px;padding:14px 22px;font-weight:900;cursor:pointer}
        .status{display:inline-block;padding:8px 12px;border-radius:999px;font-weight:900;font-size:13px;background:#fef3c7;color:#92400e}
        .success{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px}

        .fiscal-header{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:20px;
        margin-bottom:20px;
    }

    .fiscal-status{
        padding:8px 12px;
        border-radius:999px;
        background:#dcfce7;
        color:#166534;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
    }

    .fiscal-help{
        margin-bottom:22px;
        padding:16px;
        border:1px solid #bae6fd;
        border-radius:12px;
        background:#ecfeff;
        color:#155e75;
        font-size:13px;
        line-height:1.5;
    }

    .fiscal-help strong{
        display:block;
        margin-bottom:4px;
    }

    .fiscal-help a{
        display:inline-block;
        margin-top:8px;
        color:#2563eb;
        font-weight:900;
        text-decoration:none;
    }

    .fiscal-help a:hover{
        text-decoration:underline;
    }

    .fiscal-errors{
        margin-bottom:20px;
        padding:16px;
        border:1px solid #fecaca;
        border-radius:12px;
        background:#fef2f2;
        color:#991b1b;
        font-size:13px;
    }

    .fiscal-errors ul{
        margin:8px 0 0;
        padding-left:20px;
    }

    .fiscal-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:16px 18px;
    }

    .fiscal-full{
        grid-column:1 / -1;
    }

    .fiscal-grid label{
        display:block;
        margin-bottom:7px;
        color:#172033;
        font-size:13px;
        font-weight:900;
    }

    .fiscal-grid input,
    .fiscal-grid select,
    .fiscal-grid textarea{
        width:100%;
        margin:0;
        padding:12px 13px;
        border:1px solid #cbd5e1;
        border-radius:10px;
        background:#ffffff;
        color:#172033;
        box-sizing:border-box;
        font-family:inherit;
        font-size:14px;
    }

    .fiscal-grid select{
        min-height:44px;
    }

    .fiscal-grid textarea{
        resize:vertical;
    }

    .fiscal-grid input:focus,
    .fiscal-grid select:focus,
    .fiscal-grid textarea:focus{
        border-color:#4361ee;
        outline:3px solid rgba(67,97,238,.12);
    }

    .optional{
        color:#64748b;
        font-size:11px;
        font-weight:700;
    }

    .fiscal-actions{
        display:flex;
        justify-content:flex-end;
        margin-top:22px;
    }

    @media(max-width:800px){
        .fiscal-grid{
            grid-template-columns:1fr;
        }

        .fiscal-full{
            grid-column:auto;
        }

        .fiscal-header{
            flex-direction:column;
        }

        .fiscal-actions .btn{
            width:100%;
        }
    }

    .fiscal-summary-card {
        padding: 22px;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: #ffffff;
    }

    .fiscal-summary-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding-bottom: 18px;
        margin-bottom: 18px;
        border-bottom: 1px solid #e2e8f0;
    }

    .fiscal-summary-title {
        margin-bottom: 5px;
        color: #172033;
        font-size: 18px;
        font-weight: 900;
    }

    .fiscal-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .fiscal-summary-item {
        padding: 14px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 11px;
        background: #f8fafc;
    }

    .fiscal-summary-item span {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .fiscal-summary-item strong {
        display: block;
        color: #172033;
        font-size: 14px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .fiscal-summary-full {
        grid-column: 1 / -1;
    }

    .fiscal-summary-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .fiscal-form-container {
        margin-top: 6px;
    }

    .fiscal-cancel {
        background: #e2e8f0;
        color: #334155;
    }

    @media (max-width: 800px) {
        .fiscal-summary-grid {
            grid-template-columns: 1fr;
        }

        .fiscal-summary-full {
            grid-column: auto;
        }

        .fiscal-summary-header {
            flex-direction: column;
        }

        .fiscal-summary-actions .btn {
            width: 100%;
        }
    }

    /* =========================================================
    RESUMEN FISCAL COMPACTO
    ========================================================= */

    #facturacion.card {
        padding: 20px;
    }

    #facturacion .fiscal-header {
        margin-bottom: 14px;
    }

    #facturacion .fiscal-header .muted {
        margin-bottom: 0;
    }

    #facturacion .fiscal-help {
        margin-bottom: 14px;
        padding: 12px 14px;
    }

    #fiscalSummary {
        padding: 16px;
        border-color: #d7e2f0;
        background: #ffffff;
    }

    #fiscalSummary .fiscal-summary-header {
        gap: 14px;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    #fiscalSummary .fiscal-summary-header .muted {
        margin-bottom: 0;
    }

    #fiscalSummary .fiscal-summary-title {
        margin-bottom: 3px;
        font-size: 17px;
    }

    #fiscalSummary .fiscal-status {
        padding: 6px 10px;
        font-size: 11px;
    }

    #fiscalSummary .fiscal-summary-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    #fiscalSummary .fiscal-summary-item,
    #fiscalSummary .fiscal-summary-full {
        grid-column: auto;
    }

    #fiscalSummary .fiscal-summary-item {
        min-width: 0;
        padding: 10px 12px;
        border-radius: 9px;
    }

    #fiscalSummary .fiscal-summary-item span {
        margin-bottom: 3px;
        font-size: 11px;
    }

    #fiscalSummary .fiscal-summary-item strong {
        font-size: 13px;
        line-height: 1.35;
    }

    #fiscalSummary .fiscal-summary-actions {
        margin-top: 12px;
    }

    #fiscalSummary .fiscal-summary-actions .btn {
        margin-top: 0;
        padding: 11px 17px;
    }

    #fiscalFormContainer .fiscal-actions .btn {
        margin-top: 0;
    }

    @media (max-width: 800px) {
        #facturacion.card {
            padding: 16px;
        }

        #fiscalSummary {
            padding: 14px;
        }

        #fiscalSummary .fiscal-summary-grid {
            grid-template-columns: 1fr;
        }

        #fiscalSummary .fiscal-summary-actions .btn {
            width: 100%;
        }
    }
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo zigo-logo">
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
            <a href="#">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Configuración</div>
        <div class="subtitle">Administra tus datos fiscales, acceso e identidad.</div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="config-grid">
            <div class="card">
                <button class="tab active" type="button" data-tab="facturacion">Datos de facturación</button>
                <button class="tab" type="button" data-tab="seguridad">Acceso y seguridad</button>
                <button class="tab" type="button" data-tab="identidad">Mi identidad</button>
            </div>

            <div>
                <section
                    id="facturacion"
                    class="card config-section"
                >
                    <div class="fiscal-header">
                        <div>
                            <div class="section-title">
                                Datos de facturación
                            </div>

                            <div class="muted">
                                Registra los datos exactamente como
                                aparecen en tu Constancia de Situación
                                Fiscal.
                            </div>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="fiscal-errors">
                            <strong>
                                Revisa la información fiscal:
                            </strong>

                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="fiscal-help">
                        <strong>
                            Importante
                        </strong>

                        <div>
                            RFC, razón social, código postal y régimen
                            deben coincidir exactamente con los datos
                            registrados ante el SAT.
                        </div>

                        <a
                            href="https://www.sat.gob.mx/personas/tramites-del-rfc"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Consultar información en el SAT
                        </a>
                    </div>

                    @if($fiscalProfile)
                        <div
                            id="fiscalSummary"
                            class="fiscal-summary-card"
                        >
                            <div class="fiscal-summary-header">
                                <div>
                                    <div class="fiscal-summary-title">
                                        Información fiscal registrada
                                    </div>

                                    <div class="muted">
                                        Estos datos se utilizarán al solicitar
                                        una factura desde Mis pagos.
                                    </div>
                                </div>

                                <span class="fiscal-status">
                                    Datos guardados
                                </span>
                            </div>

                            <div class="fiscal-summary-grid">
                                <div class="fiscal-summary-item">
                                    <span>RFC</span>

                                    <strong>
                                        {{ $fiscalProfile->rfc }}
                                    </strong>
                                </div>

                                <div class="fiscal-summary-item">
                                    <span>Código postal fiscal</span>

                                    <strong>
                                        {{
                                            $fiscalProfile
                                                ->codigo_postal_fiscal
                                        }}
                                    </strong>
                                </div>

                                <div class="fiscal-summary-item fiscal-summary-full">
                                    <span>Nombre o razón social</span>

                                    <strong>
                                        {{
                                            $fiscalProfile
                                                ->razon_social
                                        }}
                                    </strong>
                                </div>

                                <div class="fiscal-summary-item">
                                    <span>Régimen fiscal</span>

                                    <strong>
                                        {{
                                            $fiscalProfile
                                                ->regimen_fiscal
                                        }}
                                        -
                                        {{
                                            $regimenesFiscales[
                                                $fiscalProfile
                                                    ->regimen_fiscal
                                            ] ?? 'Sin descripción'
                                        }}
                                    </strong>
                                </div>

                                <div class="fiscal-summary-item">
                                    <span>Uso de CFDI</span>

                                    <strong>
                                        {{
                                            $fiscalProfile
                                                ->uso_cfdi
                                        }}
                                        -
                                        {{
                                            $usosCfdi[
                                                $fiscalProfile
                                                    ->uso_cfdi
                                            ] ?? 'Sin descripción'
                                        }}
                                    </strong>
                                </div>

                                @if($fiscalProfile->direccion_fiscal)
                                    <div class="fiscal-summary-item fiscal-summary-full">
                                        <span>Dirección fiscal</span>

                                        <strong>
                                            {{
                                                $fiscalProfile
                                                    ->direccion_fiscal
                                            }}
                                        </strong>
                                    </div>
                                @endif

                                <div class="fiscal-summary-item fiscal-summary-full">
                                    <span>Correo para recibir facturas</span>

                                    <strong>
                                        {{
                                            $fiscalProfile
                                                ->email_facturacion
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <div class="fiscal-summary-actions">
                                <button
                                    id="editFiscalButton"
                                    class="btn"
                                    type="button"
                                >
                                    Editar datos fiscales
                                </button>
                            </div>
                        </div>
                    @endif

                    <div
                        id="fiscalFormContainer"
                        class="fiscal-form-container"
                        @if(
                            $fiscalProfile
                            && !$errors->any()
                        )
                            style="display:none;"
                        @endif
                    >

                    <form
                        method="POST"
                        action="{{ route(
                            'b2c.configuracion.fiscal.guardar'
                        ) }}"
                    >
                        @csrf

                        <div class="fiscal-grid">
                            <div>
                                <label for="rfc">
                                    RFC con homoclave
                                </label>

                                <input
                                    id="rfc"
                                    type="text"
                                    name="rfc"
                                    maxlength="13"
                                    value="{{ old(
                                        'rfc',
                                        $fiscalProfile->rfc ?? ''
                                    ) }}"
                                    placeholder="Ej. XXXXAAMMDDXXX"
                                    autocomplete="off"
                                    required
                                >
                            </div>

                            <div>
                                <label
                                    for="codigo_postal_fiscal"
                                >
                                    Código postal fiscal
                                </label>

                                <input
                                    id="codigo_postal_fiscal"
                                    type="text"
                                    name="codigo_postal_fiscal"
                                    maxlength="5"
                                    inputmode="numeric"
                                    value="{{ old(
                                        'codigo_postal_fiscal',
                                        $fiscalProfile
                                            ->codigo_postal_fiscal
                                            ?? ''
                                    ) }}"
                                    placeholder="Ej. XXXXX"
                                    required
                                >
                            </div>

                            <div class="fiscal-full">
                                <label for="razon_social">
                                    Nombre o razón social
                                </label>

                                <input
                                    id="razon_social"
                                    type="text"
                                    name="razon_social"
                                    maxlength="255"
                                    value="{{ old(
                                        'razon_social',
                                        $fiscalProfile
                                            ->razon_social
                                            ?? ''
                                    ) }}"
                                    placeholder="Tal como aparece en la constancia"
                                    required
                                >
                            </div>

                            <div>
                                <label for="regimen_fiscal">
                                    Régimen fiscal
                                </label>

                                <select
                                    id="regimen_fiscal"
                                    name="regimen_fiscal"
                                    required
                                >
                                    <option value="">
                                        -- Selecciona un régimen --
                                    </option>

                                    @foreach(
                                        $regimenesFiscales
                                        as $clave => $descripcion
                                    )
                                        <option
                                            value="{{ $clave }}"
                                            @selected(
                                                (string) old(
                                                    'regimen_fiscal',
                                                    $fiscalProfile
                                                        ->regimen_fiscal
                                                        ?? ''
                                                ) === (string) $clave
                                            )
                                        >
                                            {{ $clave }}
                                            -
                                            {{ $descripcion }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="uso_cfdi">
                                    Uso de CFDI
                                </label>

                                <select
                                    id="uso_cfdi"
                                    name="uso_cfdi"
                                    required
                                >
                                    <option value="">
                                        -- Selecciona un uso de CFDI --
                                    </option>

                                    @foreach(
                                        $usosCfdi
                                        as $clave => $descripcion
                                    )
                                        <option
                                            value="{{ $clave }}"
                                            @selected(
                                                (string) old(
                                                    'uso_cfdi',
                                                    $fiscalProfile
                                                        ->uso_cfdi
                                                        ?? ''
                                                ) === (string) $clave
                                            )
                                        >
                                            {{ $clave }}
                                            -
                                            {{ $descripcion }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fiscal-full">
                                <label for="direccion_fiscal">
                                    Dirección fiscal
                                    <span class="optional">
                                        Opcional
                                    </span>
                                </label>

                                <textarea
                                    id="direccion_fiscal"
                                    name="direccion_fiscal"
                                    maxlength="500"
                                    rows="3"
                                    placeholder="Calle, número, colonia, municipio y estado"
                                >{{ old(
                                    'direccion_fiscal',
                                    $fiscalProfile
                                        ->direccion_fiscal
                                        ?? ''
                                ) }}</textarea>
                            </div>

                            <div class="fiscal-full">
                                <label for="email_facturacion">
                                    Correo para recibir facturas
                                </label>

                                <input
                                    id="email_facturacion"
                                    type="email"
                                    name="email_facturacion"
                                    maxlength="255"
                                    value="{{ old(
                                        'email_facturacion',
                                        $fiscalProfile
                                            ->email_facturacion
                                            ?? auth()->user()->email
                                    ) }}"
                                    placeholder="facturacion@empresa.com"
                                    required
                                >
                            </div>
                        </div>

                        <div class="fiscal-actions">
                            @if($fiscalProfile)
                                <button
                                    id="cancelFiscalEdit"
                                    class="btn fiscal-cancel"
                                    type="button"
                                >
                                    Cancelar
                                </button>
                            @endif

                            <button
                                class="btn"
                                type="submit"
                            >
                                {{
                                    $fiscalProfile
                                        ? 'Actualizar datos fiscales'
                                        : 'Guardar datos fiscales'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
                </section>

                <br>

                <section id="seguridad" class="card config-section" style="display:none;">
                    <div class="section-title">Acceso y seguridad</div>
                    <div class="muted">Cambio de contraseña del usuario.</div>

                    <label>Contraseña actual</label>
                    <input type="password" disabled>

                    <label>Nueva contraseña</label>
                    <input type="password" disabled>

                    <label>Confirmar contraseña</label>
                    <input type="password" disabled>

                    <button class="btn" type="button" disabled>Actualizar contraseña</button>
                </section>

                <br>

                <section id="identidad" class="card config-section" style="display:none;">
                    <div class="section-title">Mi identidad</div>
                    <div class="muted">
                        Sube tu INE y una selfie sosteniendo tu INE para validación de seguridad logística.
                    </div>

                    <p>
                        Estado:
                        <span class="status">{{ $identity->status ?? 'SIN_VERIFICAR' }}</span>
                    </p>

                    <form method="POST" action="{{ route('b2c.configuracion.identidad.guardar') }}" enctype="multipart/form-data">
                        @csrf

                        <label>INE frontal</label>
                        <input type="file" name="ine_front" accept="image/*" required>

                        <label>INE reverso</label>
                        <input type="file" name="ine_back" accept="image/*" required>

                        <label>Selfie sosteniendo INE</label>
                        <input type="file" name="selfie_with_ine" accept="image/*" required>

                        <button class="btn" type="submit">Enviar a revisión</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>

<script>
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;

            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            document.querySelectorAll('.config-section').forEach(section => {
                section.style.display = section.id === target ? 'block' : 'none';
            });
        });
    });

    const fiscalSummary =
        document.getElementById(
            'fiscalSummary'
        );

    const fiscalFormContainer =
        document.getElementById(
            'fiscalFormContainer'
        );

    const editFiscalButton =
        document.getElementById(
            'editFiscalButton'
        );

    const cancelFiscalEdit =
        document.getElementById(
            'cancelFiscalEdit'
        );

    if (
        editFiscalButton
        && fiscalSummary
        && fiscalFormContainer
    ) {
        editFiscalButton.addEventListener(
            'click',
            function () {
                fiscalSummary.style.display =
                    'none';

                fiscalFormContainer.style.display =
                    'block';

                const firstField =
                    fiscalFormContainer
                        .querySelector('input');

                if (firstField) {
                    firstField.focus();
                }
            }
        );
    }

    if (
        cancelFiscalEdit
        && fiscalSummary
        && fiscalFormContainer
    ) {
        cancelFiscalEdit.addEventListener(
            'click',
            function () {
                fiscalFormContainer.style.display =
                    'none';

                fiscalSummary.style.display =
                    'block';
            }
        );
    }

</script>

</body>
</html>