<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo envío - ZIGO</title>
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
        input{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;box-sizing:border-box}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .btn{margin-top:24px;background:#2563eb;color:white;border:none;border-radius:12px;padding:14px 28px;font-weight:900;cursor:pointer}
        .suggestions{background:white;border:1px solid #e5e7eb;border-radius:10px;position:absolute;z-index:10;width:100%;box-shadow:0 10px 24px rgba(0,0,0,.12)}
        .suggestion-item{padding:12px;cursor:pointer}
        .suggestion-item:hover{background:#eff6ff}
        .autocomplete-wrap{position:relative}
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
        <div class="title">Información del envío</div>
        <div class="subtitle">Captura los datos de origen y destino.</div>

        <form method="POST" action="{{ route('b2c.nuevo-envio.guardar') }}">
            @csrf

            <div class="grid">
                <section class="card">
                    <h2>Datos del origen</h2>

                    <label>Nombre completo del remitente</label>
                    <input name="remitente_nombre" required>

                    <div class="row">
                        <div>
                            <label>Empresa opcional</label>
                            <input name="remitente_empresa">
                        </div>
                        <div>
                            <label>Teléfono</label>
                            <input name="remitente_telefono" required>
                        </div>
                    </div>

                    <label>Correo electrónico</label>
                    <input type="email" name="remitente_email">

                    <label>Calle / Avenida</label>
                    <input name="remitente_direccion" required>

                    <div class="row">
                        <div>
                            <label>No. Exterior</label>
                            <input name="remitente_num_ext" required>
                        </div>
                        <div>
                            <label>No. Interior</label>
                            <input name="remitente_num_int">
                        </div>
                    </div>

                    <label>Referencias</label>
                    <input name="remitente_referencias">

                    <div class="autocomplete-wrap">
                        <label>Código postal origen</label>
                        <input type="text" id="cp_origen" name="cp_origen" autocomplete="off" required>
                        <input type="hidden" id="colonia_origen" name="colonia_origen">
                        <input type="hidden" id="ciudad_origen" name="ciudad_origen">
                        <input type="hidden" id="estado_origen" name="estado_origen">
                        <div id="colonias_origen_list" class="suggestions"></div>
                    </div>

                    <label>Colonia origen</label>
                    <input id="colonia_origen_label" readonly>

                    <div class="row">
                        <div>
                            <label>Municipio / Ciudad</label>
                            <input id="ciudad_origen_label" readonly>
                        </div>
                        <div>
                            <label>Estado</label>
                            <input id="estado_origen_label" readonly>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>Datos del destino</h2>

                    <label>Nombre completo del destinatario</label>
                    <input name="destinatario_nombre" required>

                    <div class="row">
                        <div>
                            <label>Empresa opcional</label>
                            <input name="destinatario_empresa">
                        </div>
                        <div>
                            <label>Teléfono</label>
                            <input name="destinatario_telefono" required>
                        </div>
                    </div>

                    <label>Correo electrónico</label>
                    <input type="email" name="destinatario_email">

                    <label>Calle / Avenida</label>
                    <input name="destinatario_direccion" required>

                    <div class="row">
                        <div>
                            <label>No. Exterior</label>
                            <input name="destinatario_num_ext" required>
                        </div>
                        <div>
                            <label>No. Interior</label>
                            <input name="destinatario_num_int">
                        </div>
                    </div>

                    <label>Referencias</label>
                    <input name="destinatario_referencias">

                    <div class="autocomplete-wrap">
                        <label>Código postal destino</label>
                        <input type="text" id="cp_destino" name="cp_destino" autocomplete="off" required>
                        <input type="hidden" id="colonia_destino" name="colonia_destino">
                        <input type="hidden" id="ciudad_destino" name="ciudad_destino">
                        <input type="hidden" id="estado_destino" name="estado_destino">
                        <div id="colonias_destino_list" class="suggestions"></div>
                    </div>

                    <label>Colonia destino</label>
                    <input id="colonia_destino_label" readonly>

                    <div class="row">
                        <div>
                            <label>Municipio / Ciudad</label>
                            <input id="ciudad_destino_label" readonly>
                        </div>
                        <div>
                            <label>Estado</label>
                            <input id="estado_destino_label" readonly>
                        </div>
                    </div>
                </section>
            </div>

            <button type="submit" class="btn">Siguiente</button>
        </form>
    </main>
</div>

<script src="{{ asset('js/b2c-cp-autocomplete.js') }}"></script>
</body>
</html>