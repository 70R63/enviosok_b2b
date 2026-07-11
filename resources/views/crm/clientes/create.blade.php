<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear cliente - CRM ZIGO</title>
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
        .card{background:white;border-radius:18px;padding:24px;box-shadow:0 10px 24px rgba(0,0,0,.08);max-width:900px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        label{font-weight:900;display:block;margin-bottom:6px}
        input,select,textarea{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        textarea{min-height:90px}
        .full{grid-column:1 / -1}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-gray{background:#334155}
        .errors{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Crear cliente</div>
        <div class="subtitle">Alta comercial para clientes B2C, B2B, API o mixtos.</div>

        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('crm.clientes.store') }}">
                @csrf

                <div class="grid">
                    <div>
                        <label>Tipo de cliente</label>
                        <select name="client_type" required>
                            <option value="b2c" @selected(old('client_type') === 'b2c')>B2C</option>
                            <option value="b2b" @selected(old('client_type') === 'b2b')>B2B</option>
                            <option value="api" @selected(old('client_type') === 'api')>API</option>
                            <option value="mixto" @selected(old('client_type') === 'mixto')>Mixto</option>
                        </select>
                    </div>

                    <div>
                        <label>Estado comercial</label>
                        <select name="commercial_status" required>
                            <option value="prospecto" @selected(old('commercial_status') === 'prospecto')>Prospecto</option>
                            <option value="activo" @selected(old('commercial_status') === 'activo')>Activo</option>
                            <option value="suspendido" @selected(old('commercial_status') === 'suspendido')>Suspendido</option>
                            <option value="perdido" @selected(old('commercial_status') === 'perdido')>Perdido</option>
                        </select>
                    </div>

                    <div>
                        <label>Nombre del cliente</label>
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </div>

                    <div>
                        <label>Empresa</label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}">
                    </div>

                    <div>
                        <label>Contacto</label>
                        <input type="text" name="contact_name" value="{{ old('contact_name') }}">
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="{{ old('email') }}">
                    </div>

                    <div>
                        <label>Teléfono</label>
                        <input type="text" name="phone" value="{{ old('phone') }}">
                    </div>

                    <div>
                        <label>RFC / Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id') }}">
                    </div>

                    <div>
                        <label>Origen</label>
                        <input type="text" name="source" value="{{ old('source', 'manual') }}">
                    </div>

                    <div>
                        <label>Activo</label>
                        <select name="active">
                            <option value="1" selected>Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <div class="full">
                        <label>Notas</label>
                        <textarea name="notes">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <p style="margin-top:22px">
                    <button class="btn" type="submit">Guardar cliente</button>
                    <a class="btn btn-gray" href="{{ route('crm.clientes.index') }}">Cancelar</a>
                </p>
            </form>
        </div>
    </main>
</div>

</body>
</html>