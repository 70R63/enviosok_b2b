<!-- Row -->
{!! Form::open([ 'route' => 'api.reportes.pagos.creacion', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'reportePagosForm' ]) !!}
    <div class="card custom-card">
        <div class="card-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">CLIENTE</span>
                        </div>
                        {!! Form::select('empresa_id'
                            , array()
                            ,null
                            ,['class'       => 'form-control select2'
                                ,'placeholder'  => 'TODOS'
                                ,'id'       => 'empresa_id'
                                
                            ]);
                        !!}
                    </div>
                </div>
            </div>

            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">BANCOS</span>
                        </div>
                        {!! Form::select('banco_id'
                            , $bancos
                            ,0
                            ,['class'       => 'form-control select2'
                                ,'placeholder'  => 'Todos'
                                ,'id'       => 'banco_id'
                                
                            ]);
                        !!}
                    </div>
                </div><!-- col-4 -->
            </div>

            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="input-group mb-3">
                        <div class="input-group-text">
                            <i class="fe fe-calendar  lh--9 op-6"></i>
                        </div>
                        <input type="text" class="form-control pull-right datepicker" id="fecha_ini" name="fecha_ini" placeholder="Fecha Inicial">
                    </div>
                </div>
            </div>

            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="input-group mb-3">
                        <div class="input-group-text">
                            <i class="fe fe-calendar  lh--9 op-6"></i>
                        </div>
                        <input type="text" class="form-control pull-right datepicker" id="fecha_fin" name="fecha_fin" placeholder="Fecha Final">
                    </div>
                </div>
            </div>

            
        </div>
        <!-- fin car-body -->
    </div>
    <div class="col-lg-12">
    <div class="form-group row justify-content-around">     
        <div>
            <a id="generarReportePagos" class="btn btn-primary" >Generar</a>    
            <a id="limpiar" class="btn badge-dark" >Limpiar</a>
            
        </div>   
    </div>  
</div>  


{!! Form::close() !!}
<!-- End Row -->