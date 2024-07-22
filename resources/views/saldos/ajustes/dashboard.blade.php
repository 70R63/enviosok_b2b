@extends('dashboard')
@section('content')

@include('saldos.ajustes.dashboard.header')

<!-- Row -->
<div class="row">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Ajustes de Pago por id de guia</label> <span class="d-block tx-12 mb-0 text-muted">Ajustes de Pagos</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-xxl-4 col-xl-12 col-lg-12 col-md-12">
        @if ($iniciarBusqueda)
        <div class="card custom-card">
            <div class="card-body ">
                @include('saldos.ajustes.dashboard.buscarIdGuia')
            </div>
        </div>
        @endif
        @if (!$iniciarBusqueda)
        <div class="card custom-card">
            <div class="card custom-card">
                @include('saldos.ajustes.dashboard.detalleGuia')    
            </div>
        </div>
        @endif
    </div>
    <div class="col-xxl-4 col-xl-12 col-lg-12 col-md-12">

        @if (!$iniciarBusqueda)
        <div class="card custom-card">
            @include('saldos.ajustes.dashboard.campos')
            
        </div>
        @endif
    </div>
  
    
</div>
<div class="row row-sm">
    <div class="col-xxl-12 col-xl-12 col-lg-12 col-md-12">
        <div class="card custom-card">
            <div class="card-body p-3">
                @include('saldos.ajustes.dashboard.tabla')
            </div>
        </div>
    </div>
</div>
<!-- End Row -->
     
@endsection
