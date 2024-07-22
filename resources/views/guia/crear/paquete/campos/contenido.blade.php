<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Contenido </span>
	</div>
	{!! Form::text('contenido', null,
		['class' 		=> 'form-control'
		,'id'			=> 'contenido'
		,'placeholder'	=> 'Breve descripcion del paquete'
			
		])
	!!}
</div>