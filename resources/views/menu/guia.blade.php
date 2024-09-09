<li class="nav-item">
    <a class="nav-link with-sub" href="#">
        <img src="{{asset('assets/azul_3.svg')}}" class="ml-1" height="35"  alt="">
        <span class="sidemenu-label">Guías</span>
        <i class="angle fe fe-chevron-right"></i>
    </a>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('guia.index') }}">Dashboard</a>
		</li>
	</ul>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('cotizaciones.index') }}">Creación</a>
		</li>
	</ul>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('rastreos.index') }}">Rastreo </a>
		</li>
	</ul>

	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('guias.masivas.index') }}">Masiva </a>
		</li>
	</ul>

</li>
