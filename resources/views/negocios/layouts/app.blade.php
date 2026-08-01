<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Negocios ZIGO')</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.layout{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:100vh}.sidebar{background:#0f766e;color:#fff;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}.menu a,.logout-btn{display:block;color:#fff;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.12);padding:13px;border-radius:12px}.menu a.active{background:#facc15;color:#111827}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}.content{padding:40px;min-width:0}.title{font-size:38px;font-weight:900;margin:0 0 8px}.subtitle{color:#64748b;margin-bottom:28px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:24px}.card,.section{background:#fff;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}.label{color:#64748b;font-weight:800;font-size:14px}.value{font-size:34px;font-weight:900;margin-top:8px}.filters{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:14px;align-items:end}.field label{display:block;font-size:13px;font-weight:800;margin-bottom:6px;color:#475569}.field input,.field select{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 11px;background:#fff}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;border:0;border-radius:10px;padding:11px 16px;background:#0f766e;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn-secondary{background:#e2e8f0;color:#111827}.btn-small{padding:8px 11px;font-size:13px}.table-wrap{overflow-x:auto;margin-top:20px}.table{width:100%;border-collapse:collapse;white-space:nowrap}.table th,.table td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:middle}.table th{font-size:12px;text-transform:uppercase;color:#64748b}.empty{text-align:center;color:#64748b;padding:36px}.errors{background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:18px}.pagination{margin-top:20px}.detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.detail-item{padding:14px;background:#f8fafc;border-radius:12px}.detail-item .label{margin-bottom:6px}.address-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:20px}.muted{color:#64748b}.top-actions{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
        @media(max-width:1100px){.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.grid,.detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.layout{display:block}.sidebar{padding:20px}.content{padding:24px 16px}.grid,.filters,.detail-grid,.address-grid{grid-template-columns:1fr}.title{font-size:30px}}
    </style>
</head>
<body>
<div class="layout">
    @include('negocios.partials.sidebar')
    <main class="content">@yield('content')</main>
</div>
</body>
</html>
