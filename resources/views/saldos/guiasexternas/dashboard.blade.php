@extends('dashboard')
@section('content')

@include('saldos.guiasexternas.dashboard.header')

<!-- Row -->
<div class="row">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Ajustes por Guias Externas</label> <span class="d-block tx-12 mb-0 text-muted">Ajustes de Pagos</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-xxl-2 col-xl-12 col-lg-12 col-md-12">
        
        {!! Form::open([ 'route' => ['externas.store'], 'method' =>    'POST'     , 'class'=>'parsley-style-1', 'id'=>'ajustesStoreForm' 
            ,'enctype'=>'multipart/form-data'
        ]) 
        !!}
            <div class="card custom-card">
                <div class="card-body ">
                    @include("saldos.guiasexternas.dashboard.campos")       
                </div>
                <div class="form-group row justify-content-around">     
                    <div>    
                        
                        <button type="submit" class="btn btn-primary ml-3" >Cargar Archivo</button>
                    </div>   
                </div> 
            </div>
        {!! Form::close() !!}
    </div>
    <div class="col-xxl-10 col-xl-12 col-lg-12 col-md-12">
         <div class="card custom-card">
            <div class="card-body ">
                @include("saldos.guiasexternas.dashboard.tabla")
            </div>
        </div>
    </div>
  
    
</div>
<!-- End Row -->
     
@endsection
