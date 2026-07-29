<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificaciones de identidad - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}
        .cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}
        .card,.panel{background:white;border-radius:18px;padding:20px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .card span{display:block;color:#64748b;font-weight:800;font-size:12px;margin-bottom:8px}.card strong{font-size:28px}
        .panel{margin-bottom:22px}.filters{display:grid;grid-template-columns:1fr 240px auto;gap:14px;align-items:end}
        label{font-weight:900;display:block;margin-bottom:6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}
        table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc}
        .badge{display:inline-block;padding:5px 9px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .pending{background:#fef3c7;color:#92400e}.approved{background:#dcfce7;color:#166534}.rejected{background:#fee2e2;color:#991b1b}.correction{background:#ffedd5;color:#9a3412}
        .muted{color:#64748b;font-size:13px}
        @media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr}.panel{overflow:auto}}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Verificaciones de identidad</div>
        <div class="subtitle">
            Revisión manual y protegida de documentos de usuarios B2C.
        </div>

        <section class="cards">
            <div class="card">
                <span>Sin verificar</span>
                <strong>{{ (int) ($statusCounts['SIN_VERIFICAR'] ?? 0) }}</strong>
            </div>
            <div class="card">
                <span>Pendientes</span>
                <strong>{{ (int) ($statusCounts['PENDIENTE'] ?? 0) }}</strong>
            </div>
            <div class="card">
                <span>Corrección</span>
                <strong>{{ (int) ($statusCounts['CORRECCION_REQUERIDA'] ?? 0) }}</strong>
            </div>
            <div class="card">
                <span>Aprobadas</span>
                <strong>{{ (int) ($statusCounts['APROBADA'] ?? 0) }}</strong>
            </div>
            <div class="card">
                <span>Rechazadas</span>
                <strong>{{ (int) ($statusCounts['RECHAZADA'] ?? 0) }}</strong>
            </div>
        </section>

        @if(session('success'))
            <section class="panel" style="background:#dcfce7;color:#166534;font-weight:800">
                {{ session('success') }}
            </section>
        @endif

        <section class="panel">
            <form method="GET" action="{{ route('crm.identity.index') }}" class="filters">
                <div>
                    <label>Buscar usuario</label>
                    <input
                        name="search"
                        value="{{ $search }}"
                        placeholder="Nombre, apellidos o correo"
                    >
                </div>
                <div>
                    <label>Estado</label>
                    <select name="status">
                        @foreach([
                            'TODOS' => 'Todos',
                            'SIN_VERIFICAR' => 'Sin verificar',
                            'PENDIENTE' => 'Pendientes',
                            'CORRECCION_REQUERIDA' => 'Corrección requerida',
                            'APROBADA' => 'Aprobadas',
                            'RECHAZADA' => 'Rechazadas',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="btn" type="submit">Consultar</button>
            </form>
        </section>

        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Documentos</th>
                    <th>Guías generadas</th>
                    <th>Enviada</th>
                    <th>Última revisión</th>
                    <th>Acción</th>
                </tr>
                </thead>
                <tbody>
                @forelse($verifications as $verification)
                    @php
                        $statusClass = match($verification->status) {
                            'PENDIENTE' => 'pending',
                            'APROBADA' => 'approved',
                            'RECHAZADA' => 'rejected',
                            'CORRECCION_REQUERIDA' => 'correction',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $verification->user->name ?? 'Usuario eliminado' }}</strong>
                            <div class="muted">{{ $verification->user->email ?? '-' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $statusClass }}">
                                {{ $verification->status }}
                            </span>
                        </td>
                        <td>
                            {{ $verification->hasCompleteDocuments() ? '3 de 3' : 'Incompletos' }}
                        </td>
                        <td>
                            {{ (int) ($guideCounts[$verification->user_id] ?? 0) }}
                        </td>
                        <td>
                            {{ optional($verification->submitted_at)->format('d/m/Y H:i') ?: '-' }}
                        </td>
                        <td>
                            {{ optional($verification->reviewed_at)->format('d/m/Y H:i') ?: '-' }}
                            @if($verification->reviewer)
                                <div class="muted">{{ $verification->reviewer->name }}</div>
                            @endif
                        </td>
                        <td>
                            <a
                                class="btn"
                                href="{{ route('crm.identity.show', $verification) }}"
                            >
                                Revisar expediente
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            No se encontraron verificaciones con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div style="margin-top:18px">
                {{ $verifications->links() }}
            </div>
        </section>
    </main>
</div>
</body>
</html>