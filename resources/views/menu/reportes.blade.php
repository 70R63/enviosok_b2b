<li class="nav-item">
	<a class="nav-link with-sub" href="#">
		<span class="shape1"></span>
		<span class="shape2"></span>
		<i class="pe-7s-display2 sidemenu-icon"></i>
		<span class="sidemenu-label">R E P O R T E S <span></span> </span>
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