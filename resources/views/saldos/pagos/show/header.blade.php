<div class="page-header">
    <div>
        <h2 class="main-content-title tx-24 mg-b-5">DASHBOARD</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="">Saldos</a></li>
            <li class="breadcrumb-item " aria-current="page">
                <a href="{{ route('pagos.index') }}">Pagos </a>
            </li>
            <li class="breadcrumb-item active">Detalle</li>
        </ol>
    </div>

    <div class="d-flex">
        <div class="justify-content-center">
            <a type="button" class="btn btn-primary my-2 btn-icon-text bg-primary text-white rounded-5 px-3" href="{{ route('pagos.index') }}" >
                <i class="fa fa-chevron-left"> Regresar</i>
            </a>
        </div>
    </div>

    <div class="d-flex">
        <div class="justify-content-center">
            <a type="button" class="btn btn-primary my-2 btn-icon-text bg-primary text-white rounded-5 px-3" href="{{ route('finanzas.pasarela.index') }}" >
                <i class="fas fa-user-cog"> Nuevo Pago</i>
            </a>
        </div>
    </div>
</div>
