<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis incidencias - ZIGO</title>

    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px}
        .zigo-logo{text-align:center;padding:8px}
        .zigo-img{width:180px;max-width:100%;display:block;margin:0 auto;animation:zigoEntrance 1s ease-out;transition:all .35s ease}
        .zigo-logo:hover .zigo-img{transform:scale(1.03);filter:drop-shadow(0 0 8px rgba(0,255,255,.45)) drop-shadow(0 0 14px rgba(0,128,255,.35))}
        .zigo-tagline{margin-top:8px;font-size:10px;letter-spacing:2px;color:#dce7f7;text-transform:uppercase;font-weight:600;line-height:1.5}
        @keyframes zigoEntrance{from{opacity:0;transform:translateX(-35px)}to{opacity:1;transform:translateX(0)}}

        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}

        .content{padding:40px}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b}
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:13px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900}
        .btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:900;font-size:13px;margin:2px;border:none;cursor:pointer}
        .primary{background:#2563eb;color:white}
        .info{background:#0891b2;color:white}
        .warning{background:#f97316;color:white}
        .empty{background:#eff6ff;color:#1e40af;padding:18px;border-radius:12px;font-weight:800;text-align:center}
        .success-msg{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px}

        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);align-items:center;justify-content:center;z-index:999}
        .modal-card{background:white;border-radius:18px;width:620px;max-width:92%;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
        .modal-header h2{margin:0;font-size:26px}
        .close{border:none;background:transparent;font-size:28px;cursor:pointer}
        label{display:block;font-weight:900;margin:14px 0 6px}
        input,select,textarea{width:100%;box-sizing:border-box;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font-family:Arial,sans-serif;font-size:14px}
        textarea{min-height:120px;resize:vertical}
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo zigo-logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">
            <div class="zigo-tagline">Tecnología • Logística • Conexión</div>
        </div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a href="#">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="header">
            <div>
                <div class="title">Mis incidencias</div>
                <div class="subtitle">Reporta problemas con guías, pagos, entregas o tu cuenta.</div>
            </div>

            <button class="btn primary" type="button" onclick="openModal()">
                + Nuevo reporte
            </button>
        </div>

        @if(session('success'))
            <div class="success-msg">
                {{ session('success') }}
            </div>
        @endif

        <div class="card">
            @if($incidencias->isEmpty())
                <div class="empty">
                    No has reportado ninguna incidencia aún.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Guía</th>
                            <th>Tipo / Motivo</th>
                            <th>Fecha</th>
                            <th>Estatus</th>
                            <th>Respuesta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($incidencias as $incidencia)
                            <tr>
                                <td>{{ $incidencia->folio }}</td>
                                <td>{{ $incidencia->tracking_number ?? '-' }}</td>
                                <td>
                                    <strong>{{ $incidencia->tipo }}</strong><br>
                                    {{ $incidencia->asunto }}
                                </td>
                                <td>{{ $incidencia->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $incidencia->estatus }}</td>
                                <td>{{ $incidencia->respuesta_admin ?? 'Pendiente de revisión' }}</td>
                                <td>
                                    @if($incidencia->evidencia)
                                        <a href="{{ asset('storage/' . $incidencia->evidencia) }}" target="_blank" class="btn info">
                                            Ver evidencia
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</div>

<div id="modalIncidencia" class="modal">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Nuevo reporte</h2>
            <button type="button" class="close" onclick="closeModal()">×</button>
        </div>

        <form method="POST" action="{{ route('b2c.incidencias.guardar') }}" enctype="multipart/form-data">
            @csrf

            <label>Tipo de incidencia</label>
            <select name="tipo" required>
                <option value="">Selecciona una opción</option>
                <option value="Problema con guía">Problema con guía</option>
                <option value="Rastreo incorrecto">Rastreo incorrecto</option>
                <option value="Entrega retrasada">Entrega retrasada</option>
                <option value="Paquete dañado">Paquete dañado</option>
                <option value="Facturación">Facturación</option>
                <option value="Pago">Pago</option>
                <option value="Mi cuenta">Mi cuenta</option>
                <option value="Otro">Otro</option>
            </select>

            <label>Tracking o guía relacionada</label>
            <input name="tracking_number" placeholder="Opcional">

            <label>Asunto</label>
            <input name="asunto" required>

            <label>Descripción</label>
            <textarea name="descripcion" required></textarea>

            <label>Evidencia opcional</label>
            <input type="file" name="evidencia">

            <button type="submit" class="btn warning" style="margin-top:18px">
                Enviar reporte
            </button>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalIncidencia').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalIncidencia').style.display = 'none';
    }
</script>

</body>
</html>