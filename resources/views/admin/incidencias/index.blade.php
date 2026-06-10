<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administración de Incidencias</title>

<style>

body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#f4f7fb;
    color:#111827;
}

.container{
    width:95%;
    margin:30px auto;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.title{
    font-size:34px;
    font-weight:900;
}

.subtitle{
    color:#64748b;
}

.card{
    background:white;
    border-radius:18px;
    padding:24px;
    box-shadow:0 10px 24px rgba(0,0,0,.08);
}

.filters{
    margin-bottom:20px;
}

.filters select{
    padding:10px;
    border-radius:10px;
    border:1px solid #d1d5db;
}

.btn{
    background:#2563eb;
    color:white;
    border:none;
    border-radius:10px;
    padding:10px 15px;
    text-decoration:none;
    font-weight:800;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#f8fafc;
    font-weight:900;
}

th,td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
}

.badge{
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:900;
}

.abierta{
    background:#fee2e2;
    color:#991b1b;
}

.revision{
    background:#fef3c7;
    color:#92400e;
}

.atendida{
    background:#dbeafe;
    color:#1d4ed8;
}

.cerrada{
    background:#dcfce7;
    color:#166534;
}

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
        <a href="{{ route('admin.incidencias.index') }}">Incidencias</a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-admin">Cerrar sesión</button>
        </form>
    </div>
</div>

<div class="container">

    <div class="header">
        <div>
            <div class="title">
                Incidencias B2C
            </div>

            <div class="subtitle">
                Administración de reportes de clientes
            </div>
        </div>
    </div>

    <div class="card">

       <form method="GET" style="display:flex;gap:12px;margin-bottom:24px;">
            <input 
                type="text" 
                name="q" 
                value="{{ $q ?? '' }}" 
                placeholder="Buscar folio, tracking, usuario, correo, pago o cotización..."
                style="flex:1;padding:12px;border:1px solid #cbd5e1;border-radius:10px;"
            >

            <select name="estatus" style="padding:12px;border:1px solid #cbd5e1;border-radius:10px;">
                <option value="">Todas</option>
                <option value="ABIERTA" {{ ($estatus ?? '') === 'ABIERTA' ? 'selected' : '' }}>ABIERTA</option>
                <option value="EN_REVISION" {{ ($estatus ?? '') === 'EN_REVISION' ? 'selected' : '' }}>EN_REVISION</option>
                <option value="ATENDIDA" {{ ($estatus ?? '') === 'ATENDIDA' ? 'selected' : '' }}>ATENDIDA</option>
                <option value="CERRADA" {{ ($estatus ?? '') === 'CERRADA' ? 'selected' : '' }}>CERRADA</option>
            </select>

            <button class="btn" type="submit">Buscar</button>

            <a href="{{ route('admin.incidencias.index') }}" class="btn" style="background:#64748b;">
                Limpiar
            </a>
        </form>

        @if(isset($cotizaciones) && $cotizaciones->isNotEmpty())
            <div style="margin-bottom:25px;">
                <h3>Resultados relacionados con guías / pagos</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Cotización</th>
                            <th>Usuario</th>
                            <th>Tracking</th>
                            <th>Pago</th>
                            <th>Estado pago</th>
                            <th>Estado guía</th>
                            <th>Paquetería</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cotizaciones as $cotizacion)
                            <tr>
                                <td>#{{ $cotizacion->id }}</td>
                                <td>
                                    {{ $cotizacion->user->name ?? 'Invitado' }}<br>
                                    <small>{{ $cotizacion->user->email ?? '-' }}</small>
                                </td>
                                <td>{{ $cotizacion->tracking_number ?? '-' }}</td>
                                <td>{{ $cotizacion->payment_id ?? '-' }}</td>
                                <td>{{ $cotizacion->payment_status ?? $cotizacion->estatus ?? '-' }}</td>
                                <td>{{ $cotizacion->guia_estatus ?? 'SIN_GUIA' }}</td>
                                <td>{{ $cotizacion->logistico ?? '-' }}</td>
                                <td>${{ number_format($cotizacion->precio ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3>Incidencias</h3>

        <table>

            <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Guía</th>
                <th>Tipo</th>
                <th>Fecha</th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
            </thead>

            <tbody>

            @forelse($incidencias as $incidencia)

                <tr>

                    <td>
                        #{{ $incidencia->id }}
                    </td>

                    <td>
                        {{ $incidencia->user->name ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $incidencia->tracking_number ?? '-' }}
                    </td>

                    <td>
                        {{ $incidencia->tipo }}
                    </td>

                    <td>
                        {{ $incidencia->created_at }}
                    </td>

                    <td>

                        @if($incidencia->estatus=='ABIERTA')
                            <span class="badge abierta">
                                ABIERTA
                            </span>
                        @endif

                        @if($incidencia->estatus=='EN_REVISION')
                            <span class="badge revision">
                                EN REVISION
                            </span>
                        @endif

                        @if($incidencia->estatus=='ATENDIDA')
                            <span class="badge atendida">
                                ATENDIDA
                            </span>
                        @endif

                        @if($incidencia->estatus=='CERRADA')
                            <span class="badge cerrada">
                                CERRADA
                            </span>
                        @endif

                    </td>

                    <td>

                        <a
                            href="{{ route('admin.incidencias.show',$incidencia->id) }}"
                            class="btn">

                            Ver detalle

                        </a>

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="7">
                        No existen incidencias registradas.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

</body>
</html>