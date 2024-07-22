<!-- Campos para el cliente con valor readonly -->
<div class="card custom-card">
    <div class="card-body">
    	<div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
            <label class="main-content-label mb-4">DETALLES DEL REMITENTE</label>
        </div>
    	<div class="card-item">
			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NOMBRE
					</span>
				</div>

				{!! Form::text('nombre'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'nombre'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CONTACTO
					</span>
				</div>

				{!! Form::text('contacto'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'contacto'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">DIRECCIÓN
					</span>
				</div>

				{!! Form::text('direccion'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. EXTERIOR</span>
				</div>

				{!! Form::text('no_ext'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'no_ext'
						,'required'	=>	'true'
						
					])
				!!}

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. INTERIOR</span>
				</div>

				{!! Form::text('no_int'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'no_int'
						
					])
				!!}
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">C.P.
					</span>
				</div>

				{!! Form::text('cp'
					, $objeto['cp_manual']
					,['class' 		=> 'form-control buscaCP'
						,'id'		=> 'cp'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">REFERENCIA
					</span>
				</div>

				{!! Form::text('direccion2'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion2'
						
					])
				!!}

			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">COLONIA
					</span>
				</div>

				{!! Form::select('colonia'
					, array()
					,"0"
					,['class' 		=> 'form-control'
						,'placeholder'	=> 'Seleccionar'
						,'required'	=> 'true'
						,'id'		=> 'colonia'
						
					]);
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CIUDAD
					</span>
				</div>

				{!! Form::text('ciudad'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'ciudad'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">
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
						,'readonly' =>  'true'		
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CELULAR
					</span>
				</div>

				{!! Form::text('celular'
					, ''
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
					, ''
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
{!! Form::hidden('esManual'
    , $objeto['esManual']
    ,['class'       => 'form-control'
        ,'id'       => 'esManual' 
    ])
!!}
<!-- fin class="card custom-card" -->
