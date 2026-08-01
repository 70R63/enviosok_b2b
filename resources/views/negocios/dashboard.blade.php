@extends('negocios.layouts.app')

@section('title', 'Dashboard | ZIGO Negocios')

@section('content')
    <h1 class="title">Portal Negocios ZIGO</h1>
    <div class="subtitle">Consola empresarial para operación logística, usuarios, reportes y facturación.</div>

    <div class="grid">
        <div class="card"><div class="label">Guías del mes</div><div class="value">0</div></div>
        <div class="card"><div class="label">Saldo disponible</div><div class="value">$0</div></div>
        <div class="card"><div class="label">Usuarios</div><div class="value">0</div></div>
        <div class="card"><div class="label">Adeudos</div><div class="value">0</div></div>
    </div>

    <div class="section">
        <h2>Funciones empresariales</h2>
        <p><strong>Envíos:</strong> creación individual y masiva de guías.</p>
        <p><strong>Usuarios:</strong> administración de operadores de la empresa.</p>
        <p><strong>Reportes:</strong> consulta de consumo, pagos, entregas y adeudos.</p>
        <p><strong>API:</strong> acceso a integraciones para ERP, ecommerce o sistemas internos.</p>
    </div>
@endsection
