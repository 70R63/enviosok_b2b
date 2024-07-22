@extends('dashboard')
@section('content')

@include('reportes.ventas.index.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Creacion de Reportes de Ventas</label> <span class="d-block tx-12 mb-0 text-muted">Reporte de Ventas</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-3 col-md-3">
        @include('reportes.ventas.index.filtroForma')
    </div>

    <div class="col-lg-9 col-md-9">
        @include('reportes.ventas.index.tabla')
    </div>
</div>
<!-- End Row -->
     
@endsection
