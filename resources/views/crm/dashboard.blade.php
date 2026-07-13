<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}
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
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Panel General ZIGO</div>
        <div class="subtitle">Centro de control para operación, clientes, empresas, soporte, APIs y pagos.</div>

        @php
            $usuariosB2c = \App\Models\User::count();
            $empresas = \App\Models\CrmClient::whereIn('client_type', ['b2b', 'mixto'])->count();
            $incidencias = \Illuminate\Support\Facades\Schema::hasTable('b2c_incidencias')
                ? \Illuminate\Support\Facades\DB::table('b2c_incidencias')->count()
                : 0;
            $adeudos = 0;
        @endphp

        <div class="grid">
            <div class="card">
                <div class="label">Usuarios B2C</div>
                <div class="value">{{ $usuariosB2c }}</div>
            </div>

            <div class="card">
                <div class="label">Empresas</div>
                <div class="value">{{ $empresas }}</div>
            </div>

            <div class="card">
                <div class="label">Incidencias</div>
                <div class="value">{{ $incidencias }}</div>
            </div>

            <div class="card">
                <div class="label">Adeudos</div>
                <div class="value">{{ $adeudos }}</div>
            </div>
        </div>

        <div class="section">
            <h2>Consolas activas</h2>
            <p><strong>B2C:</strong> Cliente final</p>
            <p><strong>Negocios:</strong> Empresas B2B</p>
            <p><strong>Soporte:</strong> Mesa de ayuda</p>
            <p><strong>CRM:</strong> Administración general</p>
            <p><strong>API Hub:</strong> Integradores y APIs</p>
        </div>
    </main>
</div>

</body>
</html>