<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Negocios ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#0f766e;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.12);padding:13px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .card{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-weight:800;font-size:14px}
        .value{font-size:34px;font-weight:900;margin-top:8px}
        .section{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo">ZIGO Negocios</div>

        <div class="menu">
            <a href="{{ route('negocios.dashboard') }}">Dashboard</a>
            <a href="#">Nuevo envío</a>
            <a href="#">Mis guías</a>
            <a href="#">Cotizaciones</a>
            <a href="#">Usuarios</a>
            <a href="#">Sucursales</a>
            <a href="#">Direcciones</a>
            <a href="#">Saldo</a>
            <a href="#">Facturación</a>
            <a href="#">Adeudos</a>
            <a href="#">Reportes</a>
            <a href="#">API</a>
            <a href="#">Configuración</a>

            <form method="POST" action="{{ route('negocios.logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Portal Negocios ZIGO</div>
        <div class="subtitle">Consola empresarial para operación logística, usuarios, reportes y facturación.</div>

        <div class="grid">
            <div class="card"><div class="label">Guías del mes</div><div class="value">0</div></div>
            <div class="card"><div class="label">Saldo disponible</div><div class="value">$0</div></div>
            <div class="card"><div class="label">Usuarios</div><div class="value">0</div></div>
            <div class="card"><div class="label">Adeudos</div><div class="value">0</div></div>
        </div>

        <div class="section">
            <h2>Funciones empresariales</h2>
            <p><strong>Envíos:</strong> creación individual y masiva de guías.</p>
            <p><strong>Usuarios:</strong> administración de operadores de la empresa.</p>
            <p><strong>Reportes:</strong> consulta de consumo, pagos, entregas y adeudos.</p>
            <p><strong>API:</strong> acceso a integraciones para ERP, ecommerce o sistemas internos.</p>
        </div>
    </main>
</div>

</body>
</html>