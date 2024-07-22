<div class="col-sm-5">
	    <div>
	        <span class="tx-18 mb-5">DESTINO 1</span> 
	    </div>
	</div>
<div class="checkManualHtml">
	
	<div class="input-group mb-3 checkSemiHtml">
		<div class="input-group-prepend">
			<span class="input-group-text" id="basic-addon1"> Nombre<span class="tx-danger">*</span></span>
		</div>
		{!! Form::select('cliente'
			, array()
			,'MEX'
			,['class' 		=> 'form-control select2 cotizacionSemi'
				,'placeholder'	=> 'Seleccionar'
				,'required'	=> ''
				,'name'		=> 'cliente'
				,'id'		=> 'cliente'
			]);
		!!}
	</div>
</div>

<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1"> CP <span class="tx-danger">*</span></span>
	</div>
	{!! Form::text('cp_d', null,
		['class' 		=> 'form-control cotizacionManual cotizacionSemi'
			,'id'		=> 'cp_d'
			,'placeholder'	=> 'Codigo Postal'
			,'required'	=> ''
			,'readonly' =>	'true'
			,'pattern'	=> '\d{5}'
		])
	!!}
	
</div>