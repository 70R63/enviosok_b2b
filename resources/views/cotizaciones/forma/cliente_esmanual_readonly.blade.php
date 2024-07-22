<!-- Campos para el cliente con valor readonly -->
<div class="card custom-card">
    <div class="card-body">
    	<div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
            <label class="main-content-label mb-4">DETALLES DEL DESTINATARIO </label>
        </div>
    	<div class="card-item">
			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NOMBRE
					</span>
				</div>

				{!! Form::text('nombre_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'nombre_d'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CONTACTO
					</span>
				</div>

				{!! Form::text('contacto_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'contacto_d'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">DIRECCIÓN
					</span>
				</div>

				{!! Form::text('direccion_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion_d'
						,'required'	=>	'true'
						
					])
				!!}
			</div>

			<div class="input-group mb-3">

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. EXTERIOR</span>
				</div>

				{!! Form::text('no_ext_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'no_ext_d'
						,'required'	=>	'true'
						
					])
				!!}

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. INTERIOR</span>
				</div>

				{!! Form::text('no_int_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'no_int_d'
						
					])
				!!}
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">C.P.
					</span>
				</div>

				{!! Form::text('cp_d'
					, $objeto['cp_d_manual']
					,['class' 		=> 'form-control buscaCP_d'
						,'id'		=> 'cp_d'
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

				{!! Form::text('direccion2_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion2_d'
						
					])
				!!}

			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">COLONIA
					</span>
				</div>

				{!! Form::select('colonia_d'
					, array()
					,"0"
					,['class' 		=> 'form-control'
						,'placeholder'	=> 'Seleccionar'
						,'required'	=> 'true'
						,'id'		=> 'colonia_d'
						
					]);
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CIUDAD
					</span>
				</div>

				{!! Form::text('ciudad_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'ciudad_d'
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

				{!! Form::text('entidad_federativa_d'
					, null
					,['class' 		=> 'form-control'
						,'id'		=> 'entidad_federativa_d'
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

				{!! Form::text('celular_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'celular_d'
						,'required'	=>	'true'
						
					])
				!!}
			
			<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">TELEFONO
					</span>
				</div>

				{!! Form::text('telefono_d'
					, ''
					,['class' 		=> 'form-control'
						,'id'		=> 'telefono_d'
						
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
