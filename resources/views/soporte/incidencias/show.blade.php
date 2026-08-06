<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle incidencia - ZIGO</title>

<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
.container{width:90%;margin:30px auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
.title{font-size:34px;font-weight:900}
.subtitle{color:#64748b}
.card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:20px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.label{font-size:13px;color:#64748b;font-weight:800;margin-bottom:6px}
.value{font-size:17px;font-weight:900}
.btn{display:inline-block;background:#2563eb;color:white;border:none;border-radius:10px;padding:12px 18px;text-decoration:none;font-weight:900;cursor:pointer}
.back{background:#64748b}
.success{background:#16a34a}
textarea,select{width:100%;box-sizing:border-box;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font-family:Arial;font-size:14px}
textarea{min-height:140px}
.alert{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px}
.badge{padding:7px 12px;border-radius:999px;font-size:13px;font-weight:900;background:#fef3c7;color:#92400e}

.topbar{
    background:white;
    padding:16px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,.06);
}

.brand{
    font-weight:900;
    font-size:20px;
}

.user-actions{
    display:flex;
    align-items:center;
    gap:14px;
}

.user-actions a{
    text-decoration:none;
    color:#2563eb;
    font-weight:800;
}

.logout-admin{
    background:#ef4444;
    color:white;
    border:none;
    padding:10px 14px;
    border-radius:10px;
    font-weight:900;
    cursor:pointer;
}

</style>
</head>

<body>

<div class="topbar">
    <div class="brand">ZIGO Admin</div>

    <div class="user-actions">
        <a href="{{ route('soporte.incidencias.index') }}">Incidencias</a>

        <form method="POST" action="{{ route('soporte.logout') }}">
            @csrf
            <button type="submit" class="logout-admin">Cerrar sesión</button>
        </form>
    </div>
</div>

<div class="container">

    <div class="header">
        <div>
            <div class="title">Incidencia {{ $incidencia->folio }}</div>
            <div class="subtitle">Detalle y respuesta al usuario</div>
        </div>

        <a href="{{ route('soporte.incidencias.index') }}" class="btn back">
            Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="grid">
            <div>
                <div class="label">Usuario</div>
                <div class="value">{{ $incidencia->user->name ?? 'N/A' }}</div>
            </div>

            <div>
                <div class="label">Correo</div>
                <div class="value">{{ $incidencia->user->email ?? 'N/A' }}</div>
            </div>

            <div>
                <div class="label">Tracking / Guía</div>
                <div class="value">{{ $incidencia->tracking_number ?? '-' }}</div>
            </div>

            <div>
                <div class="label">Estatus</div>
                <div class="value">
                    <span class="badge">{{ $incidencia->estatus }}</span>
                </div>
            </div>

            <div>
                <div class="label">Tipo</div>
                <div class="value">{{ $incidencia->tipo }}</div>
            </div>

            <div>
                <div class="label">Fecha</div>
                <div class="value">{{ $incidencia->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="label">Asunto</div>
        <div class="value">{{ $incidencia->asunto }}</div>

        <br>

        <div class="label">Descripción del usuario</div>
        <p>{{ $incidencia->descripcion }}</p>

        @if($incidencia->evidencia)
            <br>
            <a href="{{ route('soporte.incidencias.evidence', $incidencia) }}" class="btn">
                Ver evidencia
            </a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('soporte.incidencias.follow-up', $incidencia) }}">
            @csrf

            <label class="label">Cambiar estatus</label>
            <select name="estatus" required>
                <option value="ABIERTA" {{ $incidencia->estatus === 'ABIERTA' ? 'selected' : '' }}>ABIERTA</option>
                <option value="EN_REVISION" {{ $incidencia->estatus === 'EN_REVISION' ? 'selected' : '' }}>EN_REVISION</option>
                <option value="ATENDIDA" {{ $incidencia->estatus === 'ATENDIDA' ? 'selected' : '' }}>ATENDIDA</option>
                <option value="CERRADA" {{ $incidencia->estatus === 'CERRADA' ? 'selected' : '' }}>CERRADA</option>
            </select>

            <br><br>

            <label class="label">Prioridad</label>
            <select name="prioridad" required>
                <option value="BAJA" {{ $incidencia->prioridad === 'BAJA' ? 'selected' : '' }}>BAJA</option>
                <option value="MEDIA" {{ $incidencia->prioridad === 'MEDIA' || !$incidencia->prioridad ? 'selected' : '' }}>MEDIA</option>
                <option value="ALTA" {{ $incidencia->prioridad === 'ALTA' ? 'selected' : '' }}>ALTA</option>
                <option value="URGENTE" {{ $incidencia->prioridad === 'URGENTE' ? 'selected' : '' }}>URGENTE</option>
            </select>

            <br><br>

            <label class="label">Respuesta al usuario</label>
            <textarea name="public_response">{{ old('public_response') }}</textarea>
            <label class="label">Nota interna (no visible al cliente)</label>
            <textarea name="internal_note">{{ old('internal_note') }}</textarea>
            <label><input type="checkbox" name="solution" value="1"> Marcar como solución</label>

            <button type="submit" class="btn success" style="margin-top:18px">
                Guardar respuesta
            </button>
        </form>
    </div>

    <div class="card"><form method="POST" action="{{ route('soporte.incidencias.status',$incidencia) }}">@csrf @method('PATCH')<label class="label">Estatus permitido</label><select name="status"><option value="EN_PROCESO">EN_PROCESO</option><option value="RESUELTA">RESUELTA</option></select><button class="btn">Actualizar</button></form></div>
    <div class="card"><h2>Historial</h2>@forelse($incidencia->events as $evento)<p><b>{{ $evento->created_at?->format('d/m/Y H:i') }} · {{ $evento->origin }}</b><br>{{ $evento->event_type }} · {{ $evento->previous_status ?: '—' }} → {{ $evento->new_status ?: '—' }}@if($evento->public_message)<br>Respuesta pública: {{ $evento->public_message }}@endif @if($evento->internal_note)<br><strong>Nota interna:</strong> {{ $evento->internal_note }}@endif</p>@empty — @endforelse</div>
</div>

</body>
</html>
