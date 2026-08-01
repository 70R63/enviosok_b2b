<div class="security-nav" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px">
    <a class="btn {{ request()->routeIs('crm.seguridad.index') ? '' : 'btn-gray' }}" href="{{ route('crm.seguridad.index') }}">Resumen</a>
    <a class="btn {{ request()->routeIs('crm.seguridad.usuarios*') ? '' : 'btn-gray' }}" href="{{ route('crm.seguridad.usuarios') }}">Identidades</a>
    <a class="btn {{ request()->routeIs('crm.seguridad.roles*') ? '' : 'btn-gray' }}" href="{{ route('crm.seguridad.roles') }}">Roles</a>
    <a class="btn {{ request()->routeIs('crm.seguridad.auditoria') ? '' : 'btn-gray' }}" href="{{ route('crm.seguridad.auditoria') }}">Auditoría</a>
</div>
