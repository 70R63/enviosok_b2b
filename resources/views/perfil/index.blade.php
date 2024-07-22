
<div class="dropdown main-profile-menu">
	<a class="d-flex" href="">
		<span class="main-img-user" ><img alt="avatar" src="{{  url('spruha/img/users/user.png')}}"></span>
	</a>
	<div class="dropdown-menu">
		@csrf
		<div class="header-navheading">
			<h6 class="main-notification-title">{{ Auth::user()->name }}</h6>
			<p class="main-notification-text">{{Auth::user()->roles->isNotEmpty() ? Auth::user()->roles->first()->name : ""}}</p>
			
		</div>
		<a class="dropdown-item border-top" href="{{route('profile.index')}}">
			<i class="fe fe-user"></i> Mi perfil
		</a>
		<a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('submit-form').submit();">
			<i class="fe fe-power"></i> Cerrar sesión
		</a>
		<form id="submit-form" action="{{ route('logout') }}" method="POST" class="hidden">
    		@csrf
    		@method('POST')
    	</form>
	</div>
</div>
