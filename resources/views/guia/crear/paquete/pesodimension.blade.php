<!-- Clone peso -->
<div id="clone" style="display: none;">
	<div class="input-group mb-3 clone" >
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1">Peso (Kg.) <span class="tx-danger">*</span></span>
		</div>
		{!! Form::text('peso[]', null,
			['class' 		=> 'form-control'
			,'placeholder'	=> 'Ejemplos 0.1, 1, 10, 12.5'
			,'id'		=> 'peso[]'
			,'autocomplete'	=>	'off'
			])
		!!}
		<button id="removeRow" type="button" class="btn btn-danger">
			<i class="fe fe-trash-2 wd-20 ht-20 text-center tx-18"></i>
		</button>
	</div>
	<div class="input-group mb-3">
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1">Dimensiones <span class="tx-danger">*</span></span>
		</div>
		{!! Form::text('largo[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'largo'
			,'placeholder'	=> 'Largo '
			
			])
		!!}

		{!! Form::text('ancho[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'ancho'
			,'placeholder'	=> 'Ancho  '
			
			])
		!!}

		{!! Form::text('alto[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'alto'
			,'placeholder'	=> 'Alto '
			
			])
		!!}
	</div>
</div>
<!-- Fin Clone peso -->
