@extends('devops.layout')
@section('title', 'Health checks - ZIGO DevOps')
@section('content')
<div class="title">Health checks</div><div class="subtitle">Comprobaciones fijas; no se aceptan URLs capturadas por usuarios.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
@if($canRunHealth)<form class="card" method="POST" action="{{ url('/health/run') }}">@csrf<div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;"><div><label for="environment"><strong>Ambiente</strong></label><select id="environment" name="environment" style="min-width:180px;"><option value="stage">Stage</option><option value="production">PRD</option></select></div><button class="btn" type="submit">Ejecutar health checks</button></div></form>@endif
@foreach(['stage' => 'Stage', 'production' => 'PRD'] as $environment => $label)
<section class="card"><h2>{{ $label }}</h2><div class="table-wrap"><table><thead><tr><th>Check</th><th>Estado</th><th>HTTP</th><th>Tiempo</th><th>Mensaje</th><th>Fecha</th></tr></thead><tbody>
@forelse($checks->get($environment, collect()) as $check)<tr><td>{{ $check->check_key }}</td><td><span class="badge badge-gray">{{ $check->status }}</span></td><td>{{ $check->http_status ?? '—' }}</td><td>{{ $check->duration_ms !== null ? $check->duration_ms . ' ms' : '—' }}</td><td>{{ $check->message }}</td><td>{{ $check->checked_at->format('d/m/Y H:i:s') }}</td></tr>@empty<tr><td colspan="6">Sin resultados.</td></tr>@endforelse
</tbody></table></div></section>
@endforeach
@endsection
