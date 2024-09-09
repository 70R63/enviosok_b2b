<div class="" >
	<span class="tx-18 text-header">Hola {{ Auth::user()->name }}, bienvenido al portal de <strong>{{ Session::get('empresa_nombre') }}</strong></span>

	{!! Form::hidden('empresaIdHeader'
	    , Auth::user()->empresa_id
	    ,['class'       => 'form-control'
	        ,'id'       => 'empresaIdHeader'

	    ])
	!!}


</div>
