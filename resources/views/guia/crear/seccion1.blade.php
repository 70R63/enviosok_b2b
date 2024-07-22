
<div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
    <h4>REMITENTE </h4>
    <div class="card custom-card">
        <div class="card-body">
            <div class="card-item">
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Pais <span class="tx-danger">*</span></span>       
                    </div>
                    
                    {!! Form::select('pais', array(
                        'MEX'   => 'Mexico'
                        )
                        ,'MEX'
                        ,['class'       => 'form-control'
                            ,'placeholder'  => 'Seleccionar'
                            ,'required' => ''
                            ,'name'     => 'pais'
                            ,'id'       => 'pais'
                        ]);
                    !!}
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Nombre <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('nombre', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Nombre Completo'
                        ,'required' => ''

                        ])
                    !!}
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Compañia <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('compania', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Compañia o Razon Social '
                        ,'required' => ''
                        ])
                    !!}
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Calle <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('calle', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Dirección '
                        ,'required' => ''
                        ])
                    !!}
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Número <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('numero', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Numero Exterior o interior'
                        ,'required' => ''
                        ])
                    !!}
                </div>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> CP <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('cp', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Codigo Postal'
                        ,'required' => ''
                        ,'pattern'  => '\d{5}'
                        ])
                    !!}
                </div>

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> Colonia <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('colonia', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Colonia'
                        ,'required' => ''
                        ])
                    !!}
                </div>                          

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> Entididad Federativa <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('entidad_federativa', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Ingresa la entidad federativa'
                        ,'required' => ''
                        ])
                    !!}
                </div>

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> Telefono <span class="tx-danger">*</span></span>
                    </div>
                    {!! Form::text('telefono', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Número Telefonica'
                        ,'required' => ''
                        ,'pattern'  => '\d{10}'
                        ])
                    !!}
                </div>

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> @ 
                        </span>
                    </div>
                    {!! Form::text('email', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'ejemplo@ulalaexpress.com'
                        ,'data-parsley-type' => 'email'
                        ])
                    !!}
                </div>

                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1"> Referencia</span>
                    </div>
                    {!! Form::text('referencia', null,
                        ['class'        => 'form-control'
                        ,'placeholder'  => 'Referencias: mercados, calles, cines, etc.'
                        
                        ])
                    !!}
                </div>
            </div>
        </div>
    </div>
</div>



