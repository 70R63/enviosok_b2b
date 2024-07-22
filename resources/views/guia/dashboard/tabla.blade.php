<!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="guiasTablaAjax" class="table table-striped table-bordered text-nowrap " >
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th class='notexport'>GUIA /<br>RETORNO</th>
                                <th>MENSAJERIA</th>
                                <th>SERVICIO</th>
                                <th>USUARIO</th>
                                <th>CLIENTE XPERTA</th>
                                <th>REMITENTE <br>(CONTACTO)</th>
                                <th>DESTINATATIO <br>(CONTACTO)</th>
                                <th>CP ORIGEN</th>
                                <th>CIUDAD ORIGEN</th>
                                <th>CP DESTINO</th>
                                <th>CIUDAD DESTINO</th>
                                <th>CREACION</th>
                                <th>CANAL</th>
                                <th>TRACKING</th>
                                <th>PRECIO</th>
                                <th>PIEZAS</th>
                                <th>PESO KG</th>
                                <th>ALTO</th>
                                <th>ANCHO</th>
                                <th>LARGO</th>
                                <th>VALOR DEL ENVIO</th>
                                <th>COSTO SEGURO</th>
                                <th>APLICA A.E.</th>
                                <th>ZONA</th>
                            </tr>
                        </thead>
                                                
                        <tfoot>
                            <tr>
                              <td colspan="25">Los datos son responsalidad del usuario</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('guia.dashboard.retorno')
@include('guia.modal.eliminar')
<!-- End Row-->
