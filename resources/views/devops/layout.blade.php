<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ZIGO DevOps')</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.devops-header{background:#0f172a;color:#fff;padding:20px 28px}.header-inner{max-width:1440px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:20px}.brand{font-size:27px;font-weight:900}.header-meta{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}.meta-badge{padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:800}.devops-shell{max-width:1440px;margin:auto;padding:30px}.devops-nav{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:25px}.devops-nav a{color:#334155;background:#e2e8f0;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:900}.devops-nav a.active{background:#2563eb;color:#fff}.content{min-width:0}.title{font-size:36px;font-weight:900;margin-bottom:7px}.subtitle{color:#64748b;margin-bottom:25px}.card,.summary-card{background:#fff;border-radius:16px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.07);margin-bottom:20px}.summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.summary-card span{display:block;color:#64748b;font-weight:800}.summary-card strong{display:block;margin-top:8px}.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 14px;border-radius:9px;font-weight:900;border:0;cursor:pointer}.btn-gray{background:#475569}.btn-small{padding:7px 10px;font-size:12px}.alert{padding:13px 15px;border-radius:10px;margin-bottom:17px;font-weight:800}.alert-success{background:#dcfce7;color:#166534}.alert-error{background:#fee2e2;color:#991b1b}.alert-info{background:#dbeafe;color:#1d4ed8}.muted{color:#64748b;font-size:13px;margin-top:5px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:800px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#e2e8f0;font-size:12px;font-weight:800}input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}.pagination-wrap{margin-top:18px}.empty-state{text-align:center;color:#64748b;padding:28px}@media(max-width:760px){.header-inner{align-items:flex-start;flex-direction:column}.header-meta{justify-content:flex-start}.devops-shell{padding:20px}.summary-grid{grid-template-columns:1fr}.title{font-size:30px}}
    </style>
    @stack('styles')
</head>
<body>
<header class="devops-header"><div class="header-inner"><div class="brand">ZIGO DevOps</div><div class="header-meta"><span class="meta-badge">Ambiente app: {{ strtoupper(app()->environment()) }}</span><span class="meta-badge">Permiso: {{ auth()->user()?->hasRol('sysadmin') ? 'Operación completa' : (auth()->user()?->hasRol('soporte') ? 'Consulta y health checks' : 'Solo consulta') }}</span><span class="meta-badge">{{ auth()->user()?->name }}</span><form method="POST" action="{{ url('/logout') }}">@csrf<button type="submit" class="meta-badge" style="border:0;color:#fff;cursor:pointer;">Cerrar sesión</button></form></div></div></header>
<div class="devops-shell">
    @include('crm.devops.partials.nav')
    <main class="content">@yield('content')</main>
</div>
@stack('scripts')
</body>
</html>
