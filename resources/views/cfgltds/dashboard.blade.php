@extends('dashboard')
@section('content')

@include('cfgltds.dashboard.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Dashboard </label> <span class="d-block tx-12 mb-3 text-muted">En esta pantalla se realia un resumen de los proveedores (LTD) que estan con funcinalidad sobre el sistema.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12 col-xl-12  col-md-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <label class="main-content-label mb-4">Servicios inicializados</label>
                </div>
                <div>
                    @include('cfgltds.dashboard.tabla')
                </div>    
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

@endsection
