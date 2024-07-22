<div class="row row-sm">
    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">CLIENTE</span>
            </div>
            {!! Form::select('empresa_id'
                , $pluckEmpresa
                ,null
                ,['class'       => 'form-control select2'
                    ,'placeholder'  => 'TODOS'
                    ,'id'       => 'empresa_id'
                    ,'required' => ''
                    
                ]);
            !!}
        </div>
    </div>
    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">TIPO DE PAGO</span>
            </div>
            {!! Form::select('tipo_pago_id'
                , $pluckTipoPagos
                ,null
                ,['class'       => 'form-control select2'
                    ,'placeholder'  => 'TODOS'
                    ,'id'       => 'tipo_pago_id'
                    ,'required' => ''
                    
                ]);
            !!}
        </div>
    </div>
</div>

<div class="row row-sm">
    
    <div class="col-lg-6 col-md-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">BANCO</span>
            </div>
            {!! Form::select('banco_id'
                , $pluckBancos
                ,null
                ,['class'       => 'form-control select2'
                    ,'placeholder'  => 'TODOS'
                    ,'id'       => 'banco_id'
                    ,'required' => ''
                    
                ]);
            !!}
        </div>
    </div>

    <div class="col-lg-6 col-md-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">REFERENCIA</span>
            </div>
            {!! Form::text('referencia', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'referencia'
                    ,'required' => ''
                    ,'id'       => 'referencia'
                ])
            !!}
        </div>
    </div>
</div>

<div class="row row-sm">
    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">Importe de Deposito</span>
            </div>
            {!! Form::text('importe', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'Importe de Deposito'
                    ,'required' => ''
                    ,'id'   => 'importe'
                ])
            !!}
            
            
        </div>
    </div>
</div>

<div class="row row-sm">
    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-text">
                <i class="fe fe-calendar  lh--9 op-6"></i>
            </div>
            <input type="text" class="form-control pull-right datepicker" id="fecha_deposito" name="fecha_deposito" placeholder="Fecha Deposito" required>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">Hora Deposito</span>
            </div>
            {!! Form::text('hora_deposito', null,
                ['class'        => 'form-control'
                    ,'placeholder'  => 'Hora de Deposito hh::mm'
                    ,'required' => ''
                    ,'id'   => 'hora_deposito'
                    ,'pattern'  => '([01]?[0-9]|2[0-3]):[0-5][0-9]'
                ])
            !!}
        </div>
    </div>
</div>


