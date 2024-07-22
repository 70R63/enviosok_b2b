@extends('dashboard')
@section('content')

@include('clientes.crear.header')

<!-- Row -->
<div class="col-xl-12">
    <div class="card custom-card">
        <div class="card-header bg-transparent border-bottom-0">
            <div class="card custom-card">
                <div class="card-header bg-transparent border-bottom-0">
                    <div>
                        <label class="main-content-label mb-2">Creacion de una direccion</label> <span class="d-block tx-12 mb-0 text-muted">Seccion para agregar los destinatarios</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {!! Form::open([ 'route' => 'clientes.store', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'generalForm' ]) !!}
        <div class="row row-sm">
            @include('clientes.forma.principal')
            <div>
                <a href="{{ route('clientes.index') }}" class="btn badge-dark" >Cancelar</a>
                <button type="submit" class="btn btn-primary ml-3" >Enviar</button>
            </div>
        </div>
    {!! Form::close() !!}     
</div>
<!-- End Row -->

<!-- END Page -->
@endsection