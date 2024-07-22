<div class="pd-15">
    <label class="main-content-label mb-0">Detalle de la Guia</label>
</div>
<div class="card-body">
    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text tx-20" id="basic-addon1">
                    GUIA ID: {!! isset($guia->guias_id)  ? $guia->guias_id : "Sin datos actuales" !!}
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text tx-20" id="basic-addon1">
                    TRACKING: {!! $guia->tracking_number  !!}
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text tx-20" id="basic-addon1">
                    CLIENTE: {!! $guia->empresa_nombre  !!}
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text  tx-20" id="basic-addon1">
                    FECHA PICKUP: {!! $guia->pickup_fecha  !!}
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="input-group mb-3">
            <div class="input-group-prepend">
                <span class="input-group-text tx-20" id="basic-addon1">
                    LTD: {!! $guia->ltd_nombre  !!}
                </span>
            </div>
        </div>
    </div>
</div>