
{!! Form::open([ 'route' => 'guiaretorno.create', 'method' => 'GET' , 'class'=>'parsley-style-1', 'id'=>'generalForm' ]) !!}
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="modalRetorno" aria-hidden="true">
	<div class="modal-dialog" role="document">
	    <div class="modal-content">
	    	<div class="modal-header">
	            <h5 class="modal-title" id="exampleModalLabel"></h5>
	            <button class="close" type="button" data-dismiss="modal" aria-label="Close">
	            	<span aria-hidden="true">×</span>
	            </button>
	        </div>

	    	<div class="modal-body">
                <div class="card custom-card pricingTable2"> 
                    <div class="pricing-plans  bg-primary">
                        <h2>Retorno de guia - ID <span id="spanID"> </span> </h2>
                    </div>
                    <div class="pricingContent2">
                        <ul>
                            <h4>
                                <li><b>Remitente : <span id="spanRemitente"> </span></b>   
                                </li>
                                <li>
                                	<b>Destinatorio : <span id="spanDestinatario"> </span> </b> 
                                </li>
                            </h4>

                            <h4>
                                <li><b>Nuevo Remitente: <span id="spanNuevoRemitente"> </span></b> 
                                </li>
                                <li>
                                	<b>Nuevo Destinatorio: <span id="spanNuevoDestinatario"> </span></b> 
                                </li>
                            </h4>
                        </ul>
                    </div>
                    <div class="pricing-plans  bg-primary">
                        Para continuar con la creación, presionar el boton continuar
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary ml-3" >Continuar</button>
                    <a class="btn badge-dark" data-dismiss="modal" type="button">Cerrar</a>
                </div>
            </div>
            <!-- FIN class="modal-body" -->
	    </div> <!-- modal-content -->
  	</div> <!-- modal-dialog -->
</div> <!--modal fad -->

<!-- Campos ocultos -->

{!! Form::hidden('guia_id'
    , null
    ,['class'       => 'form-control'
        ,'id'       => 'guia_id'
    ])
!!}

{!! Form::close() !!} 