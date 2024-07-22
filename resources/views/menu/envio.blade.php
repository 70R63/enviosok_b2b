<li class="nav-item">
	<a class="nav-link with-sub" href="#">
		<span class="shape1"></span>
		<span class="shape2"></span>
		<i class="fe fe-truck sidemenu-icon"></i>
		<span class="sidemenu-label">E N V I O S</span>
		<i class="angle fe fe-chevron-right"></i>
	</a>
	<ul class="nav-sub">
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('envio.index') }}">Dashboard</a>
		</li>
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ url('envios/cotizaciones') }}" >Cotizar</a>
		</li>
		<li class="nav-sub-item">
			<a class="nav-sub-link" href="{{ route('envios.guia.index') }}">Guias</a>
		</li>
		
	</ul>
</li>
