<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de guía - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}.menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}.menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}.content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}.label{color:#64748b;font-weight:800;font-size:13px}.value{font-size:23px;font-weight:900;margin-top:7px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}label{font-weight:900;display:block;margin-bottom:6px}input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}textarea{min-height:90px}.btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}.btn-gray{background:#334155}.alert-ok{background:#dcfce7;color:#166534;padding:13px;border-radius:10px;margin-bottom:16px;font-weight:800}.alert-error{background:#fee2e2;color:#991b1b;padding:13px;border-radius:10px;margin-bottom:16px;font-weight:800}.hint{font-size:13px;color:#64748b;margin-top:6px}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}th{background:#f8fafc}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <a class="btn btn-gray" href="{{ route('crm.guias.index') }}">← Volver a Guías</a>
        <div class="title" style="margin-top:20px">Guía {{ $cotizacion->tracking_number ?: '#' . $cotizacion->guia_id }}</div>
        <div class="subtitle">Cotización B2C #{{ $cotizacion->id }} · Usuario {{ $cotizacion->user->email }}</div>

        @if(session('success'))<div class="alert-ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert-error">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="grid">
            <div class="card"><div class="label">Usuario</div><div class="value">{{ $cotizacion->user->name }}</div><div class="hint">{{ $cotizacion->user->email }}</div></div>
            <div class="card"><div class="label">Precio base proveedor</div><div class="value">${{ number_format((float) $suggestedQuotedCost, 2) }}</div><div class="hint">Costo original reportado por Xperta.</div></div>
            <div class="card"><div class="label">Precio pagado por cliente</div><div class="value">${{ number_format((float) $cotizacion->precio, 2) }}</div><div class="hint">Incluye pricing ZIGO y protección cuando aplique.</div></div>
            <div class="card"><div class="label">Peso cotizado</div><div class="value">{{ number_format((float) $suggestedQuotedWeight, 2) }} kg</div><div class="hint">Peso facturable utilizado inicialmente.</div></div>
        </section>

        <section class="panel">
            <h2>Registrar adeudo reportado por Xperta</h2>
            <p class="hint">
                El adeudo se calcula sobre la diferencia entre el costo original de Xperta y el costo final reportado. No modifica el precio ni la utilidad de la guía original.
            </p>

            <form method="POST" action="{{ route('crm.guias.adeudos.store', $cotizacion) }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label>Tipo</label>
                        <select name="tipo" required>
                            <option value="REPESAJE" @selected(old('tipo') === 'REPESAJE')>Repesaje / sobrepeso</option>
                            <option value="AJUSTE_OPERATIVO" @selected(old('tipo') === 'AJUSTE_OPERATIVO')>Ajuste operativo</option>
                        </select>
                    </div>
                    <div>
                        <label>Referencia del Excel de Xperta</label>
                        <input name="referencia_xperta" value="{{ old('referencia_xperta') }}" maxlength="150" required>
                    </div>
                    <div>
                        <label>Peso cotizado (kg)</label>
                        <input type="number" name="peso_cotizado" min="0" step="0.01" value="{{ old('peso_cotizado', $suggestedQuotedWeight) }}">
                    </div>
                    <div>
                        <label>Peso real reportado (kg)</label>
                        <input type="number" name="peso_real" min="0" step="0.01" value="{{ old('peso_real', $suggestedRealWeight) }}">
                    </div>
                    <div>
                        <label>Costo original Xperta</label>
                        <input type="number" name="costo_cotizado" min="0" step="0.01" value="{{ old('costo_cotizado', $suggestedQuotedCost) }}" required>
                    </div>
                    <div>
                        <label>Costo final Xperta</label>
                        <input type="number" name="costo_real" min="0.01" step="0.01" value="{{ old('costo_real') }}" required>
                    </div>
                    <div style="grid-column:1/-1">
                        <label>Concepto visible para el usuario</label>
                        <input name="concepto" value="{{ old('concepto', 'Ajuste por peso real de la guía') }}" maxlength="255" required>
                    </div>
                    <div style="grid-column:1/-1">
                        <label>Observaciones internas</label>
                        <textarea name="observaciones" maxlength="2000">{{ old('observaciones') }}</textarea>
                    </div>
                </div>
                <button class="btn" type="submit" style="margin-top:16px">Registrar adeudo al usuario</button>
            </form>
        </section>

        <section class="panel">
            <h2>Adeudos relacionados</h2>
            <table>
                <thead><tr><th>ID</th><th>Referencia Xperta</th><th>Concepto</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @forelse($cotizacion->adeudos as $adeudo)
                        <tr>
                            <td><a href="{{ route('crm.adeudos.show', $adeudo) }}">#{{ $adeudo->id }}</a></td>
                            <td>{{ $adeudo->referencia_xperta }}</td>
                            <td>{{ $adeudo->concepto }}</td>
                            <td>${{ number_format((float) $adeudo->monto, 2) }}</td>
                            <td>{{ $adeudo->estatus_label }}</td>
                            <td>{{ $adeudo->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">La guía todavía no tiene adeudos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>
