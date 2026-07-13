<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar cliente - CRM ZIGO</title>
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
        .form-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
        }

        .full{
            grid-column:1/-1;
        }

        .card{
            background:white;
            border-radius:18px;
            padding:24px;
            box-shadow:0 10px 24px rgba(0,0,0,.08);
        }
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-gray{background:#334155}
        .errors{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
    </style>
</head>
<body>

<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Editar cliente</div>
        <div class="subtitle">Actualización de información comercial.</div>

        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            {{-- FORMULARIO 1: DATOS GENERALES --}}
            <form method="POST" action="{{ route('crm.clientes.update', $cliente) }}">
                @csrf
                @method('PUT')

                <div class="grid">
                    <div>
                        <label>Tipo de cliente</label>
                        <select name="client_type" required>
                            <option value="b2c" @selected(old('client_type', $cliente->client_type) === 'b2c')>B2C</option>
                            <option value="b2b" @selected(old('client_type', $cliente->client_type) === 'b2b')>B2B</option>
                            <option value="api" @selected(old('client_type', $cliente->client_type) === 'api')>API</option>
                            <option value="mixto" @selected(old('client_type', $cliente->client_type) === 'mixto')>Mixto</option>
                        </select>
                    </div>

                    <div>
                        <label>Estado comercial</label>
                        <select name="commercial_status" required>
                            <option value="prospecto" @selected(old('commercial_status', $cliente->commercial_status) === 'prospecto')>Prospecto</option>
                            <option value="activo" @selected(old('commercial_status', $cliente->commercial_status) === 'activo')>Activo</option>
                            <option value="suspendido" @selected(old('commercial_status', $cliente->commercial_status) === 'suspendido')>Suspendido</option>
                            <option value="perdido" @selected(old('commercial_status', $cliente->commercial_status) === 'perdido')>Perdido</option>
                        </select>
                    </div>

                    <div>
                        <label>Nombre del cliente</label>
                        <input type="text" name="name" value="{{ old('name', $cliente->name) }}" required>
                    </div>

                    <div>
                        <label>Empresa</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $cliente->company_name) }}">
                    </div>

                    <div>
                        <label>Contacto</label>
                        <input type="text" name="contact_name" value="{{ old('contact_name', $cliente->contact_name) }}">
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="{{ old('email', $cliente->email) }}">
                    </div>

                    <div>
                        <label>Teléfono</label>
                        <input type="text" name="phone" value="{{ old('phone', $cliente->phone) }}">
                    </div>

                    <div>
                        <label>RFC / Tax ID</label>
                        <input type="text" name="tax_id" value="{{ old('tax_id', $cliente->tax_id) }}">
                    </div>

                    <div>
                        <label>Origen</label>
                        <input type="text" name="source" value="{{ old('source', $cliente->source) }}">
                    </div>

                    <div>
                        <label>Activo</label>
                        <select name="active">
                            <option value="1" @selected(old('active', $cliente->active ? '1' : '0') == '1')>Sí</option>
                            <option value="0" @selected(old('active', $cliente->active ? '1' : '0') == '0')>No</option>
                        </select>
                    </div>

                    <div class="full">
                        <label>Notas</label>
                        <textarea name="notes">{{ old('notes', $cliente->notes) }}</textarea>
                    </div>
                </div>

                <p style="margin-top:22px">
                    <button class="btn" type="submit">Actualizar cliente</button>
                    <a class="btn btn-gray" href="{{ route('crm.clientes.index') }}">Cancelar</a>
                </p>
            </form>

            {{-- FORMULARIO 2: SEGUIMIENTO COMERCIAL --}}
            <div class="card" style="margin-top:24px;">
                <h2>Seguimiento comercial</h2>
                <p style="color:#64748b;margin-top:0;">
                    Registra llamadas, citas, prioridad y próximo contacto del prospecto.
                </p>

                @if($cliente->commercial_status === 'prospecto' && is_null($cliente->reviewed_at))
                    <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800;">
                        Prospecto nuevo sin revisar.
                    </div>
                @endif

                <form method="POST" action="{{ route('crm.clientes.seguimiento', $cliente) }}">
                    @csrf

                    <div class="form-grid">
                        <div>
                            <label>Estatus de seguimiento</label>
                            <select name="lead_status" required>
                                <option value="nuevo" @selected(($cliente->lead_status ?? 'nuevo') === 'nuevo')>Nuevo</option>
                                <option value="sin_revisar" @selected(($cliente->lead_status ?? '') === 'sin_revisar')>Sin revisar</option>
                                <option value="contactado" @selected(($cliente->lead_status ?? '') === 'contactado')>Contactado</option>
                                <option value="cita_agendada" @selected(($cliente->lead_status ?? '') === 'cita_agendada')>Cita agendada</option>
                                <option value="en_negociacion" @selected(($cliente->lead_status ?? '') === 'en_negociacion')>En negociación</option>
                                <option value="convertido" @selected(($cliente->lead_status ?? '') === 'convertido')>Convertido a cliente</option>
                                <option value="descartado" @selected(($cliente->lead_status ?? '') === 'descartado')>Descartado</option>
                            </select>
                        </div>

                        <div>
                            <label>Prioridad</label>
                            <select name="lead_priority" required>
                                <option value="baja" @selected(($cliente->lead_priority ?? 'media') === 'baja')>Baja</option>
                                <option value="media" @selected(($cliente->lead_priority ?? 'media') === 'media')>Media</option>
                                <option value="alta" @selected(($cliente->lead_priority ?? 'media') === 'alta')>Alta</option>
                                <option value="urgente" @selected(($cliente->lead_priority ?? 'media') === 'urgente')>Urgente</option>
                            </select>
                        </div>

                        <div>
                            <label>Próximo seguimiento</label>
                            <input type="datetime-local"
                                name="next_follow_up_at"
                                value="{{ $cliente->next_follow_up_at ? $cliente->next_follow_up_at->format('Y-m-d\TH:i') : '' }}">
                        </div>

                        <div>
                            <label>Último contacto</label>
                            <input readonly value="{{ $cliente->last_contact_at ? $cliente->last_contact_at->format('d/m/Y H:i') : 'Sin contacto registrado' }}">
                        </div>

                        <div class="full">
                            <label>Notas internas de seguimiento</label>
                            <textarea name="internal_notes" rows="5" placeholder="Ejemplo: Se llamó al cliente, solicita propuesta de cuenta empresarial...">{{ old('internal_notes', $cliente->internal_notes) }}</textarea>
                        </div>
                    </div>

                    <button class="btn" type="submit" style="margin-top:16px;">
                        Guardar seguimiento
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

</body>
</html>