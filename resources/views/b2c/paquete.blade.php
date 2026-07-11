<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Información del paquete - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:34px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:25px}
        .grid{display:grid;grid-template-columns:2fr 1fr;gap:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:20px}
        label{font-weight:800;font-size:14px;margin-bottom:6px;display:block}
        input,select{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:10px;margin-bottom:14px;box-sizing:border-box}
        .row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
        .btn{background:#2563eb;color:white;border:none;border-radius:10px;padding:13px 22px;font-weight:900;cursor:pointer}
        .muted{color:#64748b;font-size:14px}
        .summary p{margin:8px 0}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <h2>ZIGO</h2>

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
        <div class="title">Información del paquete</div>
        <div class="subtitle">Captura dimensiones, peso y contenido del envío.</div>

        <div class="grid">
            <div>
                @if($errors->any())
                    <div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;font-weight:800;margin-bottom:18px;">
                        Revisa los campos obligatorios antes de continuar.
                    </div>
                @endif
                <form method="POST" action="{{ route('b2c.paquete.guardar', $cotizacion->id) }}">
                    @csrf

                    <div class="card">
                        <h2>Dimensiones y peso</h2>

                        <label>Tipo de envío</label>
                        <select name="tipo_envio" required>
                            <option value="">Selecciona</option>
                            <option value="caja" {{ old('tipo_envio', $cotizacion->tipo_envio) == 'caja' ? 'selected' : '' }}>Caja</option>
                            <option value="sobre" {{ old('tipo_envio', $cotizacion->tipo_envio) == 'sobre' ? 'selected' : '' }}>Sobre</option>
                        </select>

                        <label>Peso kg</label>
                            <input type="number" step="0.01" id="peso" name="peso" value="{{ old('peso', $cotizacion->peso) }}" placeholder="Ej. 2.5" required>

                            @php
                                $medidas = !empty($cotizacion->medidas)
                                    ? explode('x', $cotizacion->medidas)
                                    : [null, null, null];
                            @endphp

                            <label>Dimensiones cm</label>

                            <div class="row">
                                <input type="number" id="largo" name="largo" placeholder="Largo" value="{{ old('largo', $medidas[0]) }}" required>
                                <input type="number" id="ancho" name="ancho" placeholder="Ancho" value="{{ old('ancho', $medidas[1]) }}" required>
                                <input type="number" id="alto" name="alto" placeholder="Alto" value="{{ old('alto', $medidas[2]) }}" required>
                            </div>

                        <div style="background:#f8fafc;border-radius:12px;padding:14px;margin-top:8px;margin-bottom:14px">
                            <p><strong>Peso real:</strong> <span id="peso_real_txt">0</span> kg</p>
                            <p><strong>Peso volumétrico:</strong> <span id="peso_vol_txt">0</span> kg</p>
                            <p><strong>Peso a cotizar:</strong> <span id="peso_cotizar_txt">0</span> kg</p>
                        </div>

                        <p class="muted" style="background:#fff7ed;border:1px solid #fed7aa;color:#ea580c;padding:12px;border-radius:10px;">
                            ⚠️ Si el paquete pesa más o es más grande de lo declarado, podrían aplicar cargos adicionales por el envío.
                        </p>
                        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                            <button type="button"
                                    id="limpiarDimensiones"
                                    style="
                                        background:#ef4444;
                                        color:white;
                                        border:none;
                                        padding:10px 18px;
                                        border-radius:8px;
                                        cursor:pointer;
                                        font-weight:bold;">
                                Limpiar datos
                            </button>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Valor y contenido</h2>

                        <label>Contenido del envío</label>
                        <input type="text" name="contenido" value="{{ old('contenido', $cotizacion->contenido) }}" placeholder="Ej. ropa, documentos, accesorios" required>

                        <label>Valor declarado MXN</label>
                        <input type="number" step="0.01" name="valor_declarado" value="{{ old('valor_declarado', $cotizacion->valor_declarado ?? 0) }}">
                    </div>

                    <div class="card">
                        <h2>Objetos prohibidos para envío</h2>

                        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:14px;text-align:center;margin-top:18px;">
                            @foreach([
                                '🔫 Armas',
                                '🔥 Encendedores',
                                '🧯 Inflamables',
                                '☢️ Materiales tóxicos',
                                '🚬 Vapes',
                                '💥 Explosivos',
                                '☣️ Químicos',
                                '🔋 Baterías',
                                '⛽ Gasolina',
                                '🔪 Arma blanca',
                                '💊 Drogas',
                                '🧴 Gas aerosol'
                            ] as $item)
                                <div style="border:1px solid #fecaca;border-radius:12px;padding:12px;background:#fff5f5;font-weight:800;">
                                    <div style="font-size:30px;margin-bottom:6px;">{{ explode(' ', $item)[0] }}</div>
                                    <div style="font-size:13px;">{{ trim(substr($item, strpos($item, ' ') + 1)) }}</div>
                                </div>
                            @endforeach
                        </div>

                            <div style="margin-top:20px;padding:18px;border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;">

                                <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;line-height:1.7;">

                                    <input
                                        type="checkbox"
                                        id="acepta_no_prohibidos"
                                        name="acepta_no_prohibidos"
                                        value="1"
                                        style="margin-top:5px;width:18px;height:18px;">

                                    <span>

                                        <strong>⚠ Declaración del remitente</strong>

                                        <br><br>

                                        Confirmo que el contenido de este envío
                                        <strong>NO contiene artículos prohibidos,
                                        restringidos, peligrosos o ilegales</strong>
                                        y acepto las políticas de transporte de ZIGO.

                                        <br><br>

                                        Asimismo, acepto que proporcionar información falsa
                                        podrá ocasionar la cancelación del envío,
                                        retención del paquete o cargos adicionales.

                                    </span>

                                </label>

                            </div>

                            <div id="mensajeAceptacion"
                                style="
                                    display:none;
                                    margin-top:12px;
                                    padding:14px;
                                    border-radius:10px;
                                    background:#fee2e2;
                                    border:1px solid #ef4444;
                                    color:#991b1b;
                                    font-weight:bold;">

                                Debes aceptar la declaración del remitente para poder cotizar el envío.

                            </div>
                        </div>

                    <button class="btn" type="submit">Cotizar envío</button>
                </form>
            </div>

            <div class="card summary">
                <h2>Resumen</h2>
                <p><strong>Origen:</strong> {{ $cotizacion->cp_origen }} - {{ $cotizacion->colonia_origen }}</p>
                <p><strong>Destino:</strong> {{ $cotizacion->cp_destino }} - {{ $cotizacion->colonia_destino }}</p>
            </div>
        </div>
    </main>
</div>

<script>
function calcularPesoVolumetrico() {
    const peso = parseFloat(document.getElementById('peso').value) || 0;
    const largo = parseFloat(document.getElementById('largo').value) || 0;
    const ancho = parseFloat(document.getElementById('ancho').value) || 0;
    const alto = parseFloat(document.getElementById('alto').value) || 0;

    if (peso === 0 && largo === 0 && ancho === 0 && alto === 0) {
        document.getElementById('peso_real_txt').innerText = '--';
        document.getElementById('peso_vol_txt').innerText = '--';
        document.getElementById('peso_cotizar_txt').innerText = '--';
        return;
    }

    const pesoVolumetrico = (largo * ancho * alto) / 5000;
    const pesoCotizar = Math.max(peso, pesoVolumetrico);

    document.getElementById('peso_real_txt').innerText = peso.toFixed(2);
    document.getElementById('peso_vol_txt').innerText = pesoVolumetrico.toFixed(2);
    document.getElementById('peso_cotizar_txt').innerText = pesoCotizar.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function () {
    ['peso','largo','ancho','alto'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcularPesoVolumetrico);
    });

    calcularPesoVolumetrico();
});

document.getElementById('limpiarDimensiones').addEventListener('click', function () {

    document.getElementById('peso').value='';
    document.getElementById('largo').value='';
    document.getElementById('ancho').value='';
    document.getElementById('alto').value='';

    calcularPesoVolumetrico();
});

document.querySelector('form').addEventListener('submit', function(e){

    const chk = document.getElementById('acepta_no_prohibidos');

    if(!chk.checked){

        e.preventDefault();

        document.getElementById('mensajeAceptacion').style.display='block';

        chk.scrollIntoView({
            behavior:'smooth',
            block:'center'
        });

        return false;
    }

});
</script>

</body>
</html>