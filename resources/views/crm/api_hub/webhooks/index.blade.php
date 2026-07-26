<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Webhooks API Hub - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-weight:800;font-size:14px}
        .value{font-size:30px;font-weight:900;margin-top:8px}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#dbeafe;color:#1e40af}
        .badge-orange{background:#ffedd5;color:#9a3412}
        .badge-gray{background:#e2e8f0;color:#334155}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}
        .btn-red{background:#dc2626}
        .btn-gray{background:#334155}
        .btn-orange{background:#c2410c}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        th{background:#f8fafc;font-weight:900}
        input,select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        label{font-weight:900;display:block;margin-bottom:6px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .events{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}
        .event-option{display:flex;align-items:flex-start;gap:8px;padding:10px;background:#f8fafc;border-radius:8px}
        .event-option input{width:auto;margin-top:2px}
        .event-option label{font-weight:700;margin:0}
        .success{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .warning{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;padding:14px;border-radius:10px;margin-bottom:18px;font-weight:800}
        .muted{color:#64748b;font-size:13px}
        .secret{font-family:monospace;font-size:15px;background:white;padding:12px;border-radius:8px;border:1px solid #fed7aa;margin-top:8px;word-break:break-all}
        .endpoint{border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-top:16px}
        .endpoint-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:15px}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
        .inline{display:inline-block}
        code{font-size:12px;word-break:break-all}
        details{margin-top:8px}
        summary{cursor:pointer;font-weight:800;color:#334155}
        .attempt{margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px;font-size:12px}
        .attempt-ok{border-left:4px solid #16a34a}
        .attempt-error{border-left:4px solid #dc2626}
        .response{max-width:320px;white-space:pre-wrap;word-break:break-word}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Webhooks API Hub</div>
        <div class="subtitle">
            Configuración de endpoints, eventos firmados y bandeja outbox para {{ $apiClient->name }}.
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(session('new_webhook_secret'))
            <div class="warning">
                <strong>Secreto webhook generado:</strong>
                <div class="secret">{{ session('new_webhook_secret') }}</div>
                <div style="margin-top:8px">
                    Cópialo ahora. Por seguridad no volverá a mostrarse.
                </div>
            </div>
        @endif

        <p>
            <a class="btn btn-gray" href="{{ route('crm.api-hub.show', $apiClient) }}">
                ← Volver al cliente API
            </a>
            <a class="btn" href="{{ route('crm.api-hub.products', $apiClient) }}">
                Administrar productos
            </a>
        </p>

        <div class="grid">
            <div class="card">
                <div class="label">Cliente API</div>
                <div class="value" style="font-size:22px">{{ $apiClient->name }}</div>
                <div class="muted">{{ $apiClient->company_name ?? '-' }}</div>
            </div>
            <div class="card">
                <div class="label">Producto WEBHOOKS</div>
                <div class="value" style="font-size:22px">
                    {{ $webhooksProductEnabled ? 'Habilitado' : 'Deshabilitado' }}
                </div>
            </div>
            <div class="card">
                <div class="label">Endpoints</div>
                <div class="value">{{ $endpoints->count() }}</div>
            </div>
            <div class="card">
                <div class="label">Entregas en outbox</div>
                <div class="value">{{ $deliveries->count() }}</div>
                <div class="muted">Últimas 50 visibles</div>
            </div>
        </div>

        @unless($webhooksProductEnabled)
            <div class="warning">
                Habilita el producto WEBHOOKS para este cliente antes de registrar endpoints.
            </div>
        @endunless

        <div class="panel" style="margin-bottom:24px">
            <h2>Nuevo endpoint webhook</h2>
            <p class="muted">
                Producción requiere HTTPS. Las entregas se procesan mediante el comando y scheduler de API Hub.
            </p>

            <form method="POST" action="{{ route('crm.api-hub.webhooks.store', $apiClient) }}">
                @csrf

                <div class="form-grid">
                    <div>
                        <label>Nombre</label>
                        <input type="text" name="name" value="{{ old('name', 'Webhook facturación') }}" required>
                    </div>
                    <div>
                        <label>Ambiente</label>
                        <select name="environment" required>
                            <option value="sandbox" @selected(old('environment') === 'sandbox')>sandbox</option>
                            <option value="production" @selected(old('environment') === 'production')>production</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:16px">
                    <label>URL receptora</label>
                    <input type="url" name="url" value="{{ old('url') }}" placeholder="https://cliente.example.com/webhooks/zigo" required>
                </div>

                <div style="margin-top:16px">
                    <label>Eventos habilitados</label>
                    <div class="events">
                        @foreach($supportedEvents as $eventCode => $eventLabel)
                            <div class="event-option">
                                <input
                                    id="new-{{ $loop->index }}"
                                    type="checkbox"
                                    name="events[]"
                                    value="{{ $eventCode }}"
                                    @checked(in_array($eventCode, old('events', array_keys($supportedEvents)), true))
                                >
                                <label for="new-{{ $loop->index }}">
                                    {{ $eventLabel }}<br>
                                    <span class="muted">{{ $eventCode }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="margin-top:18px">
                    <button class="btn" type="submit" @disabled(! $webhooksProductEnabled)>
                        Crear endpoint y secreto
                    </button>
                </div>
            </form>
        </div>

        <div class="panel" style="margin-bottom:24px">
            <h2>Endpoints configurados</h2>

            @forelse($endpoints as $endpoint)
                <div class="endpoint">
                    <div class="endpoint-head">
                        <div>
                            <strong>{{ $endpoint->name }}</strong>
                            <div class="muted">
                                ID {{ $endpoint->id }} · {{ strtoupper($endpoint->environment) }} · secreto {{ $endpoint->secret_prefix }}...
                            </div>
                        </div>
                        <div>
                            @if($endpoint->active)
                                <span class="badge badge-green">Activo</span>
                            @else
                                <span class="badge badge-red">Inactivo</span>
                            @endif
                            <span class="badge badge-blue">
                                {{ $endpoint->pending_deliveries_count }} pendientes
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('crm.api-hub.webhooks.update', [$apiClient, $endpoint]) }}">
                        @csrf

                        <div class="form-grid">
                            <div>
                                <label>Nombre</label>
                                <input type="text" name="name" value="{{ $endpoint->name }}" required>
                            </div>
                            <div>
                                <label>Ambiente</label>
                                <input type="text" value="{{ $endpoint->environment }}" disabled>
                            </div>
                        </div>

                        <div style="margin-top:16px">
                            <label>URL receptora</label>
                            <input type="url" name="url" value="{{ $endpoint->url }}" required>
                        </div>

                        <div style="margin-top:16px">
                            <label>Eventos</label>
                            <div class="events">
                                @foreach($supportedEvents as $eventCode => $eventLabel)
                                    <div class="event-option">
                                        <input
                                            id="endpoint-{{ $endpoint->id }}-{{ $loop->index }}"
                                            type="checkbox"
                                            name="events[]"
                                            value="{{ $eventCode }}"
                                            @checked($endpoint->supportsEvent($eventCode))
                                        >
                                        <label for="endpoint-{{ $endpoint->id }}-{{ $loop->index }}">
                                            {{ $eventLabel }}<br>
                                            <span class="muted">{{ $eventCode }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="actions">
                            <button class="btn" type="submit">Guardar configuración</button>
                        </div>
                    </form>

                    <div class="actions">
                        <form class="inline" method="POST" action="{{ route('crm.api-hub.webhooks.toggle', [$apiClient, $endpoint]) }}">
                            @csrf
                            <button class="btn {{ $endpoint->active ? 'btn-red' : '' }}" type="submit">
                                {{ $endpoint->active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>

                        <form class="inline" method="POST" action="{{ route('crm.api-hub.webhooks.rotate-secret', [$apiClient, $endpoint]) }}" onsubmit="return confirm('¿Confirmas rotar el secreto? El secreto anterior dejará de ser válido.');">
                            @csrf
                            <button class="btn btn-orange" type="submit">Rotar secreto</button>
                        </form>
                    </div>
                </div>
            @empty
                <p>No hay endpoints webhook registrados.</p>
            @endforelse
        </div>

        <div class="panel">
            <h2>Entregas e historial de webhooks</h2>
            <p class="muted">
                Firma HMAC SHA-256 sobre timestamp + punto + cuerpo JSON exacto. Los códigos HTTP 200 a 299 se consideran entregados.
            </p>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Endpoint / evento</th>
                        <th>Factura externa</th>
                        <th>Estado</th>
                        <th>Intentos</th>
                        <th>Última respuesta</th>
                        <th>Próximo intento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->id }}</td>
                            <td>
                                <strong>{{ $delivery->endpoint?->name ?? '-' }}</strong>
                                <div class="muted">{{ $delivery->endpoint?->environment ?? '-' }}</div>
                                <code>{{ $delivery->event }}</code>
                            </td>
                            <td>{{ $delivery->billingRequest?->external_id ?? '-' }}</td>
                            <td>
                                @if($delivery->status === 'DELIVERED')
                                    <span class="badge badge-green">{{ $delivery->status }}</span>
                                @elseif($delivery->status === 'FAILED')
                                    <span class="badge badge-red">{{ $delivery->status }}</span>
                                @elseif($delivery->status === 'RETRY')
                                    <span class="badge badge-orange">{{ $delivery->status }}</span>
                                @else
                                    <span class="badge badge-blue">{{ $delivery->status }}</span>
                                @endif

                                @if($delivery->delivered_at)
                                    <div class="muted">{{ $delivery->delivered_at }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $delivery->attempts }}/{{ $delivery->max_attempts }}
                                @if($delivery->response_time_ms !== null)
                                    <div class="muted">{{ $delivery->response_time_ms }} ms</div>
                                @endif
                            </td>
                            <td class="response">
                                @if($delivery->response_status !== null)
                                    <strong>HTTP {{ $delivery->response_status }}</strong>
                                @else
                                    <span class="muted">Sin respuesta HTTP</span>
                                @endif

                                @if($delivery->error_message)
                                    <div class="muted">{{ $delivery->error_message }}</div>
                                @endif

                                @if($delivery->attemptHistory->isNotEmpty())
                                    <details>
                                        <summary>Ver historial</summary>
                                        @foreach($delivery->attemptHistory as $attempt)
                                            <div class="attempt {{ $attempt->successful ? 'attempt-ok' : 'attempt-error' }}">
                                                <strong>Intento {{ $attempt->attempt_number }}</strong>
                                                · {{ $attempt->started_at }}
                                                @if($attempt->duration_ms !== null)
                                                    · {{ $attempt->duration_ms }} ms
                                                @endif
                                                <br>
                                                @if($attempt->response_status !== null)
                                                    HTTP {{ $attempt->response_status }}
                                                @else
                                                    Sin respuesta HTTP
                                                @endif
                                                @if($attempt->error_message)
                                                    <div>{{ $attempt->error_message }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </details>
                                @endif
                            </td>
                            <td>
                                {{ $delivery->next_attempt_at ?? '-' }}
                            </td>
                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('crm.api-hub.webhooks.deliveries.retry', [$apiClient, $delivery->endpoint, $delivery]) }}"
                                    onsubmit="return confirm('¿Colocar esta entrega nuevamente en la cola? El receptor debe manejar el event_id de forma idempotente.');"
                                >
                                    @csrf
                                    <button class="btn btn-orange" type="submit">
                                        Reenviar
                                    </button>
                                </form>
                                <div class="muted" style="margin-top:8px">
                                    <code>{{ \Illuminate\Support\Str::limit($delivery->signature, 24) }}</code>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">Todavía no se han generado eventos webhook.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
