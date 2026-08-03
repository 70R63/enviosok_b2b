<nav class="devops-nav" aria-label="Navegación DevOps">
    <a href="{{ url('/') }}" class="{{ request()->path() === '/' ? 'active' : '' }}">Resumen</a>
    <a href="{{ url('/deployments') }}" class="{{ request()->is('deployments*') && request('view') !== 'history' ? 'active' : '' }}">Despliegues</a>
    <a href="{{ url('/health') }}" class="{{ request()->is('health*') ? 'active' : '' }}">Health checks</a>
    <a href="{{ url('/deployments?view=history') }}" class="{{ request('view') === 'history' ? 'active' : '' }}">Historial</a>
</nav>
