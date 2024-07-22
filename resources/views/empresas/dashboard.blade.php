@extends('dashboard')
@section('content')

@include('empresas.dashboard.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Clientes</label> <span class="d-block tx-12 mb-3 text-muted">Se muestra un resumen general de los clientes.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12 col-xl-1  col-md-12">
    </div>
    
    <div class="col-lg-12 col-xl-12  col-md-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    
                </div>
                <div>
                    @include('empresas.dashboard.tabla')
                </div>    
            </div>
        </div>
    </div>
</div>
<!-- Row end -->
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12 col-xl-3  col-md-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <label class="main-content-label mb-4">GRAFICAS</label>
                </div>
                @include('empresas.dashboard.grafica') 
            </div>
        </div>
    </div>
</div>
<!-- Row end -->
@endsection