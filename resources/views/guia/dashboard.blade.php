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
                        <label class="main-content-label mb-2">Mis envíos</label>
                        <span class="d-block tx-12 mb-3 text-muted">
                            Aquí podrás descargar las guías generadas, desde el inicio de tu operación a la fecha, filtrar por fecha, por línea transportista, por fecha y exportar esta información a Excel contribuyendo a la realización de tus indicadores.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('guia.dashboard.tabla')

@include('guia.dashboard.grafica')

@endsection
