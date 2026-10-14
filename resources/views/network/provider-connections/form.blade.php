@extends('network.layout')
@section('title', 'Configuración provider - Network')
@section('content')
<h1 class="title">{{ $connection ? 'Configurar relación' : 'Nueva relación' }}</h1>
<p><a href="{{ route('network.provider-connections.index') }}">Volver a relaciones</a></p>
<p>Los servicios, cobertura y tarifas son administrados por el proveedor. No se copian al consumer.</p>
@if(!$connection)
<form method="GET" class="card grid">
<div class="field"><label for="consumer-picker">Consumer</label><select id="consumer-picker" name="consumer_uuid" required><option value="">Seleccionar</option>@foreach($tenants as $tenant)<option value="{{ $tenant->uuid }}" @selected($consumer?->id === $tenant->id)>{{ $tenant->name }} ({{ $tenant->slug }})</option>@endforeach</select></div>
<div class="field"><label for="provider-picker">Provider</label><select id="provider-picker" name="provider_uuid" required><option value="">Seleccionar</option>@foreach($tenants as $tenant)<option value="{{ $tenant->uuid }}" @selected($provider?->id === $tenant->id)>{{ $tenant->name }} ({{ $tenant->slug }})</option>@endforeach</select></div>
<div><button class="btn">Consultar servicios del provider</button></div>
</form>
@endif
@if($consumer && $provider)
<form method="POST" action="{{ $connection ? route('network.provider-connections.update', $connection) : route('network.provider-connections.store') }}" class="card">
@csrf
<input type="hidden" name="consumer_uuid" value="{{ $consumer->uuid }}"><input type="hidden" name="provider_uuid" value="{{ $provider->uuid }}">
<h2>{{ $consumer->name }} → {{ $provider->name }}</h2>
<div class="grid">
<div class="field"><label for="capability">Capability</label><select id="capability" name="capability"><option>LOCAL_SHIPPING</option></select></div>
<div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="inactive" @selected(old('status', $connection?->status ?? 'inactive') === 'inactive')>Inactiva</option><option value="active" @selected(old('status', $connection?->status) === 'active')>Activa (requiere readiness sin FAIL)</option></select></div>
<div class="field"><label for="valid-from">Vigente desde</label><input id="valid-from" name="valid_from" type="datetime-local" value="{{ old('valid_from', $connection?->valid_from?->format('Y-m-d\TH:i')) }}"></div>
<div class="field"><label for="valid-to">Vigente hasta (exclusivo)</label><input id="valid-to" name="valid_to" type="datetime-local" value="{{ old('valid_to', $connection?->valid_to?->format('Y-m-d\TH:i')) }}"></div>
</div>
<fieldset><legend>Servicios autorizados de {{ $provider->name }}</legend>
@forelse($services as $service)
<p><label><input style="width:auto" type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, old('services', $authorized)))> {{ $service->name }} — {{ $service->code }} ({{ $service->status }}, {{ $service->published ? 'publicado' : 'no publicado' }})</label></p>
@empty<p>No hay servicios en este provider. Configurarlos desde su consola tenant.</p>@endforelse
</fieldset>
<p>Puede guardarse incompleta en estado inactivo. Cambiar servicios o desactivar puede impedir selecciones/fulfillments pendientes; no altera contratos ni economía congelada.</p>
<button class="btn">Guardar configuración</button>
</form>
<div class="card"><h2>Pricing comercial consumer / provider</h2><p>Clave carrier para la administración comercial existente: <code>{{ $commercialKey }}</code>.</p>
<p>Configurar explícitamente margen/precio con esta clave en <a href="{{ route('crm.pricing.index') }}">Pricing comercial</a>, segmento <code>anonymous</code>, plan vacío y paquete <code>all</code> o tipos autorizados. Requiere acceso CRM comercial existente. No se genera ninguna regla automáticamente.</p></div>
@endif
@if($report)
<div class="card table-wrap"><h2>Readiness {{ $connection->status === 'inactive' ? 'para activación' : 'actual' }}</h2>
<p>{{ $report['ready'] ? 'Sin FAIL de configuración. Verificar los WARN antes de operar.' : 'Hay errores críticos; completar configuración antes de activar.' }} {{ $connection->status === 'inactive' ? 'Esta relación continúa inactiva.' : '' }}</p>
<table><thead><tr><th>Estado</th><th>Diagnóstico</th></tr></thead><tbody>@foreach($report['checks'] as $check)<tr><td>{{ $check['status'] }}</td><td>{{ $check['message'] }}</td></tr>@endforeach</tbody></table>
</div>
@endif
@endsection
