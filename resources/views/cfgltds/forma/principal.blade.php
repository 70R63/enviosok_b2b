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
					
					{!! Form::text('nombre', null,
						['class' 		=> 'form-control'
							,'placeholder'	=> 'NOMBRE COMERCIAL'
							,'id'		=> 'nombre'
							,'required'	=>	'true'
						])
					!!}
					   
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">
							VERSION <span class="tx-danger">*</span>
						</span>
					</div>
					{!! Form::text('version'
						, null
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Version del desarrollo sobre el ltd '
							,'id'		=> 'version'
							,'required'	=>	'true'
						])
					!!}
				</div>

				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">DESCRIPCION</span>
					</div>
					{!! Form::text('descripcion'
						,null
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'descripcion  '
							,'id'		=> 'Descripcion'
							,'required'	=>	'true'
						]
					); !!}
				</div>
				
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="basic-addon1">LOGO</span>
					</div>
					{!! Form::text('imagen_ruta'
						,null
						,['class' 		=> 'form-control'
							,'placeholder'	=> 'Logo de la compañia  '
							,'id'		=> 'imagen_ruta'
							,'required'	=>	'true'
						]
					); !!}
				</div>
			</div>
		</div>
	</div>
</div>