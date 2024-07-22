<!-- Inicio Class Embalaje -->
<div class="embalaje">	
	<div class="input-group mb-3">
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1">Peso (Kg.) <span class="tx-danger">*</span></span>
		</div>
		{!! Form::text('peso[]', null,
			['class' 		=> 'form-control'
			,'placeholder'	=> 'Ejemplos 0.1, 1, 10, 12.5'
			,'id'		=> 'peso'
			,'required'	=> 'true'
			])
		!!}
		<!--
		@if(3 == 3)
			<button id="addRow" type="button" class="btn btn-info">
		    	<i class="fe fe-plus wd-20 ht-20 text-center tx-18"></i>
		    </button>
		@endif
		-->
	</div>

	<div class="input-group mb-3">
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1">Dimensiones <span class="tx-danger">*</span></span>
		</div>
		{!! Form::text('largo[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'largo'
			,'placeholder'	=> 'Largo '
			,'required'	=> 'true'
			])
		!!}

		{!! Form::text('ancho[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'ancho'
			,'placeholder'	=> 'Ancho  '
			,'required'	=> 'true'
			])
		!!}

		{!! Form::text('alto[]', null,
			['class' 		=> 'form-control'
			,'id'			=> 'alto'
			,'placeholder'	=> 'Alto '
			,'required'	=> 'true'
			])
		!!}
	</div>
	<div id="multiPieza"></div>
</div>
<!-- Fin Class Embalaje -->