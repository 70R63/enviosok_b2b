<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CRM ZIGO')</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px;min-width:0}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .card,.summary-card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .card{margin-bottom:20px}
        .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
        .summary-card span{display:block;color:#64748b;font-weight:800;font-size:14px}
        .summary-card strong{display:block;font-size:30px;margin-top:8px}
        .btn{display:inline-block;background:#2563eb;color:white;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:900;border:none;cursor:pointer;font-size:14px;text-align:center}
        .btn-gray{background:#475569}
        .btn-small{padding:7px 10px;font-size:12px;white-space:nowrap}
        .table-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .table-actions form{margin:0}
        .filters{display:grid;gap:12px;margin-bottom:18px}
        .filters-invoices{grid-template-columns:repeat(4,minmax(140px,1fr))}
        input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:white}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;min-width:1100px}
        th,td{padding:13px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        th{background:#f8fafc;font-weight:900;white-space:nowrap}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;white-space:nowrap}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#dbeafe;color:#1d4ed8}
        .badge-yellow{background:#fef3c7;color:#92400e}
        .badge-gray{background:#e2e8f0;color:#334155}
        .muted{color:#64748b;font-size:13px;margin-top:4px}
        .empty-state{text-align:center;color:#64748b;padding:32px}
        .pagination-wrap{margin-top:18px}
        .alert{padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .alert-error{background:#fee2e2;color:#991b1b}
        .alert-success{background:#dcfce7;color:#166534}
        .alert-info{background:#dbeafe;color:#1d4ed8}
        @media(max-width:1100px){
            .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .filters-invoices{grid-template-columns:repeat(2,minmax(140px,1fr))}
        }
        @media(max-width:760px){
            .layout{grid-template-columns:1fr}
            .sidebar{padding:20px}
            .content{padding:22px}
            .summary-grid,.filters-invoices{grid-template-columns:1fr}
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
