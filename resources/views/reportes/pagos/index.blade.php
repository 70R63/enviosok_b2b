@extends('dashboard')
@section('content')

@include('reportes.pagos.index.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Creacion de Reportes de Pagos</label> <span class="d-block tx-12 mb-0 text-muted">Reporte de Pagos</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-3 col-md-3">
        @include('reportes.pagos.index.filtroForma')
    </div>

    <div class="col-lg-9 col-md-9">
        @include('reportes.pagos.index.tabla')
    </div>
</div>
<!-- End Row -->
     
@endsection
