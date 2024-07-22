@extends('dashboard')
@section('content')
@include('roles.dashboard.header')

<div class="container"> 
	<div class="card">
		<div class="card-header">
			<h4>NOMBRE: {{$roles->name}} </h4>
			<h4>SLUG: {{$roles->slug}} </h4>
		</div>
		<div class="card-body">
			<h5 class="card-tittle">Permisos</h5>
			<p class="card-text">
				........................
			</p>
		</div>
		<div class="card-footer"> <a href="{{route('roles.index')}}" class="btn btn-info">Inicio</a></div>
	</div>
</div>
@endsection