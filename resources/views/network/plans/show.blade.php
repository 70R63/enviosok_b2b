@extends('network.layout') @section('title',$plan->name.' - ZIGO Network') @section('content')
<div class="title">{{ $plan->name }}</div><div class="subtitle">{{ $plan->code }}</div>
<div class="card"><p><strong>Estado:</strong> {{ ucfirst($plan->status) }}</p><p><strong>Mensual:</strong> {{ $plan->monthly_price??'N/A' }} {{ $plan->currency }}</p><p><strong>Anual:</strong> {{ $plan->annual_price??'N/A' }} {{ $plan->currency }}</p><p><strong>Operaciones:</strong> {{ $plan->included_operations??'N/A' }}</p></div>
<div class="card"><h2>Módulos asociados</h2><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Incluido</th><th>Límite</th></tr></thead><tbody>@forelse($plan->modules as $module)<tr><td>{{ $module->code }}</td><td>{{ $module->name }}</td><td>{{ $module->pivot->is_included?'Sí':'No' }}</td><td>{{ $module->pivot->limit_value??'N/A' }}</td></tr>@empty<tr><td colspan="4">Sin módulos asociados.</td></tr>@endforelse</tbody></table></div></div>
@endsection
