<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarifas V2 - CRM ZIGO</title>
    <style>
        :root{
            --primary:#4361ee;
            --primary-dark:#3347c8;
            --success:#16a34a;
            --danger:#dc2626;
            --warning:#d97706;
            --muted:#64748b;
            --border:#e2e8f0;
            --panel:#ffffff;
            --background:#f4f7fb;
            --text:#111827;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,sans-serif;background:var(--background);color:var(--text)}
        .layout{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:var(--primary)}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:28px;min-width:0}
        .title{font-size:34px;font-weight:900;margin-bottom:6px}
        .subtitle{color:var(--muted);margin-bottom:16px;max-width:1000px;line-height:1.45}
        .tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;position:sticky;top:0;z-index:20;background:var(--background);padding:8px 0 10px}
        .tab{display:inline-block;padding:9px 14px;border-radius:999px;background:#e2e8f0;color:#334155;text-decoration:none;font-weight:900}
        .tab.active{background:var(--primary);color:#fff}
        .card{background:var(--panel);border-radius:16px;padding:18px;box-shadow:0 8px 20px rgba(15,23,42,.06);margin-bottom:16px;overflow:hidden}
        .card h2,.card h3{margin-top:0}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .form-grid-5{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
        label{display:block;font-size:13px;font-weight:900;margin-bottom:6px;color:#334155}
        input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:9px;font-size:14px;background:#fff}
        textarea{min-height:84px;resize:vertical}
        table{width:100%;border-collapse:collapse}
        th,td{padding:11px;border-bottom:1px solid var(--border);text-align:left;font-size:13px;vertical-align:top}
        th{background:#f8fafc;font-weight:900;white-space:nowrap}
        .table-wrap{overflow:auto}
        .btn{display:inline-block;background:var(--primary);color:white;text-decoration:none;font-weight:900;padding:9px 13px;border-radius:9px;border:none;cursor:pointer}
        .btn:hover{background:var(--primary-dark)}
        .btn-sm{padding:7px 10px;font-size:12px}
        .btn-red{background:var(--danger)}
        .btn-green{background:var(--success)}
        .btn-gray{background:#475569}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900;white-space:nowrap}
        .on{background:#dcfce7;color:#166534}
        .off{background:#fee2e2;color:#991b1b}
        .draft{background:#fef3c7;color:#92400e}
        .dynamic{background:#dbeafe;color:#1d4ed8}
        .manual{background:#ede9fe;color:#6d28d9}
        .success,.error,.validation{padding:13px 15px;border-radius:11px;margin-bottom:16px;font-weight:800}
        .success{background:#dcfce7;color:#166534}
        .error,.validation{background:#fee2e2;color:#991b1b}
        .validation ul{margin:8px 0 0 20px}
        .muted{color:var(--muted)}
        .note{background:#eff6ff;border:1px solid #bfdbfe;padding:14px;border-radius:12px;line-height:1.45}
        .warning{background:#fff7ed;border:1px solid #fed7aa;padding:14px;border-radius:12px;color:#9a3412}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .stat{background:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 6px 16px rgba(15,23,42,.05)}
        .stat strong{display:block;font-size:24px;margin-top:4px}
        .inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .section-title{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:14px}
        .section-title h2,.section-title h3{margin:0}
        details{border:1px solid var(--border);border-radius:12px;padding:12px;margin-top:12px;background:#fafafa}
        summary{cursor:pointer;font-weight:900}
        .rate-line{border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:10px;background:#fff}
        .small{font-size:12px}
        .nowrap{white-space:nowrap}

        .compact-panel{padding:0;border:none;background:#fff}
        .compact-panel>summary{list-style:none;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 18px;cursor:pointer}
        .compact-panel>summary::-webkit-details-marker{display:none}
        .compact-panel>summary::after{content:'+';font-size:24px;font-weight:900;color:var(--primary);line-height:1}
        .compact-panel[open]>summary::after{content:'−'}
        .compact-panel[open]>summary{border-bottom:1px solid var(--border)}
        .compact-panel>.panel-body{padding:16px 18px 18px}
        .summary-main{min-width:0}
        .summary-title{font-size:18px;font-weight:900;line-height:1.25}
        .summary-subtitle{color:var(--muted);font-size:13px;margin-top:4px;white-space:normal}
        .summary-badges{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap;margin-left:auto}
        .summary-meta{display:flex;gap:18px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin-top:7px}
        .compact-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:16px}
        .compact-actions .compact-panel{margin-bottom:0}
        .table-panel>.panel-body{padding-top:8px}
        .rate-card-panel>summary{align-items:flex-start}
        .rate-card-panel .note{margin-top:0}
        .panel-heading-count{font-size:12px;color:var(--muted);font-weight:800;margin-top:3px}
        @media(max-width:1200px){
            .compact-actions{grid-template-columns:1fr}
        }
        @media(max-width:1200px){
            .form-grid,.form-grid-5,.grid-3,.stats{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
        @media(max-width:900px){
            .layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .content{padding:22px}
            .grid,.grid-3,.form-grid,.form-grid-5,.stats{grid-template-columns:1fr}
            .title{font-size:30px}
        }
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Tarifas ZIGO V2</div>
        <div class="subtitle">
            Administración por fuente, convenio, LTD, servicio y versión tarifaria.
            Las ganancias ZIGO permanecen separadas del costo proveedor.
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="validation">
                Revisa los datos capturados:
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <nav class="tabs">
            <a class="tab {{ $activeTab === 'provider-rates' ? 'active' : '' }}"
               href="{{ route('crm.pricing.index', ['tab' => 'provider-rates']) }}">
                Tarifarios proveedor
            </a>

            <a class="tab {{ $activeTab === 'sources' ? 'active' : '' }}"
               href="{{ route('crm.pricing.index', ['tab' => 'sources']) }}">
                Fuentes y convenios
            </a>

            <a class="tab {{ $activeTab === 'profits' ? 'active' : '' }}"
               href="{{ route('crm.pricing.index', ['tab' => 'profits']) }}">
                Ganancias ZIGO
            </a>

            <a class="tab {{ $activeTab === 'simulator' ? 'active' : '' }}"
               href="{{ route('crm.pricing.index', ['tab' => 'simulator']) }}">
                Simulador
            </a>
        </nav>

        @include('crm.pricing.tabs.' . $activeTab)
    </main>
</div>
</body>
</html>
