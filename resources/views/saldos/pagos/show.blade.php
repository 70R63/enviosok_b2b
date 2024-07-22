@extends('dashboard')
@section('content')

@include('saldos.pagos.show.header')

<!-- Row -->
<div class="row">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Muestra el historial de pagos realizados</label> <span class="d-block tx-12 mb-0 text-muted">Reporte de Pagos</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
            @include("saldos.pagos.show.tabla")
            </div>
        </div>
    </div>

</div>
<!-- End Row -->
     
@endsection
