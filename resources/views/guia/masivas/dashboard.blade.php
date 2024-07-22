@extends('dashboard')
@section('content')

@include('guia.dashboard.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Carga Masiva de Guias</label> <span class="d-block tx-12 mb-3 text-muted">Dashboard para cargar de forma masiva guias basada en en un archivo de CSV.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row -->
<div class="row row-sm">
    <div class="col-xxl-2 col-xl-12 col-lg-12 col-md-12">
        
        {!! Form::open([ 'route' => ['guias.masivas.store'], 'method' =>    'POST'     , 'class'=>'parsley-style-1', 'id'=>'ajustesStoreForm' 
            ,'enctype'=>'multipart/form-data'
        ]) 
        !!}
            <div class="card custom-card">
                <div class="card-body ">
                    @include("guia.masivas.dashboard.campos")       
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
                @include("guia.masivas.dashboard.tabla")
            </div>
        </div>
    </div>
  
    
</div>
<!-- End Row -->

     
@endsection
