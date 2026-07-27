<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Conciliación de saldo - ZIGO</title>

<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
.container{width:92%;max-width:1100px;margin:30px auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
.title{font-size:32px;font-weight:900}
.subtitle{color:#64748b}
.card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:20px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.label{font-size:13px;color:#64748b;font-weight:800;margin-bottom:6px}
.value{font-size:17px;font-weight:900}
.btn{display:inline-block;background:#2563eb;color:white;border:none;border-radius:10px;padding:12px 18px;text-decoration:none;font-weight:900;cursor:pointer}
.btn-danger{background:#dc2626}
.btn-secondary{background:#64748b}
.alert{padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px}
.alert-success{background:#dcfce7;color:#166534}
.alert-error{background:#fee2e2;color:#991b1b}
.alert-warning{background:#fef3c7;color:#92400e}
textarea{width:100%;box-sizing:border-box;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font-family:Arial;font-size:14px;min-height:130px}
table{width:100%;border-collapse:collapse}
th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f8fafc}
.checkbox{display:flex;gap:10px;align-items:flex-start;margin:18px 0;font-weight:800}
.checkbox input{margin-top:3px}
.topbar{background:white;padding:16px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.brand{font-weight:900;font-size:20px}
.user-actions{display:flex;align-items:center;gap:14px}
.user-actions a{text-decoration:none;color:#2563eb;font-weight:800}
.logout-admin{background:#ef4444;color:white;border:none;padding:10px 14px;border-radius:10px;font-weight:900;cursor:pointer}
</style>
</head>

<body>

<div class="topbar">
    <div class="brand">ZIGO Admin</div>

    <div class="user-actions">
        <a href="{{ route('admin.incidencias.index') }}">
            Incidencias
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-admin">
                Cerrar sesión
            </button>
        </form>
    </div>
</div>

<div class="container">
    <div class="header">
        <div>
            <div class="title">
                Conciliación de saldo #{{ $cotizacion->id }}
            </div>
            <div class="subtitle">
                Revisión manual antes de devolver un pago prepago
            </div>
        </div>

        <a
            href="{{ route('admin.incidencias.index') }}"
            class="btn btn-secondary"
        >
            Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="grid">
            <div>
                <div class="label">Usuario</div>
                <div class="value">
                    {{ $cotizacion->user->name ?? 'N/A' }}
                </div>
                <small>
                    {{ $cotizacion->user->email ?? '-' }}
                </small>
            </div>

            <div>
                <div class="label">Saldo actual</div>
                <div class="value">
                    ${{ number_format($preview['balance']->saldo ?? 0, 2) }}
                </div>
            </div>

            <div>
                <div class="label">Estado del envío</div>
                <div class="value">
                    {{ $cotizacion->estatus_label }}
                </div>
            </div>

            <div>
                <div class="label">Estado de guía</div>
                <div class="value">
                    {{ $cotizacion->guia_estatus ?? 'SIN_GUIA' }}
                </div>
            </div>

            <div>
                <div class="label">Pago</div>
                <div class="value">
                    {{ $cotizacion->payment_status_label }}
                </div>
            </div>

            <div>
                <div class="label">Importe</div>
                <div class="value">
                    ${{ number_format($cotizacion->precio ?? 0, 2) }}
                </div>
            </div>

            <div>
                <div class="label">Referencia Estafeta</div>
                <div class="value">
                    {{ $cotizacion->guia_provider_reference ?? '-' }}
                </div>
            </div>

            <div>
                <div class="label">Número de solicitud</div>
                <div class="value">
                    {{ $cotizacion->guia_provider_request_number ?? '-' }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Movimiento de compra</h3>

        @if($preview['purchase_movement'])
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Referencia</th>
                        <th>Monto</th>
                        <th>Saldo anterior</th>
                        <th>Saldo nuevo</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            #{{ $preview['purchase_movement']->id }}
                        </td>
                        <td>
                            {{ $preview['purchase_movement']->referencia }}
                        </td>
                        <td>
                            ${{ number_format($preview['purchase_movement']->monto, 2) }}
                        </td>
                        <td>
                            ${{ number_format($preview['purchase_movement']->saldo_anterior, 2) }}
                        </td>
                        <td>
                            ${{ number_format($preview['purchase_movement']->saldo_nuevo, 2) }}
                        </td>
                        <td>
                            {{ $preview['purchase_movement']->estatus }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @else
            <div class="alert alert-warning">
                No se encontró un movimiento COMPRA_GUIA aplicado
                por el importe actual.
            </div>
        @endif
    </div>

    @if($cotizacion->saldoReversals->isNotEmpty())
        <div class="card">
            <h3>Reversos registrados</h3>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Monto</th>
                        <th>Administrador</th>
                        <th>Motivo</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cotizacion->saldoReversals as $reversal)
                        <tr>
                            <td>#{{ $reversal->id }}</td>
                            <td>
                                ${{ number_format($reversal->amount, 2) }}
                            </td>
                            <td>
                                {{ $reversal->adminUser->name ?? 'N/A' }}
                            </td>
                            <td>{{ $reversal->reason }}</td>
                            <td>
                                {{ $reversal->created_at->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card">
        <h3>Validación para reverso</h3>

        @if($preview['eligible'])
            <div class="alert alert-warning">
                Esta operación devuelve saldo al cliente y no
                puede deshacerse desde esta pantalla. Primero
                revisa Estafeta y confirma que no existe guía.
            </div>

            <form
                method="POST"
                action="{{ route('admin.conciliacion.reverse', $cotizacion->id) }}"
                onsubmit="return confirm('¿Confirmas la devolución del saldo?');"
            >
                @csrf

                <label class="label">
                    Motivo de la devolución
                </label>

                <textarea
                    name="reason"
                    required
                    minlength="20"
                    maxlength="1000"
                >{{ old('reason') }}</textarea>

                <label class="checkbox">
                    <input
                        type="checkbox"
                        name="provider_confirmed"
                        value="1"
                        required
                    >
                    <span>
                        Confirmo que revisé Estafeta y no existe
                        una guía ni un tracking para esta cotización.
                    </span>
                </label>

                <button type="submit" class="btn btn-danger">
                    Devolver saldo
                </button>
            </form>
        @else
            <div class="alert alert-error">
                El reverso no está habilitado:
            </div>

            <ul>
                @foreach($preview['reasons'] as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

</body>
</html>
