@extends('dashboard')
@section('content')


@include('cotizaciones.crear.header')
<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">RESUMEN</label> <span class="d-block tx-12 mb-3 text-muted">A CONTINUACION SE MUESTRA EL RESUMEN DE LA NUEVA GUIA</span>
                    </div>
                    
                </div>
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <label class="main-content-label mb-4">CLIENTE XPERTA: {{$objeto['clienteXperta']}}</label>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

<!--Row-->
{!! Form::open([ 'route' => 'guia.store', 'method' => 'POST' , 'class'=>'parsley-style-1', 'id'=>'generalForm', 'onsubmit' => 'disableButton()' ]) !!}
<div class="row row-sm">
    <div class="col-lg-12 col-xl-4 col-md-4">

        @if($objeto['esManual']==='SI' )
            @include('cotizaciones.forma.remitente_esmanual')

        @else
            @include('cotizaciones.forma.sucursal_readonly')
        @endif 
        
        <div>
            <a href="{{ route('cotizaciones.index') }}" class="btn badge-dark" >Cancelar</a>
            <button type="submit" class="btn btn-primary ml-3" id="btnEnviar">Crear Guia</button>
        </div>    
    </div>
    
    <div class="col-lg-12 col-xl-4 col-md-4">
        @if($objeto['esManual']==='NO' ||  $objeto['esManual']==='RETORNO')
            @include('cotizaciones.forma.cliente_readonly')
        @else
            @include('cotizaciones.forma.cliente_esmanual_readonly')
            
        @endif    
    </div>
    
        
    <div class="col-lg-12 col-xl-4 col-md-4">
        @include('cotizaciones.crear.card_preciofinal')
    </div>
</div>


<!-- Row end -->

{!! Form::close() !!}

@endsection
