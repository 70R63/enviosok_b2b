@extends('dashboard')
@section('content')

@include('empresas.editar.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Edicion del Cliente</label> <span class="d-block tx-12 mb-0 text-muted">Seccion para crear un Cliente
                    </span>
                </div>
            </div>
        </div>
        {!! Form::model($objeto,['route' => ['empresas.update',$objeto], 'method' => 'PUT' , 'class'=>'parsley-style-1', 'id'=>'generalForm' ]) !!}
            <div class="row row-sm">
                <div class="col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header bg-transparent border-bottom-0">
                            @include('empresas.forma.principal')
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('empresas.index') }}" class="btn badge-dark" >Cancelar</a>
                        <button type="submit" class="btn btn-primary ml-3" >Guardar</button>
                    </div>
                </div>
            </div>
        {!! Form::close() !!}
    </div>
</div>
<!-- End Row -->

<!-- END Page -->
@endsection