<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente de identidad - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}
        .panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}
        .summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.summary div{padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.summary span{display:block;color:#64748b;font-size:12px;font-weight:800;margin-bottom:6px}
        .documents{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.document{border:1px solid #e2e8f0;border-radius:14px;padding:14px}.document img{width:100%;height:310px;object-fit:contain;background:#f8fafc;border-radius:10px}.document h3{margin:0 0 12px}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}.btn-green{background:#16a34a}.btn-orange{background:#ea580c}.btn-red{background:#dc2626}.btn-gray{background:#334155}
        textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:9px;min-height:100px}.actions{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}.action{border:1px solid #e2e8f0;border-radius:14px;padding:16px}.action label{font-weight:800;display:block;margin:8px 0}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:900;font-size:12px;background:#e2e8f0}.pending{background:#fef3c7;color:#92400e}.approved{background:#dcfce7;color:#166534}.rejected{background:#fee2e2;color:#991b1b}.correction{background:#ffedd5;color:#9a3412}
        .success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.message{padding:14px;border-radius:12px;font-weight:800;margin-bottom:18px}
        table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fafc}
        @media(max-width:1100px){.summary,.documents,.actions{grid-template-columns:1fr}.document img{height:auto}}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <a class="btn btn-gray" href="{{ route('crm.identity.index') }}">
            ← Volver a verificaciones
        </a>

        <div class="title" style="margin-top:20px">
            Expediente de identidad #{{ $verification->id }}
        </div>
        <div class="subtitle">
            Revisión manual de documentos privados.
        </div>

        @if(session('success'))
            <div class="message success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="message error">
                <ul style="margin:0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $statusClass = match($verification->status) {
                'PENDIENTE' => 'pending',
                'APROBADA' => 'approved',
                'RECHAZADA' => 'rejected',
                'CORRECCION_REQUERIDA' => 'correction',
                default => '',
            };
        @endphp

        <section class="panel summary">
            <div>
                <span>Usuario</span>
                <strong>{{ $verification->user->name ?? '-' }}</strong>
                <br>{{ $verification->user->email ?? '-' }}
            </div>
            <div>
                <span>Estado</span>
                <strong class="badge {{ $statusClass }}">
                    {{ $verification->status }}
                </strong>
            </div>
            <div>
                <span>Guías generadas</span>
                <strong>{{ $generatedGuides }}</strong>
            </div>
            <div>
                <span>Solicitud enviada</span>
                <strong>
                    {{ optional($verification->submitted_at)->format('d/m/Y H:i') ?: '-' }}
                </strong>
            </div>
        </section>

        <section class="panel">
            <h2>Documentos</h2>
            <p style="color:#64748b">
                Los archivos se entregan mediante rutas privadas del CRM y no deben descargarse ni compartirse fuera del proceso de validación.
            </p>

            <div class="documents">
                @foreach([
                    'ine-front' => ['INE frontal', $verification->ine_front],
                    'ine-back' => ['INE reverso', $verification->ine_back],
                    'selfie' => ['Selfie sosteniendo INE', $verification->selfie_with_ine],
                ] as $document => [$label, $path])
                    <article class="document">
                        <h3>{{ $label }}</h3>

                        @if($path)
                            <a
                                href="{{ route('crm.identity.document', [$verification, $document]) }}"
                                target="_blank"
                                rel="noopener"
                            >
                                <img
                                    src="{{ route('crm.identity.document', [$verification, $document]) }}"
                                    alt="{{ $label }}"
                                >
                            </a>
                        @else
                            <div style="padding:80px 20px;text-align:center;background:#f8fafc">
                                Documento no cargado
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @if($verification->isPendingReview())
            <section class="panel">
                <h2>Decisión</h2>

                <div class="actions">
                    <form
                        class="action"
                        method="POST"
                        action="{{ route('crm.identity.approve', $verification) }}"
                        onsubmit="return confirm('¿Confirmas la aprobación de esta identidad?')"
                    >
                        @csrf
                        <h3>Aprobar identidad</h3>
                        <label>Comentario opcional</label>
                        <textarea name="comments"></textarea>
                        <button class="btn btn-green" type="submit">
                            Aprobar definitivamente
                        </button>
                    </form>

                    <form
                        class="action"
                        method="POST"
                        action="{{ route('crm.identity.correction', $verification) }}"
                    >
                        @csrf
                        <h3>Solicitar corrección</h3>

                        <label>
                            <input type="checkbox" name="documents[]" value="ine_front">
                            INE frontal
                        </label>
                        <label>
                            <input type="checkbox" name="documents[]" value="ine_back">
                            INE reverso
                        </label>
                        <label>
                            <input type="checkbox" name="documents[]" value="selfie_with_ine">
                            Selfie con INE
                        </label>

                        <label>Motivo obligatorio</label>
                        <textarea name="comments" required minlength="10"></textarea>

                        <button class="btn btn-orange" type="submit">
                            Solicitar corrección
                        </button>
                    </form>

                    <form
                        class="action"
                        method="POST"
                        action="{{ route('crm.identity.reject', $verification) }}"
                        onsubmit="return confirm('¿Confirmas el rechazo de esta identidad?')"
                    >
                        @csrf
                        <h3>Rechazar identidad</h3>
                        <label>Motivo obligatorio</label>
                        <textarea name="comments" required minlength="10"></textarea>
                        <button class="btn btn-red" type="submit">
                            Rechazar
                        </button>
                    </form>
                </div>
            </section>
        @else
            <section class="panel">
                <h2>Resultado de revisión</h2>
                <p><strong>Estado:</strong> {{ $verification->status }}</p>
                <p><strong>Comentarios:</strong> {{ $verification->comments ?: 'Sin comentarios' }}</p>
                <p>
                    <strong>Revisado por:</strong>
                    {{ $verification->reviewer->name ?? '-' }}
                    ·
                    {{ optional($verification->reviewed_at)->format('d/m/Y H:i') ?: '-' }}
                </p>
            </section>
        @endif

        <section class="panel">
            <h2>Historial</h2>
            <table>
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Cambio</th>
                    <th>Responsable</th>
                    <th>Comentarios</th>
                </tr>
                </thead>
                <tbody>
                @forelse($verification->events->sortByDesc('created_at') as $event)
                    <tr>
                        <td>{{ $event->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $event->event_type }}</td>
                        <td>
                            {{ $event->from_status ?: '-' }}
                            →
                            {{ $event->to_status }}
                        </td>
                        <td>{{ $event->actor->name ?? 'Usuario B2C' }}</td>
                        <td>{{ $event->comments ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No existen eventos registrados todavía.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>