@extends('dashboard')
@section('content')

@include('guia.dashboard.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Creacion de Guia</label> <span class="d-block tx-12 mb-0 text-muted">Introduce los datos del remitente y destino</span>
                </div>
            </div>
        </div>
        {!! Form::open([ 'route' => 'guia.store', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'enviosForm' ]) !!}
            <div class="row row-sm">
                @include("guia.crear.seccion1")
                @include("guia.crear.seccion2")
                @include("guia.crear.seccion3")
                @include("guia.crear.botones")
            </div>
        {!! Form::close() !!}
    </div>
</div>
<!-- End Row -->

<!-- END Page -->
<!-- Terminos y Condiciones Modal -->
@include('guia.crear.modals.cotizacion')
@include('guia.crear.modals.enviar')
<!-- End Basic modal -->
@endsection