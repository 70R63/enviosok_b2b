<nav class="devops-nav" aria-label="Navegación DevOps">
    <a href="{{ url('/') }}" class="{{ request()->path() === '/' ? 'active' : '' }}">Resumen</a>
    <a href="{{ url('/deployments') }}" class="{{ request()->is('deployments*') && request('view') !== 'history' ? 'active' : '' }}">Despliegues</a>
    <a href="{{ url('/releases') }}" class="{{ request()->is('releases*') ? 'active' : '' }}">Releases</a>
    <a href="{{ url('/comparison') }}" class="{{ request()->is('comparison*') ? 'active' : '' }}">Comparación</a>
    <a href="{{ url('/health') }}" class="{{ request()->is('health*') ? 'active' : '' }}">Health checks</a>
    <a href="{{ url('/alerts') }}" class="{{ request()->is('alerts*') ? 'active' : '' }}">Alertas</a>
    <a href="{{ url('/audits') }}" class="{{ request()->is('audits*') ? 'active' : '' }}">Auditoría</a>
    <a href="{{ url('/reports') }}" class="{{ request()->is('reports*') ? 'active' : '' }}">Reportes</a>
</nav>
