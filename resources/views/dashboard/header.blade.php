<div class="" >
	<b class="tx-18 text-white">HOLA {{ Auth::user()->name }}, BIENVENIDO AL PORTAL DE '{{ Session::get('empresa_nombre') }}'</b> 

	{!! Form::hidden('empresaIdHeader'
	    , Auth::user()->empresa_id
	    ,['class'       => 'form-control'
	        ,'id'       => 'empresaIdHeader'
	        
	    ])
	!!}

		
</div>
