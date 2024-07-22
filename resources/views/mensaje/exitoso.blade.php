@if(Session::has('success'))
<div class="alert alert-block alert-success">
	<button type="button" class="close" data-dismiss="alert">
		<i class="ace-icon fa fa-times"></i>
	</button>
	@foreach(Session::get('success') as $msj)
		<p></p>
		<strong><i class="ace-icon fa fa-check green"></i> {{ $msj }} </strong> 
	@endforeach
</div>

@endif