<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>API Hub ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#4338ca;color:white;padding:30px}
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
        <div class="logo">API Hub ZIGO</div>

        <div class="menu">
            <a href="{{ route('hub.dashboard') }}">Dashboard</a>
            <a href="#">Mis APIs</a>
            <a href="#">Credenciales</a>
            <a href="#">Documentación</a>
            <a href="#">Consumo</a>
            <a href="#">Webhooks</a>
            <a href="#">Planes</a>
            <a href="#">Facturación</a>
            <a href="#">Soporte técnico</a>

            <form method="POST" action="{{ route('hub.logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">API Hub ZIGO</div>
        <div class="subtitle">Consola para administrar APIs, credenciales, consumo y documentación técnica.</div>

        <div class="grid">
            <div class="card"><div class="label">APIs activas</div><div class="value">0</div></div>
            <div class="card"><div class="label">Keys generadas</div><div class="value">0</div></div>
            <div class="card"><div class="label">Consumo mensual</div><div class="value">0</div></div>
            <div class="card"><div class="label">Webhooks</div><div class="value">0</div></div>
        </div>

        <div class="section">
            <h2>APIs disponibles</h2>
            <p><strong>Cotización:</strong> cotizar envíos por CP, peso y dimensiones.</p>
            <p><strong>Guías:</strong> generación de guías con paqueterías integradas.</p>
            <p><strong>Rastreo:</strong> consulta premium de eventos de tracking.</p>
            <p><strong>CP / Colonias:</strong> consulta de cobertura geográfica.</p>
            <p><strong>Incidencias:</strong> creación y seguimiento de tickets.</p>
        </div>
    </main>
</div>

</body>
</html>