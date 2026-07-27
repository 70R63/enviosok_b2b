@php
    $editando = !empty($cotizacionEdicion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $editando ? 'Editar envío' : 'Nuevo envío' }} - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
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
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        h2{margin-top:0}
        label{font-weight:800;font-size:13px;margin-top:12px;display:block}
        input,select{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;box-sizing:border-box;background:white}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .btn{margin-top:24px;background:#2563eb;color:white;border:none;border-radius:12px;padding:14px 28px;font-weight:900;cursor:pointer}
        .suggestions{background:white;border:1px solid #e5e7eb;border-radius:10px;position:absolute;z-index:10;width:100%;box-shadow:0 10px 24px rgba(0,0,0,.12)}
        .suggestion-item{padding:12px;cursor:pointer}
        .suggestion-item:hover{background:#eff6ff}
        .autocomplete-wrap{position:relative}
        .select-address{
            width:100%;
            height:42px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            padding:0 12px;
            box-sizing:border-box;
            background:white;
        }
        .helper-box{
            margin-top:14px;
            padding:14px;
            border:1px solid #bfdbfe;
            background:#eff6ff;
            border-radius:12px;
            color:#1e3a8a;
            font-size:13px;
            font-weight:700;
        }
    </style>
</head>
<body>

<div class="layout">
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
        <div class="title">{{ $editando ? 'Editar envío' : 'Información del envío' }}</div>
        <div class="subtitle">
            {{ $editando
                ? 'Actualiza origen o destino. El servicio y el precio deberán cotizarse nuevamente.'
                : 'Captura los datos de origen y destino.'
            }}
        </div>

        @if(session('error'))
            <div class="helper-box" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;margin-bottom:18px;">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="helper-box" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;margin-bottom:18px;">
                <strong>Revisa la información capturada:</strong>
                <ul style="margin:8px 0 0;padding-left:20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ $editando
                ? route('b2c.envios.actualizar', $cotizacionEdicion->id)
                : route('b2c.nuevo-envio.guardar')
            }}"
        >
            @csrf
            @if($editando)
                @method('PUT')
            @endif

            <div class="grid">
                <section class="card">
                    <h2>Datos del origen</h2>

                    @if(($direccionesOrigen ?? collect())->isNotEmpty())
                        <label>Direcciones de origen guardadas</label>
                        <select id="direccion_origen_select" onchange="cargarDireccion('origen', this.value)">
                            <option value="">-- Selecciona una dirección --</option>
                            @foreach($direccionesOrigen as $direccion)
                                <option value='@json($direccion)'>
                                    {{ $direccion->alias ?: $direccion->nombre }} - {{ $direccion->cp }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <label>Nombre completo del remitente</label>
                    <input name="remitente_nombre" value="{{ old('remitente_nombre', $cotizacionEdicion->remitente_nombre ?? '') }}" required>

                    <div class="row">
                        <div>
                            <label>Empresa opcional</label>
                            <input name="remitente_empresa" value="{{ old('remitente_empresa') }}">
                        </div>
                        <div>
                            <label>Teléfono</label>
                            <input name="remitente_telefono" value="{{ old('remitente_telefono', $cotizacionEdicion->remitente_telefono ?? '') }}" required>
                        </div>
                    </div>

                    <label>Correo electrónico</label>
                    <input type="email" name="remitente_email" value="{{ old('remitente_email', $cotizacionEdicion->remitente_email ?? '') }}">

                    <label>Calle / Avenida</label>
                    <input name="remitente_direccion" value="{{ old('remitente_direccion', $cotizacionEdicion->remitente_direccion ?? '') }}" required>

                    <div class="row">
                        <div>
                            <label>No. Exterior</label>
                            <input name="remitente_num_ext" value="{{ old('remitente_num_ext', $cotizacionEdicion->remitente_num_ext ?? '') }}" required>
                        </div>
                        <div>
                            <label>No. Interior</label>
                            <input name="remitente_num_int" value="{{ old('remitente_num_int', $cotizacionEdicion->remitente_num_int ?? '') }}">
                        </div>
                    </div>

                    <label>Referencias</label>
                    <input name="remitente_referencias" value="{{ old('remitente_referencias') }}">

                    <div class="autocomplete-wrap">
                        <label>Código postal origen</label>
                        <input type="text" id="cp_origen" name="cp_origen" value="{{ old('cp_origen', $cotizacionEdicion->cp_origen ?? '') }}" autocomplete="off" required>
                        <input type="hidden" id="colonia_origen" name="colonia_origen" value="{{ old('colonia_origen', $cotizacionEdicion->colonia_origen ?? '') }}">
                        <input type="hidden" id="ciudad_origen" name="ciudad_origen" value="{{ old('ciudad_origen', $cotizacionEdicion->ciudad_origen ?? '') }}">
                        <input type="hidden" id="estado_origen" name="estado_origen" value="{{ old('estado_origen', $cotizacionEdicion->estado_origen ?? '') }}">
                        <div id="colonias_origen_list" class="suggestions"></div>
                    </div>

                    <label>Colonia origen</label>
                    <input id="colonia_origen_label" value="{{ old('colonia_origen', $cotizacionEdicion->colonia_origen ?? '') }}" readonly>

                    <div class="row">
                        <div>
                            <label>Municipio / Ciudad</label>
                            <input id="ciudad_origen_label" value="{{ old('ciudad_origen', $cotizacionEdicion->ciudad_origen ?? '') }}" readonly>
                        </div>
                        <div>
                            <label>Estado</label>
                            <input id="estado_origen_label" value="{{ old('estado_origen', $cotizacionEdicion->estado_origen ?? '') }}" readonly>
                        </div>
                    </div>

                    @unless($editando)
                        <label style="margin-top:16px;">
                            <input type="checkbox" name="guardar_origen" value="1" style="width:auto;height:auto;">
                            Guardar esta dirección de origen
                        </label>

                        <input name="alias_origen" placeholder="Alias opcional: Casa, Oficina, Bodega" style="margin-top:8px;">
                    @endunless

                </section>

                <section class="card">
                    <h2>Datos del destino</h2>

                    @if(($direccionesDestino ?? collect())->isNotEmpty())
                        <label>Direcciones de destino guardadas</label>
                        <select id="direccion_destino_select" onchange="cargarDireccion('destino', this.value)">
                            <option value="">-- Selecciona una dirección --</option>
                            @foreach($direccionesDestino as $direccion)
                                <option value='@json($direccion)'>
                                    {{ $direccion->alias ?: $direccion->nombre }} - {{ $direccion->cp }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <label>Nombre completo del destinatario</label>
                    <input name="destinatario_nombre" value="{{ old('destinatario_nombre', $cotizacionEdicion->destinatario_nombre ?? '') }}" required>

                    <div class="row">
                        <div>
                            <label>Empresa opcional</label>
                            <input name="destinatario_empresa" value="{{ old('destinatario_empresa') }}">
                        </div>
                        <div>
                            <label>Teléfono</label>
                            <input name="destinatario_telefono" value="{{ old('destinatario_telefono', $cotizacionEdicion->destinatario_telefono ?? '') }}" required>
                        </div>
                    </div>

                    <label>Correo electrónico</label>
                    <input type="email" name="destinatario_email" value="{{ old('destinatario_email', $cotizacionEdicion->destinatario_email ?? '') }}">

                    <label>Calle / Avenida</label>
                    <input name="destinatario_direccion" value="{{ old('destinatario_direccion', $cotizacionEdicion->destinatario_direccion ?? '') }}" required>

                    <div class="row">
                        <div>
                            <label>No. Exterior</label>
                            <input name="destinatario_num_ext" value="{{ old('destinatario_num_ext', $cotizacionEdicion->destinatario_num_ext ?? '') }}" required>
                        </div>
                        <div>
                            <label>No. Interior</label>
                            <input name="destinatario_num_int" value="{{ old('destinatario_num_int', $cotizacionEdicion->destinatario_num_int ?? '') }}">
                        </div>
                    </div>

                    <label>Referencias</label>
                    <input name="destinatario_referencias" value="{{ old('destinatario_referencias') }}">

                    <div class="autocomplete-wrap">
                        <label>Código postal destino</label>
                        <input type="text" id="cp_destino" name="cp_destino" value="{{ old('cp_destino', $cotizacionEdicion->cp_destino ?? '') }}" autocomplete="off" required>
                        <input type="hidden" id="colonia_destino" name="colonia_destino" value="{{ old('colonia_destino', $cotizacionEdicion->colonia_destino ?? '') }}">
                        <input type="hidden" id="ciudad_destino" name="ciudad_destino" value="{{ old('ciudad_destino', $cotizacionEdicion->ciudad_destino ?? '') }}">
                        <input type="hidden" id="estado_destino" name="estado_destino" value="{{ old('estado_destino', $cotizacionEdicion->estado_destino ?? '') }}">
                        <div id="colonias_destino_list" class="suggestions"></div>
                    </div>

                    <label>Colonia destino</label>
                    <input id="colonia_destino_label" value="{{ old('colonia_destino', $cotizacionEdicion->colonia_destino ?? '') }}" readonly>

                    <div class="row">
                        <div>
                            <label>Municipio / Ciudad</label>
                            <input id="ciudad_destino_label" value="{{ old('ciudad_destino', $cotizacionEdicion->ciudad_destino ?? '') }}" readonly>
                        </div>
                        <div>
                            <label>Estado</label>
                            <input id="estado_destino_label" value="{{ old('estado_destino', $cotizacionEdicion->estado_destino ?? '') }}" readonly>
                        </div>
                    </div>

                    @unless($editando)
                        <label style="margin-top:16px;">
                            <input type="checkbox" name="guardar_destino" value="1" style="width:auto;height:auto;">
                            Guardar esta dirección de destino
                        </label>

                        <input name="alias_destino" placeholder="Alias opcional: Cliente, Oficina, Casa" style="margin-top:8px;">
                    @endunless
                </section>
            </div>

            <button type="submit" class="btn">{{ $editando ? 'Guardar y volver a cotizar' : 'Siguiente' }}</button>
        </form>
    </main>
</div>

<script>
function cargarDireccion(tipo, json) {
    if (!json) return;

    const d = JSON.parse(json);

    const prefijo = tipo === 'origen' ? 'remitente' : 'destinatario';
    const geo = tipo === 'origen' ? 'origen' : 'destino';

    document.querySelector(`[name="${prefijo}_nombre"]`).value = d.nombre || '';
    document.querySelector(`[name="${prefijo}_empresa"]`).value = d.empresa || '';
    document.querySelector(`[name="${prefijo}_email"]`).value = d.email || '';
    document.querySelector(`[name="${prefijo}_telefono"]`).value = d.telefono || '';
    document.querySelector(`[name="${prefijo}_direccion"]`).value = d.calle || '';
    document.querySelector(`[name="${prefijo}_num_ext"]`).value = d.num_ext || '';
    document.querySelector(`[name="${prefijo}_num_int"]`).value = d.num_int || '';
    document.querySelector(`[name="${prefijo}_referencias"]`).value = d.referencias || '';

    document.getElementById(`cp_${geo}`).value = d.cp || '';
    document.getElementById(`colonia_${geo}`).value = d.colonia || '';
    document.getElementById(`ciudad_${geo}`).value = d.ciudad || '';
    document.getElementById(`estado_${geo}`).value = d.estado || '';

    document.getElementById(`colonia_${geo}_label`).value = d.colonia || '';
    document.getElementById(`ciudad_${geo}_label`).value = d.ciudad || '';
    document.getElementById(`estado_${geo}_label`).value = d.estado || '';
}
</script>

<script src="/js/b2c-cp-autocomplete.js?v={{ time() }}"></script>
</body>
</html>