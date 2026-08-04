@extends('devops.layout')
@section('title', 'Detalle de despliegue - ZIGO DevOps')
@section('content')
<div class="title">{{ $deployment->package_name }}</div><div class="subtitle">Detalle y evidencia del paquete registrado.</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="summary-grid">
<div class="summary-card"><span>Ambiente</span><strong style="font-size:20px;">{{ $deployment->environment === 'production' ? 'PRD' : 'Stage' }}</strong></div>
<div class="summary-card"><span>Estado</span><strong style="font-size:20px;">{{ $deployment->status }}</strong></div>
<div class="summary-card"><span>Commit</span><strong style="font-size:16px;word-break:break-all;">{{ $deployment->commit_hash ?: '—' }}</strong></div>
<div class="summary-card"><span>Usuario</span><strong style="font-size:18px;">{{ $deployment->requester?->name ?: '—' }}</strong></div>
</div>
<section class="card"><h2>Identidad del paquete</h2><p><strong>SHA-256:</strong> <code style="word-break:break-all;">{{ $deployment->package_sha256 }}</code></p><p><strong>Branch:</strong> {{ $deployment->branch ?: '—' }}</p><p><strong>Fecha:</strong> {{ $deployment->created_at->format('d/m/Y H:i:s') }}</p></section>

@if($manifest)
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;">
<section class="card"><h2>Archivos</h2><ul>@forelse($manifest['files'] ?? [] as $file)<li><code>{{ $file['path'] }}</code></li>@empty<li>Sin detalle disponible.</li>@endforelse</ul></section>
<section class="card"><h2>Migraciones y seeders</h2><p><strong>Ejecutar migrate:</strong> {{ !empty($manifest['migrate']) ? 'Sí' : 'No' }}</p><h3>Migraciones</h3><ul>@forelse($manifest['migrations'] ?? [] as $migration)<li><code>{{ $migration }}</code></li>@empty<li>Ninguna.</li>@endforelse</ul><h3>Seeders</h3><ul>@forelse($manifest['seeders'] ?? [] as $seeder)<li><code>{{ $seeder }}</code></li>@empty<li>Ninguno.</li>@endforelse</ul></section>
</div>
@if(!empty($manifest['warnings']))<div class="alert alert-info"><strong>Advertencias</strong><ul>@foreach($manifest['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
@if(!empty($manifest['migration_applied']))<div class="alert alert-error">Este despliegue aplicó migraciones. El rollback de archivos no revierte la base de datos.</div>@endif
@endif

@if($canDeploy)
<section class="card"><h2>Acciones controladas</h2>
@if(in_array($deployment->status, ['uploaded', 'failed'], true))<form method="POST" action="{{ url('/deployments/' . $deployment->id . '/validate') }}" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').textContent='Validando…';">@csrf<button class="btn" type="submit">Validar paquete</button></form>@endif
@if($deployment->status === 'validated')
    @if(!config('zigo_devops.enabled'))<div class="alert alert-info">La ejecución está deshabilitada por configuración.</div>
    @elseif($deployment->environment === 'production' && !config('zigo_devops.allow_production'))<div class="alert alert-info">Los despliegues a producción están deshabilitados.</div>
    @else<form method="POST" action="{{ url('/deployments/' . $deployment->id . '/deploy') }}" onsubmit="if(this.dataset.submitting==='true'){return false;}this.dataset.submitting='true';const button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='Desplegando…';">@csrf
        @if($deployment->environment === 'production')<label for="production_confirmation"><strong>Escribe DESPLEGAR-PRD para confirmar</strong></label><input id="production_confirmation" name="production_confirmation" autocomplete="off" required style="max-width:320px;margin:8px 0 14px;">@endif
        <button class="btn" type="submit">Desplegar en {{ $deployment->environment === 'production' ? 'PRD' : 'Stage' }}</button>
    </form>@endif
@endif
@if($deployment->rollback_available)<form method="POST" action="{{ url('/deployments/' . $deployment->id . '/rollback') }}" style="margin-top:14px;" onsubmit="if(this.dataset.submitting==='true'){return false;}this.dataset.submitting='true';const button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='Revirtiendo…';">@csrf<button class="btn btn-gray" type="submit">Rollback de archivos</button></form>@endif
</section>
@endif

<section class="card"><h2>Logs sanitizados</h2><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Nivel</th><th>Paso</th><th>Mensaje</th></tr></thead><tbody>@forelse($deployment->logs as $log)<tr><td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->level }}</td><td>{{ $log->step }}</td><td>{{ $log->message }}</td></tr>@empty<tr><td colspan="4">Sin logs.</td></tr>@endforelse</tbody></table></div></section>
<section class="card"><h2>Timeline</h2><div class="table-wrap"><table><thead><tr><th>Paso</th><th>Hora</th><th>Duración ms</th><th>Nivel</th><th>Resultado</th><th>Mensaje</th></tr></thead><tbody>@foreach($timeline as $item)<tr><td>{{ $item['step'] }}</td><td>{{ $item['time'] ?: '—' }}</td><td>{{ $item['duration_ms'] ?? '—' }}</td><td>{{ $item['level'] }}</td><td>{{ $item['result'] }}</td><td>{{ $item['message'] }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
