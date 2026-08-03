@extends('layouts.app')
@section('content')
<div class="container"><h1>Diagnóstico de proveedores</h1><p>Ambiente: {{ app()->environment() }}</p><p>READY_FOR_B2C_QUOTE_ADAPTER={{ $ready ? 'true' : 'false' }}</p><table class="table"><thead><tr><th>Carrier</th><th>Operación</th><th>Estrategia</th><th>Proveedor HTTP</th><th>Bloqueos</th></tr></thead><tbody>@foreach($strategies as $operation=>$strategy)<tr><td>Estafeta</td><td>{{ $operation }}</td><td>{{ $strategy['strategy'] }}</td><td>{{ $strategy['http_provider'] ?? 'none' }}</td><td>{{ implode(', ', $strategy['reasons'] ?? []) }}</td></tr>@endforeach</tbody></table><p>Credenciales Xperta: {{ config('services.xperta.base_url') && config('services.xperta.api_key') ? 'configured' : 'missing' }}</p></div>
@endsection
