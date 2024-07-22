@extends('dashboard')
@section('content')

@include('empresas.crear.header')

<!-- Row -->
<div class="col-xl-12">
    <div class="card custom-card">
        <div class="card-header bg-transparent border-bottom-0">
            <div class="card custom-card">
                <div class="card-header bg-transparent border-bottom-0">
                    <div>
                        <label class="main-content-label mb-2">Creacion de un Cliente</label> <span class="d-block tx-12 mb-0 text-muted">Sección para agregar los Clientes </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {!! Form::open([ 'route' => 'empresas.store', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'generalForm' ]) !!}
        <div class="row row-sm">
            @include('empresas.forma.principal')
            <div>
                <a href="{{ route('empresas.index') }}" class="btn badge-dark" >Cancelar</a>
                <button type="submit" class="btn btn-primary ml-3" >Enviar</button>
            </div>
        </div>
    {!! Form::close() !!}     
</div>
<!-- End Row -->

<!-- END Page -->
@endsection