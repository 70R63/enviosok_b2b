<div class="col-sm-12 ">
    <div class="card custom-card">
        <div class="card-body">
        	<div class="card-item">
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">NOMBRE
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('nombre'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'nombre'
							,'required'	=>	'true'
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">CONTACTO
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('contacto'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'contacto'
							,'required'	=>	'true'
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">DIRECCIÓN
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('direccion'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'direccion'
							,'required'	=>	'true'
							,'maxlength'	=> 30
						])
					!!}

					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">NO. EXTERIOR
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('no_ext'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'no_ext'
							,'required'	=>	'true'
						])
					!!}

					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">NO. INTERIOR</span>
					</div>

					{!! Form::text('no_int'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'no_int'
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">REFERENCIA
						</span>
					</div>

					{!! Form::text('direccion2'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'direccion2'
							
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">C.P.
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('cp'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'cp'
							,'required'	=>	'true'
						])
					!!}

					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">COLONIA
							<span class="tx-danger">*</span>
						</span>
					</div>
					{!! Form::select('colonia'
						, ( isset($objeto->colonia)) ? array($objeto->colonia) : array()
						,"0"
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Seleccionar'
							,'required'	=> 'true'
							,'name'		=> 'colonia'
							,'id'		=> 'colonia'
							
						]);
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">CIUDAD
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('ciudad'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'ciudad'
							,'required'	=>	'true'
							,'readonly' =>  'true'
						])
					!!}
				
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">ENTIDAD FEDERATIVA
							<span class="tx-danger">*</span>
						</span>
					</div>
					{!! Form::text('entidad_federativa'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'entidad_federativa'
							,'required'	=>	'true'
							
						])
					!!}

				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">CLIENTE
							<span class="tx-danger">*</span>
						</span>
					</div>
					{!! Form::select('empresa_id'
						, $pluckEmpresa
						,null
						,['class' 		=> 'form-control select2'
							,'placeholder'	=> 'Seleccionar'
							,'required'	=> 'true'
							,'id'		=> 'empresa_id_nojs'
						]);
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">CELULAR
							<span class="tx-danger">*</span>
						</span>
					</div>

					{!! Form::text('celular'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'celular'
							,'required'	=>	'true'
						])
					!!}
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">TELEFONO
						</span>
					</div>

					{!! Form::text('telefono'
						, null
						,['class' 		=> 'form-control'
							,'id'		=> 'telefono'
							
						])
					!!}
				</div>

			</div>
			<!-- fin class="card-item" -->
		</div>
		<!-- fin class="card-body" -->
	</div>
	<!-- fin class="card custom-card" -->
</div>