<div class="col-sm-5 ">
    <div>
        <span class="tx-18 mb-3 ">ORIGEN</span> 
    </div>
</div>
<div class="checkManualHtml">
	<div class="input-group mb-3 ">
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1"> Nombre <span class="tx-danger">*</span></span>
		</div>
		{!! Form::select('sucursal'
			, $sucursal
			,'MEX'
			,['class' 		=> 'form-control select2'
				,'placeholder'	=> 'Seleccionar'
				,'required'	=> ''
				,'name'		=> 'sucursal'
				,'id'		=> 'sucursal'

			]);
		!!}
	</div>
</div>
<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> CP <span class="tx-danger">*</span></span>
	</div>
	{!! Form::text('cp', null,
		['class' 		=> 'form-control cotizacionManual'
			,'id'		=> 'cp'
			,'placeholder'	=> 'Codigo Postal'
			,'required'	=> ''
			,'readonly' =>	'true'
			,'pattern'	=> '\d{5}'
		])
	!!}
	
</div>