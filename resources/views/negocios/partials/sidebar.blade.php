<aside class="sidebar">
    <div class="logo">ZIGO Negocios</div>
    <nav class="menu" aria-label="Navegación principal">
        <a href="{{ route('negocios.dashboard') }}" class="{{ request()->routeIs('negocios.dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="#">Nuevo envío</a>
        <a href="{{ route('negocios.guias.index') }}" class="{{ request()->routeIs('negocios.guias.*') ? 'active' : '' }}">Mis guías</a>
        <a href="#">Cotizaciones</a>
        <a href="#">Usuarios</a>
        <a href="#">Sucursales</a>
        <a href="#">Direcciones</a>
        <a href="#">Saldo</a>
        <a href="#">Facturación</a>
        <a href="#">Adeudos</a>
        <a href="#">Reportes</a>
        <a href="#">API</a>
        <a href="#">Configuración</a>
        <form method="POST" action="{{ route('negocios.logout') }}">
            @csrf
            <button class="logout-btn" type="submit">Cerrar sesión</button>
        </form>
    </nav>
</aside>
