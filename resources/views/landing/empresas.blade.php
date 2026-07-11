<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ZIGO Empresas | Soluciones logísticas B2B</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        a{text-decoration:none}
        .hero{background:linear-gradient(135deg,#2563eb,#4f46e5);color:white;padding:34px 22px 70px}
        .nav{max-width:1180px;margin:0 auto 50px;display:flex;justify-content:space-between;align-items:center;gap:20px}
        .logo{font-size:30px;font-weight:900;letter-spacing:.5px}
        .nav-links{display:flex;gap:22px;align-items:center}
        .nav-links a{color:white;font-weight:800}
        .btn-yellow{background:#facc15;color:#111827;padding:13px 20px;border-radius:12px;font-weight:900}
        .hero-grid{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:1.1fr .9fr;gap:34px;align-items:center}
        .eyebrow{text-transform:uppercase;letter-spacing:3px;font-size:12px;font-weight:900;color:#dbeafe;margin-bottom:14px}
        h1{font-size:52px;line-height:1.05;margin:0 0 18px;font-weight:900}
        .hero p{font-size:18px;line-height:1.6;color:#eef2ff;margin:0 0 26px}
        .hero-actions{display:flex;gap:14px;flex-wrap:wrap}
        .btn-white{background:white;color:#1d4ed8;padding:14px 22px;border-radius:12px;font-weight:900}
        .btn-outline{border:2px solid rgba(255,255,255,.7);color:white;padding:12px 20px;border-radius:12px;font-weight:900}
        .hero-card{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:28px;padding:30px;box-shadow:0 24px 60px rgba(0,0,0,.2)}
        .hero-card h3{font-size:24px;margin:0 0 18px}
        .hero-card ul{margin:0;padding-left:20px;line-height:2}
        .section{max-width:1180px;margin:0 auto;padding:70px 22px}
        .section-title{text-align:center;font-size:38px;margin:0 0 14px;font-weight:900}
        .section-subtitle{text-align:center;max-width:760px;margin:0 auto 38px;color:#64748b;line-height:1.6}
        .cards{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
        .card{background:white;border-radius:20px;padding:24px;box-shadow:0 12px 30px rgba(15,23,42,.08)}
        .card h3{margin:0 0 10px;color:#1d4ed8}
        .card p{color:#475569;line-height:1.5}
        .form-section{background:#111827;color:white}
        .form-wrap{max-width:1180px;margin:0 auto;padding:70px 22px;display:grid;grid-template-columns:.9fr 1.1fr;gap:30px;align-items:start}
        .form-info h2{font-size:38px;margin:0 0 16px}
        .form-info p{color:#cbd5e1;line-height:1.7}
        .form-box{background:white;color:#111827;border-radius:24px;padding:28px;box-shadow:0 24px 60px rgba(0,0,0,.25)}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        label{font-weight:900;font-size:14px;display:block;margin-bottom:6px}
        input,select,textarea{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:13px;font-size:15px}
        textarea{min-height:110px;resize:vertical}
        .full{grid-column:1/-1}
        .submit{background:#2563eb;color:white;border:none;border-radius:12px;padding:15px 20px;font-weight:900;font-size:16px;width:100%;cursor:pointer}
        .alert{background:#dcfce7;color:#166534;border-radius:12px;padding:14px;margin-bottom:18px;font-weight:800}
        .footer{background:#020617;color:#cbd5e1;padding:36px 22px}
        .footer-inner{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:24px}
        .footer h4{color:white;margin:0 0 12px}
        .footer a{display:block;color:#cbd5e1;margin:8px 0}
        .whatsapp{position:fixed;right:22px;bottom:22px;background:#22c55e;color:white;padding:14px 18px;border-radius:999px;font-weight:900;box-shadow:0 12px 30px rgba(34,197,94,.35);z-index:99}

        @media(max-width:900px){
            .nav{align-items:flex-start}
            .nav-links{display:none}
            .hero-grid,.form-wrap{grid-template-columns:1fr}
            h1{font-size:38px}
            .cards{grid-template-columns:1fr 1fr}
            .footer-inner{grid-template-columns:1fr}
        }

        @media(max-width:600px){
            .hero{padding:26px 18px 50px}
            h1{font-size:34px}
            .section-title{font-size:30px}
            .cards{grid-template-columns:1fr}
            .form-grid{grid-template-columns:1fr}
            .full{grid-column:auto}
        }
    </style>
</head>
<body>

<section class="hero">
    <nav class="nav">
        <a href="{{ url('/') }}" class="logo" style="color:white;">ZIGO</a>
        <div class="nav-links">
            <a href="{{ url('/') }}">Inicio</a>
            <a href="{{ url('/#faq') }}">FAQ</a>
            <a href="{{ url('/#paqueterias') }}">Paqueterías</a>
            <a href="{{ route('crm.login') }}">Iniciar sesión</a>
            <a href="#solicitud" class="btn-yellow">Solicitar cuenta</a>
        </div>
    </nav>

    <div class="hero-grid">
        <div>
            <div class="eyebrow">ZIGO Empresas</div>
            <h1>Soluciones logísticas para empresas que necesitan control y crecimiento</h1>
            <p>
                Administra usuarios, direcciones, saldos, reportes y generación de guías desde una sola plataforma.
                Ideal para negocios, ecommerce, sucursales y operaciones con envíos frecuentes.
            </p>
            <div class="hero-actions">
                <a href="#solicitud" class="btn-white">Solicitar asesoría</a>
                <a href="{{ url('/') }}" class="btn-outline">Cotizar un envío</a>
            </div>
        </div>

        <div class="hero-card">
            <h3>¿Qué incluye ZIGO Empresas?</h3>
            <ul>
                <li>Acceso privado para tu negocio</li>
                <li>Usuarios y operación multiárea</li>
                <li>Direcciones frecuentes</li>
                <li>Reportes de consumo</li>
                <li>Control de saldos y pagos</li>
                <li>Generación de guías</li>
                <li>Atención y seguimiento comercial</li>
            </ul>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Todo lo que tu operación necesita</h2>
    <p class="section-subtitle">
        ZIGO Empresas está diseñado para negocios que quieren dejar de operar envíos de forma manual y comenzar a centralizar su logística.
    </p>

    <div class="cards">
        <div class="card">
            <h3>Multiusuario</h3>
            <p>Permite que diferentes personas de tu empresa operen envíos bajo una misma cuenta.</p>
        </div>
        <div class="card">
            <h3>Direcciones</h3>
            <p>Guarda remitentes, destinatarios, sucursales y ubicaciones frecuentes.</p>
        </div>
        <div class="card">
            <h3>Reportes</h3>
            <p>Consulta movimientos, guías, pagos y consumo operativo desde el CRM.</p>
        </div>
        <div class="card">
            <h3>API Hub</h3>
            <p>Integra servicios como códigos postales, colonias, cotización y rastreo.</p>
        </div>
    </div>
</section>

<section class="form-section" id="solicitud">
    <div class="form-wrap">
        <div class="form-info">
            <h2>Solicita una cuenta empresarial</h2>
            <p>
                Déjanos tus datos y un asesor ZIGO revisará tu solicitud.
                Desde el CRM podremos dar seguimiento a tu caso, conocer tus necesidades y proponerte una solución adecuada.
            </p>
            <p>
                Recomendado para empresas con envíos recurrentes, tiendas online, distribuidores, emprendedores con volumen o equipos comerciales.
            </p>
        </div>

        <div class="form-box">
            @if(session('success'))
                <div class="alert">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('landing.empresas.store') }}">
                @csrf

                <div class="form-grid">
                    <div>
                        <label>Nombre completo</label>
                        <input name="name" value="{{ old('name') }}" required>
                    </div>

                    <div>
                        <label>Empresa</label>
                        <input name="company_name" value="{{ old('company_name') }}" required>
                    </div>

                    <div>
                        <label>Correo electrónico</label>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                    </div>

                    <div>
                        <label>Teléfono / WhatsApp</label>
                        <input name="phone" value="{{ old('phone') }}" required>
                    </div>

                    <div>
                        <label>Ciudad / Estado</label>
                        <input name="city" value="{{ old('city') }}">
                    </div>

                    <div>
                        <label>Envíos estimados por mes</label>
                        <select name="monthly_shipments">
                            <option value="">Selecciona una opción</option>
                            <option value="1 a 20">1 a 20</option>
                            <option value="21 a 100">21 a 100</option>
                            <option value="101 a 500">101 a 500</option>
                            <option value="Más de 500">Más de 500</option>
                        </select>
                    </div>

                    <div class="full">
                        <label>¿Qué te interesa?</label>
                        <select name="interest">
                            <option value="">Selecciona una opción</option>
                            <option value="Cuenta empresarial">Cuenta empresarial</option>
                            <option value="Tarifas y paqueterías">Tarifas y paqueterías</option>
                            <option value="Guías masivas">Guías masivas</option>
                            <option value="API Hub / integración">API Hub / integración</option>
                            <option value="Soporte operativo">Soporte operativo</option>
                        </select>
                    </div>

                    <div class="full">
                        <label>Mensaje</label>
                        <textarea name="message" placeholder="Cuéntanos brevemente qué necesitas">{{ old('message') }}</textarea>
                    </div>

                    <div class="full">
                        <button class="submit" type="submit">Enviar solicitud</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="footer-inner">
        <div>
            <h4>ZIGO</h4>
            <p>Plataforma logística digital para personas, negocios e integradores.</p>
        </div>
        <div>
            <h4>Soluciones</h4>
            <a href="{{ url('/') }}">B2C</a>
            <a href="{{ route('landing.empresas') }}">Empresas B2B</a>
            <a href="{{ route('hub.login') }}">API Hub</a>
        </div>
        <div>
            <h4>Ayuda</h4>
            <a href="{{ url('/#faq') }}">FAQ</a>
            <a href="{{ route('soporte.login') }}">Soporte</a>
            <a href="{{ url('/') }}">Rastrear envío</a>
        </div>
        <div>
            <h4>Legal</h4>
            <a href="#">Aviso de privacidad</a>
            <a href="#">Términos y condiciones</a>
            <a href="#">Política de uso</a>
        </div>
    </div>
</footer>

<a class="whatsapp"
   href="https://wa.me/5215555555555?text=Hola%20ZIGO%2C%20quiero%20informaci%C3%B3n%20sobre%20cuenta%20empresarial"
   target="_blank">
    WhatsApp
</a>

</body>
</html>