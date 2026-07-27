<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de adeudo - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}.layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.sidebar{background:#111827;color:white;padding:30px}.logo{font-size:26px;font-weight:900;margin-bottom:30px}.menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}.menu a.active{background:#4361ee}.logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}.content{padding:36px}.title{font-size:36px;font-weight:900;margin-bottom:6px}.subtitle{color:#64748b;margin-bottom:24px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08);margin-bottom:22px}.label{color:#64748b;font-weight:800;font-size:13px}.value{font-size:23px;font-weight:900;margin-top:7px}.btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:11px 15px;border-radius:9px;border:none;cursor:pointer}.btn-gray{background:#334155}.btn-red{background:#dc2626}.btn-orange{background:#d97706}.alert-ok{background:#dcfce7;color:#166534;padding:13px;border-radius:10px;margin-bottom:16px;font-weight:800}.alert-error{background:#fee2e2;color:#991b1b;padding:13px;border-radius:10px;margin-bottom:16px;font-weight:800}label{font-weight:900;display:block;margin-bottom:6px}textarea{width:100%;min-height:90px;padding:11px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}.actions{display:grid;grid-template-columns:1fr 1fr;gap:18px}.muted{color:#64748b;font-size:13px}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')
    <main class="content">
        <a class="btn btn-gray" href="{{ route('crm.adeudos.index') }}">← Volver a Adeudos</a>
        <a class="btn btn-gray" href="{{ route('crm.guias.show', $adeudo->cotizacion_id) }}">Abrir guía</a>

        <div class="title" style="margin-top:20px">Adeudo #{{ $adeudo->id }}</div>
        <div class="subtitle">{{ $adeudo->referencia_xperta }} · {{ $adeudo->estatus_label }}</div>

        @if(session('success'))<div class="alert-ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert-error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="grid">
            <div class="card"><div class="label">Usuario</div><div class="value">{{ $adeudo->user->name ?? '-' }}</div><div class="muted">{{ $adeudo->user->email ?? '-' }}</div></div>
            <div class="card"><div class="label">Guía</div><div class="value">{{ $adeudo->tracking_number ?: '-' }}</div><div class="muted">Cotización #{{ $adeudo->cotizacion_id }}</div></div>
            <div class="card"><div class="label">Costo Xperta original</div><div class="value">${{ number_format((float) $adeudo->costo_cotizado, 2) }}</div></div>
            <div class="card"><div class="label">Costo Xperta final</div><div class="value">${{ number_format((float) $adeudo->costo_real, 2) }}</div></div>
        </section>

        <section class="panel">
            <h2>{{ $adeudo->concepto }}</h2>
            <p><strong>Importe:</strong> ${{ number_format((float) $adeudo->monto, 2) }}</p>
            <p><strong>Peso cotizado:</strong> {{ $adeudo->peso_cotizado !== null ? number_format((float) $adeudo->peso_cotizado, 2) . ' kg' : '-' }}</p>
            <p><strong>Peso real:</strong> {{ $adeudo->peso_real !== null ? number_format((float) $adeudo->peso_real, 2) . ' kg' : '-' }}</p>
            <p><strong>Observaciones:</strong> {{ $adeudo->observaciones ?: '-' }}</p>
            <p><strong>Creado por:</strong> {{ $adeudo->creator->name ?? '-' }} · {{ $adeudo->created_at->format('d/m/Y H:i') }}</p>
            @if($adeudo->payment_method)
                <p><strong>Método de pago:</strong> {{ $adeudo->payment_method }} · {{ optional($adeudo->paid_at)->format('d/m/Y H:i') }}</p>
            @endif
            @if($adeudo->cancel_reason)
                <p><strong>Motivo de cierre:</strong> {{ $adeudo->cancel_reason }}</p>
            @endif
        </section>

        @if($adeudo->isPending())
            <section class="actions">
                <form class="panel" method="POST" action="{{ route('crm.adeudos.cancel', $adeudo) }}">
                    @csrf
                    <h2>Cancelar adeudo</h2>
                    <label>Motivo</label>
                    <textarea name="motivo" required minlength="10"></textarea>
                    <button class="btn btn-red" type="submit">Cancelar</button>
                </form>

                <form class="panel" method="POST" action="{{ route('crm.adeudos.waive', $adeudo) }}">
                    @csrf
                    <h2>Condonar adeudo</h2>
                    <label>Motivo</label>
                    <textarea name="motivo" required minlength="10"></textarea>
                    <button class="btn btn-orange" type="submit">Condonar</button>
                </form>
            </section>
        @endif
    </main>
</div>
</body>
</html>
