<div class="input-group mb-3">

	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Piezas <span class="tx-danger">*</span></span>
	</div>
		
	<div class="handle-counter" id="handleCounterMax40">
		<text class="counter-minus btn btn-light">-</text>
		<input type="text" value="1" class="form-control" name="piezas" id="piezas" required="">
		<text class="counter-plus btn btn-light">+</text>
	</div>
	
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Presiona para multipieza</span>
		<button id="addRow" type="button" class="btn btn-info" title="Multi-Pieza">
	    	<i class="mdi mdi-animation wd-20 ht-20 text-center tx-18"></i>
	    </button>
	</div>

</div>

<div id="clone" class="input-group mb-3 registroMultipieza">

	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Peso (Kg.) <span class="tx-danger">*</span></span>
	</div>
	{!! Form::text('peso[]', null,
		['class' 		=> 'form-control multi'
			,'id'		=> 'peso[]'
			,'data-parsley-type' => 'number'
			,'min'	=>	'0.1'
			,'placeholder'	=> 'Peso aproximado'
			,'required'	=> ''
		])
	!!}


	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1">Dimensiones (LxWxH) <span class="tx-danger">*</span></span>
	</div>
	{!! Form::text('largo[]', null,
		['class' 		=> 'form-control multi'
		,'id'			=> 'largo'
		,'placeholder'	=> 'Largo '
		,'required'		=> 'true'
		])
	!!}

	{!! Form::text('ancho[]', null,
		['class' 		=> 'form-control multi'
		,'id'			=> 'ancho'
		,'placeholder'	=> 'Ancho  '
		,'required'		=> 'true'
		])
	!!}

	{!! Form::text('alto[]', null,
		['class' 		=> 'form-control multi'
		,'id'			=> 'alto'
		,'placeholder'	=> 'Alto '
		,'required'		=> 'true'
		])
	!!}
</div>

<div id="multiPieza"></div>

<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Contenido </span>
	</div>
	{!! Form::text('contenido', null,
		['class' 		=> 'form-control'
			,'id'		=> 'contenido'
			,'placeholder'	=> 'Contenido del envio '
			
		])
	!!}
	
</div>

<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> Peso Facturado <span class="tx-danger">*</span></span>
	</div>
	{!! Form::text('pesoFacturado', null,
		['class' 		=> 'form-control'
			,'data-parsley-type' => 'number'
			,'data-parsley-type' =>'integer'
			,'min'	=>	'1'
			,'placeholder'	=> 'Valor que resulta del peso y las dimensiones'
			,'id'		=> 'pesoFacturado'
			,'required'	=> ''
			,'readonly' =>	'true'
		])
	!!}
</div>

@include('guia.crear.paquete.campos.seguro')
