<!-- Row -->
{!! Form::open([ 'route' => ['ajustes.show','detalle'], 'method' => 'GET' , 'class'=>'parsley-style-1', 'id'=>'ajustesBuscarIdGuiaForm' ]) !!}

    <div class="input-group mb-3">
        <div class="input-group-prepend">
            <span class="input-group-text" id="basic-addon1">ID GUIA </span>
        </div>
        {!! Form::text('guia_id'
            ,null
            ,['class'       => 'form-control'
                ,'placeholder'  => 'Ingrese el id del portal '
                ,'data-parsley-type'        => 'number'
                ,'id'       => 'guia_id'
                ,'required' => 'true'
            ]);
        !!}
    </div>

    <div class="form-group row justify-content-around">     
        <div>    
            <button type="submit" class="btn btn-primary ml-3" >Buscar Guia</button>
        </div>   
    </div> 
    
   

{!! Form::close() !!}
<!-- End Row -->