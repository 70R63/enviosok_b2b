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
					, $cliente->nombre
					,['class' 		=> 'form-control'
						,'id'		=> 'nombre_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CONTACTO
					</span>
				</div>

				{!! Form::text('contacto_d'
					, $cliente->contacto
					,['class' 		=> 'form-control'
						,'id'		=> 'contacto_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">DIRECCIÓN
					</span>
				</div>

				{!! Form::text('direccion_d'
					, $cliente->direccion
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. EXTERIOR</span>
				</div>

				{!! Form::text('no_ext_d'
					, $cliente->no_ext
					,['class' 		=> 'form-control'
						,'id'		=> 'no_ext_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}

				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">NO. INTERIOR</span>
				</div>

				{!! Form::text('no_int_d'
					, $cliente->no_int
					,['class' 		=> 'form-control'
						,'id'		=> 'no_int_d'
						,'readonly' =>  'true'
					])
				!!}
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">C.P.
					</span>
				</div>

				{!! Form::text('cp_d'
					, $cliente->cp
					,['class' 		=> 'form-control'
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
					, $cliente->direccion2
					,['class' 		=> 'form-control'
						,'id'		=> 'direccion2_d'
						,'readonly' =>  'true'
					])
				!!}

			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">COLONIA
					</span>
				</div>

				{!! Form::text('colonia_d'
					, $cliente->colonia
					,['class' 		=> 'form-control'
						,'id'		=> 'colonia_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			</div>

			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">CIUDAD
					</span>
				</div>

				{!! Form::text('ciudad_d'
					, $cliente->ciudad
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
					</span>
				</div>

				{!! Form::text('entidad_federativa_d'
					, $cliente->entidad_federativa
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
					, $cliente->celular
					,['class' 		=> 'form-control'
						,'id'		=> 'celular_d'
						,'required'	=>	'true'
						,'readonly' =>  'true'
					])
				!!}
			
			<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">TELEFONO
					</span>
				</div>

				{!! Form::text('telefono_d'
					, $cliente->telefono
					,['class' 		=> 'form-control'
						,'id'		=> 'telefono_d'
						,'readonly' =>  'true'
					])
				!!}
			</div>
		</div>
		<!-- fin class="card-item" -->
	</div>
	<!-- fin class="card-body" -->
</div>
<!-- fin class="card custom-card" -->
{!! Form::hidden('cliente_id'
    , $cliente->id
    ,['class'       => 'form-control'
        ,'id'       => 'cliente_id' 
    ])
!!}