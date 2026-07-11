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
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:20px}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f8fafc;font-weight:900}
        .btn{display:inline-block;background:#2563eb;color:white;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:900}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo">CRM ZIGO</div>

        <div class="menu">
            <a href="{{ route('crm.dashboard') }}">Dashboard</a>
            <a href="{{ route('crm.seguridad.index') }}">Seguridad</a>
            <a href="{{ route('crm.seguridad.usuarios') }}">Usuarios</a>
            <a href="{{ route('crm.seguridad.roles') }}">Roles</a>
            <a href="{{ route('crm.seguridad.permisos') }}">Permisos</a>
            <a href="#">Empresas</a>
            <a href="#">Prospectos</a>
            <a href="#">Guías</a>
            <a href="#">Incidencias</a>
            <a href="#">Adeudos</a>
            <a href="#">Pagos</a>
            <a href="#">API Hub</a>

            <form method="POST" action="{{ route('crm.logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        @yield('content')
    </main>
</div>

</body>
</html>