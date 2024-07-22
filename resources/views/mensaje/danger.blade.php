@if(Session::has('dangers'))
<div class="alert alert-block alert-danger">
	<button type="button" class="close" data-dismiss="alert">
		<i class="ace-icon fa fa-times"></i>
	</button>
	<p>
		Error inesperado:
	</p>
	<ul>
		@foreach(Session::get('dangers') as $danger)
		 	
			<li> {{ $danger }} </li>
		@endforeach
	</ul>
</div>

@endif