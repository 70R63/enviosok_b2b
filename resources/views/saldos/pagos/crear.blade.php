@extends('dashboard')
@section('content')

@include('saldos.pagos.crear.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Creacion de Pagos</label> <span class="d-block tx-12 mb-0 text-muted">Seccion para agregar un pago</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- End Row -->
<!-- Row -->
<div class="row row-sm">
    <div class="col-lg-9 col-md-9">
        <div class="card custom-card">
            <div class="card-body">
                {!! Form::open([ 'route' => 'pagos.store', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'saldosPagosForm' ]) !!}
                    @include("saldos.pagos.forma.campos")
                    @include("saldos.pagos.forma.botonesSubmit")  
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-3">
        <div class="card custom-card">
            <div class="card-body">
            @include("saldos.pagos.crear.grafica")
            </div>
        </div>
    </div>
</div>
<!-- End Row -->
     
@endsection
