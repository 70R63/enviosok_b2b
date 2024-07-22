<div class="pd-15">
    <label class="main-content-label mb-0">Ingrese los datos</label>
</div>

{!! Form::open([ 'route' => ['ajustes.store'], 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'ajustesStoreForm' ]) !!}

    <div class="col-lg-12">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">FACTURA </span>
            </div>
            {!! Form::text('factura_id', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'Numero de Factura o Referencia'
                    ,'required' => ''
                    ,'id'       => 'factura_id'
                ])
            !!}
        </div>
    </div>

    <div class="col-lg-12">
        <div class="input-group mb-3">
            <div class="input-group-text">
                <i class="fe fe-calendar  lh--9 op-6"></i>
            </div>
            <input type="text" class="form-control pull-right datepicker" id="fecha_deposito" name="fecha_deposito" placeholder="Fecha Factura" required>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">IMPORTE </span>
            </div>
            {!! Form::text('importe', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'Ingrese importe de diferencia'
                    ,'required' => ''
                    ,'id'       => 'importe'
                ])
            !!}
        </div>
    </div>

    <div class="col-lg-12">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">COMENTARIOS </span>
            </div>
            {!! Form::text('comentarios', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'Ingrese un comentario'
                   
                    ,'id'       => 'comentarios'
                ])
            !!}
        </div>
    </div>

    {!! Form::hidden('cia'
        , $guia->cia
        ,['class'       => 'form-control'
            ,'id'       => 'cia' 
        ])

    !!}

    {!! Form::hidden('guia_id'
        , $guia->guias_id
        ,['class'       => 'form-control'
            ,'id'       => 'guia_id' 
        ])
    !!}
    
    <div class="form-group row justify-content-around">     
        <div>    
            <a href="{{ route('ajustes.index') }}" id="limpiar" class="btn badge-dark" >Nueva Guia</a>
            <button type="submit" class="btn btn-primary ml-3" >Ajustar</button>
        </div>   
    </div>  
    
{!! Form::close() !!}