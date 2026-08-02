@extends('crm.layout')
@section('title', 'Canales públicos - CRM ZIGO')

@push('styles')
<style>
    .channel-section h2{margin:0 0 18px}
    .channel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
    .channel-card{min-width:0;padding:20px;border:1px solid #dbe3ee;border-radius:16px;background:#fff;box-shadow:0 4px 12px rgba(15,23,42,.04)}
    .channel-card h3{margin:0 0 18px;font-size:19px;color:#0f172a}
    .channel-fields{display:grid;grid-template-columns:minmax(0,1fr) 92px;gap:16px}
    .channel-field{min-width:0}
    .channel-field-full{grid-column:1/-1}
    .channel-card label{display:block;font-size:13px;font-weight:900;margin-bottom:7px;color:#334155}
    .channel-card input[type="text"],.channel-card input[type="number"]{max-width:100%;min-width:0}
    .field-help{display:block;margin-top:7px;color:#64748b;font-size:12px;line-height:1.4}
    .channel-controls{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0}
    .active-control{margin:0!important;display:flex!important;align-items:center;gap:11px;min-height:42px;padding:9px 13px;border-radius:12px;background:#f1f5f9;color:#1e293b!important;cursor:pointer}
    .active-control input[type="checkbox"]{position:absolute;opacity:0;width:1px;height:1px}
    .switch-track{position:relative;width:44px;height:24px;flex:0 0 44px;border-radius:999px;background:#94a3b8;transition:background-color .2s ease}
    .switch-track::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.3);transition:transform .2s ease}
    .active-control input:checked + .switch-track{background:#2563eb}
    .active-control input:checked + .switch-track::after{transform:translateX(20px)}
    .active-control input:focus-visible + .switch-track{outline:3px solid rgba(37,99,235,.3);outline-offset:2px}
    .order-field{width:92px;flex:0 0 92px}
    .actions{display:flex;justify-content:flex-end;margin-top:24px}
    .actions .btn{min-width:190px;padding:13px 22px;font-size:16px}
    .validation{background:#fee2e2;color:#991b1b;padding:14px;border-radius:10px;margin-bottom:18px}
    .validation ul{margin:8px 0 0 20px}
    @media(max-width:1050px){.channel-grid{grid-template-columns:1fr}}
    @media(max-width:520px){
        .channel-card{padding:16px}
        .channel-fields{grid-template-columns:minmax(0,1fr)}
        .channel-field-full{grid-column:auto}
        .channel-controls{align-items:stretch;flex-direction:column}
        .active-control,.order-field{width:100%;flex-basis:auto}
        .actions .btn{width:100%}
    }
</style>
@endpush

@section('content')
<div class="title">Canales públicos</div>
<div class="subtitle">Redes sociales y medios de contacto utilizados por B2C, B2B y landings públicas.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="validation">Revisa los datos capturados.<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('crm.marketing.public-channels.update') }}">
    @csrf
    @method('PUT')
    @php
        $sections = [
            'Redes sociales' => [
                'facebook' => ['Facebook', 'https://facebook.com/zigologistica', 'URL HTTPS completa.'],
                'instagram' => ['Instagram', 'https://instagram.com/zigologistica o @zigologistica', 'URL HTTPS o usuario.'],
                'tiktok' => ['TikTok', 'https://tiktok.com/@zigologistica o zigologistica', 'URL HTTPS o usuario.'],
            ],
            'Contacto' => [
                'whatsapp' => ['WhatsApp', '5215512345678', 'Número con código de país, solo dígitos.'],
                'commercial_phone' => ['Teléfono comercial', '55 1234 5678', 'Número con código de área.'],
                'support_phone' => ['Teléfono soporte', '55 1234 5678', 'Número con código de área.'],
                'commercial_email' => ['Correo comercial', 'ventas@dominio.com', 'Dirección válida.'],
                'support_email' => ['Correo soporte', 'soporte@dominio.com', 'Dirección válida.'],
            ],
        ];
    @endphp
    @foreach($sections as $section => $items)
        <section class="card channel-section">
            <h2>{{ $section }}</h2>
            <div class="channel-grid">
            @foreach($items as $key => $settings)
                @php
                    $defaultLabel = $settings[0];
                    $placeholder = $settings[1];
                    $help = $settings[2];
                    $channel = $channels->get($key);
                    $defaultOrder = ($loop->parent->index * 100) + ($loop->iteration * 10);
                @endphp
                <article class="channel-card">
                    <h3>{{ $defaultLabel }}</h3>
                    <div class="channel-fields">
                    <div class="channel-field">
                        <label for="{{ $key }}_label">Etiqueta</label>
                        <input id="{{ $key }}_label" type="text" name="channels[{{ $key }}][label]" maxlength="120" value="{{ old("channels.$key.label", optional($channel)->label ?? $defaultLabel) }}">
                    </div>
                    <div class="channel-field channel-field-full">
                        <label for="{{ $key }}_value">Valor o URL</label>
                        <input id="{{ $key }}_value" type="text" name="channels[{{ $key }}][value]" maxlength="255" placeholder="{{ $placeholder }}" value="{{ old("channels.$key.value", optional($channel)->url ?: optional($channel)->value) }}" aria-describedby="{{ $key }}_help">
                        <small id="{{ $key }}_help" class="field-help">{{ $help }}</small>
                        <input type="hidden" name="channels[{{ $key }}][url]" value="">
                    </div>
                    </div>
                    <div class="channel-controls">
                        <label class="active-control">
                            <input type="hidden" name="channels[{{ $key }}][is_active]" value="0">
                            <input type="checkbox" name="channels[{{ $key }}][is_active]" value="1" @checked((bool) old("channels.$key.is_active", optional($channel)->is_active ?? false))>
                            <span class="switch-track" aria-hidden="true"></span>
                            <span>Activo</span>
                        </label>
                        <div class="order-field">
                            <label for="{{ $key }}_order">Orden</label>
                            <input id="{{ $key }}_order" type="number" min="0" max="4294967295" name="channels[{{ $key }}][sort_order]" value="{{ old("channels.$key.sort_order", optional($channel)->sort_order ?? $defaultOrder) }}" required>
                        </div>
                    </div>
                </article>
            @endforeach
            </div>
        </section>
    @endforeach
    <div class="actions"><button type="submit" class="btn">Guardar cambios</button></div>
</form>
@endsection
