<!-- Basic modal -->
<div class="modal" id="modaldemo1">
	<div class="modal-dialog" role="document">
		<div class="modal-content modal-content-demo">

{!! Form::open([ 'route' => 'coberturas.store'
, 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'generalForm', 'enctype' => 'multipart/form-data' ]) !!}

		<div class="modal-header">
			<h6 class="modal-title">Carga Masiva de Coberturas</h6>
			<button aria-label="Close" class="close" data-dismiss="modal" type="button">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="modal-body">
			<h6></h6>
			<div class="input-group mb-3">
				<div class="input-group-prepend">
					<span class="input-group-text" id="basic-addon1">LTD <span class="tx-danger">*</span></span>
				</div>
			
				{!! Form::select('ltd_id', $pluckLtd
					,null
					,['class' 		=> 'form-control'
						,'placeholder'	=> 'Seleccionar'
						,'required'	=> ''
						,'id'		=> 'ltd_id'
					]);
				!!}
			</div>
		
			<div class="form-group">
				<label for="files">Cargar el archivo en formato CSV:</label>
				<input type="file" id="coberturaCSV" name="coberturaCSV"  class="form-control" accept=".csv" required />
			</div>
		</div>
		<div class="modal-footer">
			<button class="btn ripple btn-primary" type="submit">Enviar</button>
		</div>
{!! Form::close() !!}
		</div>
	</div>
</div>
<!-- End Basic modal -->