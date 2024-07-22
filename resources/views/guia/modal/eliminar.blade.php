<div class="modal fade" id="modalEliminarGuia" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
	    <div class="modal-content">
	    	<div class="modal-header">
	            <h5 class="modal-title" id="exampleModalLabel">Eliminar Registro <span class="idGuia"></span></h5>
	            <button class="close" type="button" data-dismiss="modal" aria-label="Close">
	            <span aria-hidden="true">×</span>
	            </button>
	         </div>

	    	<div class="modal-body">
	        	<p class="bigger-50 bolder center grey">
					<i class="ace-icon fa fa-hand-o-right blue bigger-120"></i>
					Seguro que quieres eliminar el ID '<span class="idGuia"></span>', del cliente '<span id="empresaNombre"></span>' ?  	
				</p>
				<p class="bigger-50 bolder center grey">
					<i class="ace-icon fa fa-hand-o-right blue bigger-120"></i>
					El saldo  a recuperar es '<span id="precio"></span>' ?  	
				</p>
	      	</div>
		     <div class="modal-footer">
		      	<button class="btn btn-primary" type="button" data-dismiss="modal">Cancelar</button>
		      	{!! Form::open([ 'route' => ['guia.destroy',1 ], 'metdod' => 'PUT' ]) !!}

		      		@csrf
		      		{{method_field('DELETE')}}
		      		{!! Form::hidden('idGuiaForm'
					    , null
					    ,['class'       => 'form-control'
					        ,'id'       => 'idGuiaForm'
					        
					    ])
					!!}
					{!! Form::hidden('ciaForm'
					    , null
					    ,['class'       => 'form-control'
					        ,'id'       => 'ciaForm'
					        
					    ])
					!!}
					{!! Form::hidden('precioForm'
					    , null
					    ,['class'       => 'form-control'
					        ,'id'       => 'precioForm'
					        
					    ])
					!!}

					<a class="btn badge-dark" onclick="$(this).closest('form').submit();">Eliminar</a>
					
				{!! Form::close() !!}
		    </div> <!-- modal-footer -->
	    </div> <!-- modal-content -->
  	</div> <!-- modal-dialog -->
</div> <!--modal fad -->