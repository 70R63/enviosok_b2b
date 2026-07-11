<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Soporte ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#1e40af;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:16px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:6px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .stat{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .stat-label{color:#64748b;font-weight:800;font-size:14px}
        .stat-value{font-size:34px;font-weight:900;margin-top:8px}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:13px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900}
        .btn{background:#2563eb;color:white;text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:900;font-size:12px}
        .empty{background:#eff6ff;color:#1e40af;padding:16px;border-radius:12px;font-weight:800}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo">ZIGO Soporte</div>

        <div class="menu">
            <a href="{{ route('soporte.dashboard') }}">Dashboard</a>
            <a href="{{ route('soporte.incidencias.index') }}">Incidencias</a>
            <a href="#">Guías</a>
            <a href="#">Clientes</a>
            <a href="#">Rastreos</a>
            <a href="#">Pagos</a>
            <a href="#">Reportes</a>

            <form method="POST" action="{{ route('soporte.logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Portal Soporte ZIGO</div>
        <div class="subtitle">Mesa de ayuda para seguimiento de incidencias, guías y clientes.</div>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">Total incidencias</div>
                <div class="stat-value">{{ $total ?? 0 }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">Abiertas</div>
                <div class="stat-value">{{ $abiertas ?? 0 }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">En proceso</div>
                <div class="stat-value">{{ $proceso ?? 0 }}</div>
            </div>

            <div class="stat">
                <div class="stat-label">Cerradas</div>
                <div class="stat-value">{{ $cerradas ?? 0 }}</div>
            </div>
        </div>

        <div class="card">
            <h2>Últimas incidencias</h2>

            @if(($incidencias ?? collect())->isEmpty())
                <div class="empty">No existen incidencias registradas.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Guía</th>
                            <th>Tipo</th>
                            <th>Estatus</th>
                            <th>Prioridad</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($incidencias as $incidencia)
                            <tr>
                                <td>{{ $incidencia->folio }}</td>
                                <td>{{ optional($incidencia->user)->name ?? '-' }}</td>
                                <td>{{ $incidencia->tracking_number ?? '-' }}</td>
                                <td>{{ $incidencia->tipo }}</td>
                                <td>{{ $incidencia->estatus }}</td>
                                <td>{{ $incidencia->prioridad ?? '-' }}</td>
                                <td>
                                    <a class="btn" href="{{ route('soporte.incidencias.show', $incidencia->id) }}">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2>Actividad reciente</h2>
            <div class="empty">
                Próximo paso: registrar comentarios, respuestas, cambios de estatus y adjuntos.
            </div>
        </div>
    </main>
</div>

</body>
</html>