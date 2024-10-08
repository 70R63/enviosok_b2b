@extends('dashboard')
@section('content')


@include('clientes.dashboard.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Registro de destinatarios</label>
                        <span class="d-block tx-12 mb-3 text-muted">
                            Podrás dar de alta tantos destinatarios como sean necesarios para llevar a cabo tu operación.
                        </span>
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
                    @include('clientes.dashboard.tabla')
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
                @include('clientes.dashboard.grafica')
            </div>
        </div>
    </div>
</div>
<!-- Row end -->


@endsection
