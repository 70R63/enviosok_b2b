<div class="col-sm-12 ">
    <div class="card custom-card">
        <div class="card-body">
        	<div class="card-item">
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">
							NOMBRE 
							<span class="tx-danger">*</span>
						</span>
					</div>
					
					{!! Form::select('nombre'
						,$pluckLtd
						,isset($ltd->id) ? $ltd->id : 0
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Seleccionar'
							,'required'	=> 'true'
							,'id'		=> 'nombre'
						]);
					!!}
					   
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">
							EJECUTIVO COMERCIAL <span class="tx-danger">*</span>
						</span>
					</div>
					{!! Form::text('responsable_legal'
						, null
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Nombre del asesor de cuenta '
							,'id'		=> 'responsable_legal'
							,'required'	=>	'true'
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">@</span>
					</div>
					{!! Form::email('email'
						,null
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Correo electronico '
							,'id'		=> 'email'
							,'required'	=>	'true'
						]
					); !!}
				</div>
				
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">Descuento (%) <span class="tx-danger">*</span></span>
					</div>
					<div class="handle-counter" id="handleCounterMax100">
						<text class="counter-minus btn btn-light">-</text>
						
						{!! Form::text('descuento'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'descuento'
							,'required'	=>	'true'
						])
					!!}

						<text class="counter-plus btn btn-light">+</text>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>