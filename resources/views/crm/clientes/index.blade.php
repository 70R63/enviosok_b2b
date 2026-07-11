<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes - CRM ZIGO</title>
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
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-red{background:#dc2626}
        .btn-gray{background:#334155}
        .filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;margin-bottom:18px}
        input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px;border-bottom:1px solid #e5e7eb;text-align:left}
        th{background:#f8fafc;font-weight:900}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .actions{display:flex;gap:8px}
        .success{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Clientes</div>
        <div class="subtitle">Administración comercial de clientes B2C, B2B, API y mixtos.</div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <p>
            <a class="btn" href="{{ route('crm.clientes.create') }}">+ Crear cliente</a>
        </p>

        <div class="card">
            <form method="GET" action="{{ route('crm.clientes.index') }}" class="filters">
                <input type="text" name="search" placeholder="Buscar por nombre, empresa, contacto, email o teléfono"
                       value="{{ request('search') }}">

                <select name="client_type">
                    <option value="">Todos los tipos</option>
                    <option value="b2c" @selected(request('client_type') === 'b2c')>B2C</option>
                    <option value="b2b" @selected(request('client_type') === 'b2b')>B2B</option>
                    <option value="api" @selected(request('client_type') === 'api')>API</option>
                    <option value="mixto" @selected(request('client_type') === 'mixto')>Mixto</option>
                </select>

                <select name="commercial_status">
                    <option value="">Todos los estados</option>
                    <option value="prospecto" @selected(request('commercial_status') === 'prospecto')>Prospecto</option>
                    <option value="activo" @selected(request('commercial_status') === 'activo')>Activo</option>
                    <option value="suspendido" @selected(request('commercial_status') === 'suspendido')>Suspendido</option>
                    <option value="perdido" @selected(request('commercial_status') === 'perdido')>Perdido</option>
                </select>

                <button class="btn btn-gray" type="submit">Filtrar</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td>{{ $client->id }}</td>
                            <td>{{ $client->name }}</td>
                            <td><span class="badge">{{ $client->client_type_label }}</span></td>
                            <td>{{ $client->company_name ?? '-' }}</td>
                            <td>{{ $client->contact_name ?? '-' }}</td>
                            <td>{{ $client->email ?? '-' }}</td>
                            <td>{{ $client->commercial_status_label }}</td>
                            <td>
                                @if($client->active)
                                    <span class="badge badge-green">Activo</span>
                                @else
                                    <span class="badge badge-red">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="{{ route('crm.clientes.edit', $client) }}">Editar</a>

                                    <form method="POST" action="{{ route('crm.clientes.destroy', $client) }}"
                                          onsubmit="return confirm('¿Suspender este cliente?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-red" type="submit">Suspender</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No hay clientes registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="margin-top:18px">
                {{ $clients->links() }}
            </div>
        </div>
    </main>
</div>

</body>
</html>