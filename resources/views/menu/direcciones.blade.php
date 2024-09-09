<li class="nav-item">
    <a class="nav-link with-sub" href="#">
        <img src="{{asset('assets/azul_2.svg')}}" class="ml-1" height="35"  alt="">
        <span class="sidemenu-label">Direcciones</span>
        <i class="angle fe fe-chevron-right"></i>
    </a>
    <ul class="nav-sub">
        <li class="nav-sub-item">
            <a class="nav-sub-link" href="{{ route('sucursales.index') }}">Remitente</a>
        </li>
    </ul>
    <ul class="nav-sub">
        <li class="nav-sub-item">
            <a class="nav-sub-link" href="{{ route('clientes.index') }}">Destinatario</a>
        </li>
    </ul>
</li>
