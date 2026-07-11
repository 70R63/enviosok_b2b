<aside class="sidebar">
    <div class="logo">CRM ZIGO</div>

    <div class="menu">
        <a href="{{ route('crm.dashboard') }}"
           class="{{ request()->routeIs('crm.dashboard') ? 'active' : '' }}">
            Dashboard
        </a>

        <a href="{{ route('crm.clientes.index') }}"
           class="{{ request()->routeIs('crm.clientes.*') ? 'active' : '' }}">
            Clientes
        </a>

        <a href="#">Usuarios B2C</a>
        <a href="#">Usuarios Negocios</a>
        <a href="#">Usuarios Soporte</a>
        <a href="#">Empresas</a>
        <a href="#">Prospectos</a>
        <a href="#">Guías</a>
        <a href="#">Incidencias</a>
        <a href="#">Adeudos</a>
        <a href="#">Pagos</a>
        <a href="#">Paqueterías</a>
        <a href="{{ route('crm.api-hub.index') }}"
           class="{{ request()->routeIs('crm.api-hub.*') ? 'active' : '' }}">
            API Hub
        </a>

        <a href="{{ route('crm.seguridad.index') }}"
           class="{{ request()->routeIs('crm.seguridad.*') ? 'active' : '' }}">
            Seguridad
        </a>

        <a href="#">Configuración</a>

        <form method="POST" action="{{ route('crm.logout') }}">
            @csrf
            <button class="logout-btn" type="submit">Cerrar sesión</button>
        </form>
    </div>
</aside>