@extends('dashboard')
@section('content')

@include('reportes.repesajes.index.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Creacion de Reportes de Repesaje</label> <span class="d-block tx-12 mb-0 text-muted">Reporte de repesaje</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-3 col-md-3">
        @include('reportes.repesajes.index.filtroForma')
    </div>

    <div class="col-lg-9 col-md-9">
        @include('reportes.repesajes.index.tabla')
    </div>
</div>
<!-- End Row -->
     
@endsection
