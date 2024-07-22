@extends('dashboard')
@section('content')

@include('ltd.editar.header')

<!-- Row -->
<div class="row">
    <div class="col-xl-12">
        <div class="card custom-card">
            <div class="card-header bg-transparent border-bottom-0">
                <div>
                    <label class="main-content-label mb-2">Editar PROVEEDOR</label> <span class="d-block tx-12 mb-0 text-muted">Seccion para editar un proveedor de mensajeria</span>
                </div>
            </div>
        </div>
        {!! Form::model($ltd,['route' => ['ltds.update',$ltd], 'method' => 'PUT' , 'class'=>'parsley-style-1', 'id'=>'generalForm' ]) !!}
            <div class="row row-sm">
                <div class="col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header bg-transparent border-bottom-0">
                            @include('ltd.forma.principal')
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('ltds.index') }}" class="btn badge-dark" >Cancelar</a>
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