<li class="nav-item">
    <a class="nav-link with-sub" href="#">
        <img src="{{asset('assets/azul_5.svg')}}" class="ml-1" height="35"  alt="">
        <span class="sidemenu-label">Reportes</span>
        <i class="angle fe fe-chevron-right"></i>
    </a>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('ventas.index') }}">Ventas </a>
		</li>
	</ul>

	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('repesajes.index') }}">Repesaje </a>
		</li>
	</ul>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('reportes.pagado.index') }}">Pagos </a>
		</li>
	</ul>
</li>
