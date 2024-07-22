<div class="modal" id="modalEnviosCotizacion">
	<div class="modal-dialog" role="document">
		<div class="modal-content modal-content-demo">
			<div class="modal-header">
				<h6 class="modal-title">Cotizaci&oacute;n</h6><button aria-label="Close" class="close" data-dismiss="modal" type="button"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				{!! Form::open([ 'route' => 'guia.index', 'method' => 'GET' , 'class'=>'parsley-style-1', 'id'=>'cotizacionesForm' ]) !!}
				<div class="row row-sm">
					<div class="col-sm-12 col-md-6">
						<div class="card custom-card">
							<div class="card-header border-bottom-0 custom-card-header">
								<h6 class="main-content-label mb-0">Origen</h6>
							</div>
							<div class="card-body mb-0">
								<div class="border"> 
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text" id="basic-addon1"> Pais <span class="tx-danger">*</span></span>
										</div>
										{!! Form::select('pais', array(
										    'MEX' 	=> 'Mexico'
										    )
											,'MEX'
											,['class' 		=> 'form-control'
												,'placeholder'	=> 'Seleccionar'
												,'required'	=> ''
												,'name'		=> 'pais'
												,'id'		=> 'pais'
											]);
										!!}
									</div>
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text" id="basic-addon1"> CP <span class="tx-danger">*</span></span>
										</div>
										{!! Form::text('cp', null,
											['class' 		=> 'form-control'
											,'placeholder'	=> 'Codigo Postal'
											,'required'	=> ''
											,'pattern'	=> '\d{5}'
											])
										!!}
										
									</div>
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text" id="basic-addon1"> Peso <span class="tx-danger">*</span></span>
										</div>
										{!! Form::text('peso', null,
											['class' 		=> 'form-control'
											,'placeholder'	=> 'Peso aproximado'
											,'required'	=> ''
											])
										!!}
										
									</div>

										
																	
								</div>
							</div>
						</div>
					</div>
					<div class="col-sm-12 col-md-6">
						<div class="card custom-card">
							<div class="card-header border-bottom-0 custom-card-header">
								<h6 class="main-content-label mb-0">Destino</h6>
							</div>
							<div class="card-body">
								<div class="border"> 
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text" id="basic-addon1"> Pais <span class="tx-danger">*</span></span>
										</div>
										{!! Form::select('pais_D', array(
										    'MEX' 	=> 'Mexico'
										    )
											,'MEX'
											,['class' 		=> 'form-control'
												,'placeholder'	=> 'Seleccionar'
												,'required'	=> ''
												,'name'		=> 'pais_d'
												,'id'		=> 'pais_d'
											]);
										!!}
									</div>
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<span class="input-group-text" id="basic-addon1"> CP <span class="tx-danger">*</span></span>
										</div>
										{!! Form::text('cp_d', null,
											['class' 		=> 'form-control'
											,'placeholder'	=> 'Codigo Postal'
											,'required'	=> ''
											,'pattern'	=> '\d{5}'
											])
										!!}
										
									</div>
					
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="form-group row justify-content-around">	
					<input class="btn btn-success" type="submit" value="Cotizar">
				</div>
				{!! Form::close() !!}
			</div>
		</div>
	</div>
</div>