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
                <section id="facturacion" class="card config-section">
                    <div class="section-title">Datos de facturación</div>
                    <div class="muted">Próximamente podrás registrar RFC, razón social, régimen fiscal y uso CFDI.</div>
                    <button class="btn" type="button">Agregar datos fiscales</button>
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
</script>

</body>
</html>