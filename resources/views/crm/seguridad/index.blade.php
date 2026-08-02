@extends('crm.layout')

@section('content')
    <div class="title">Centro de Seguridad</div>
    <div class="subtitle">Identidades, roles y actividad disponible en los portales ZIGO.</div>
    @include('crm.seguridad.partials.nav')

    <div class="summary-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        @foreach([
            'internos' => 'Personal ZIGO',
            'b2b' => 'Clientes B2B',
            'b2c' => 'Clientes B2C',
            'inactivos' => 'Inactivos',
            'roles' => 'Roles',
            'sin_clasificar' => 'Por revisar',
        ] as $key => $label)
            <div class="summary-card"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></div>
        @endforeach
    </div>

    <div class="card">
        <h2 style="margin-top:0">Operación de seguridad</h2>
        <p class="muted">El catálogo de permisos permanece disponible en modo de solo lectura y ya no forma parte de la operación principal.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
            <a class="btn" href="{{ route('crm.seguridad.usuarios') }}">Identidades</a>
            <a class="btn" href="{{ route('crm.seguridad.roles') }}">Roles</a>
            <a class="btn" href="{{ route('crm.seguridad.auditoria') }}">Auditoría</a>
        </div>
    </div>
@endsection
