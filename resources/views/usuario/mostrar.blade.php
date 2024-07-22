@extends('dashboard') 
@section('content')

<div class="container"> 
	<div class="card">
		<div class="card-header">
			<h3>Nombre: {{ $users->name}} </h3>
			<h4>E-MAIL: {{ $users->email}} </h4>
			<h4>Nombre: {{ $users->name}} </h4>
		</div>
		<div class="card-body">
			<h5 class="card-tittle">Rol</h5>
			<p class="card-text">
				........................
			</p>

			<h5 class="card-tittle">Permisos</h5>
			<p class="card-text">
				........................
			</p>
		</div>
		<div class="card-footer"> <a href="{{ url()->previous() }}" class="btn btn-info">Volver</a></div>
	</div>
</div>
@endsection