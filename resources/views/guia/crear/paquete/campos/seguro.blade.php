<div class="input-group mb-3">
	<div class="input-group-prepend">
		<span class="input-group-text" id="basic-addon1">Seguro </span>
	</div>
	<label class="custom-switch">
		<input type="checkbox" name="custom-switch-checkbox" class="custom-switch-input" id="checkSeguro">
		<span class="custom-switch-indicator"></span>
		
	</label>
	<!-- Inicio Class Seguro -->
	<div class="seguro" style="display: none;" >
	
		{!! Form::text('valor_envio', null,
			['class' 		=> 'form-control'
			,'id'			=> 'valor_envio'
			,'placeholder'	=> 'Valor de Envio Sin IVA'
			
			])
		!!}

	</div>
	<!-- Fin Class Seguro -->
</div>
