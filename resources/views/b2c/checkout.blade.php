<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout | EnvíosOK</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:35px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px;color:#111827}
        .subtitle{color:#64748b;margin-bottom:30px}
        .grid{display:grid;grid-template-columns:1fr 360px;gap:24px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:20px}
        h2{margin-top:0}
        .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
        .full{grid-column:1 / -1}
        label{display:block;font-weight:800;font-size:14px;margin-bottom:6px}
        input{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;box-sizing:border-box}
        input[readonly]{background:#f8fafc;color:#475569}
        .summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:15px}
        .total{border-top:1px solid #e5e7eb;padding-top:16px;font-size:24px;font-weight:900}
        .btn{width:100%;border:none;background:#f97316;color:white;padding:15px;border-radius:12px;font-weight:900;cursor:pointer;font-size:16px;margin-top:18px}
        .muted{color:#64748b;font-size:13px}
        @media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{display:none}.grid,.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo">EnvíosOK</div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.dashboard') }}#cotizador">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="#">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="#">Mis direcciones</a>
            <a href="#">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Completa los datos de tu guía</div>
        <div class="subtitle">Captura remitente, destinatario y contenido del paquete.</div>

        <form method="POST" action="/b2c/checkout/{{ $cotizacion->id }}">
            @csrf

            <div class="grid">
                <div>
                    <section class="card">
                        <h2>Remitente</h2>

                        <div class="form-grid">
                            <div>
                                <label>Nombre completo</label>
                                <input type="text" name="remitente_nombre" value="{{ auth()->user()->name ?? '' }}" required>
                            </div>

                            <div>
                                <label>Teléfono</label>
                                <input type="text" name="remitente_telefono" required>
                            </div>

                            <div class="full">
                                <label>Correo electrónico</label>
                                <input type="email" name="remitente_email" value="{{ auth()->user()->email ?? '' }}" required>
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

                    <section class="card">
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
                </div>

                <aside class="card">
                    <h2>Resumen</h2>

                    <div class="summary-row"><span>Mensajería</span><strong>{{ $cotizacion->logistico }}</strong></div>
                    <div class="summary-row"><span>Servicio</span><strong>{{ $cotizacion->servicio }}</strong></div>
                    <div class="summary-row"><span>Origen</span><strong>{{ $cotizacion->cp_origen }}</strong></div>
                    <div class="summary-row"><span>Destino</span><strong>{{ $cotizacion->cp_destino }}</strong></div>
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
                </aside>
            </div>
        </form>
    </main>
</div>

</body>
</html>