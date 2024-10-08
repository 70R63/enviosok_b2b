@extends('dashboard')
@section('content')

@include('tarifa.dashboard.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Tarifas</label> <span class="d-block tx-12 mb-3 text-muted">A task is accomplished by a set deadline, and must contribute toward work-related objectives.</span>
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
                    
                </div>
                <div>
                    @include('tarifa.dashboard.resumen')
                </div>    
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12 col-xl-4  col-md-4">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <label class="main-content-label mb-4">GRAFICAS</label>
                </div>
                @include('tarifa.dashboard.grafica') 
            </div>
        </div>
    </div>
</div>
<!-- Row end -->


@endsection
