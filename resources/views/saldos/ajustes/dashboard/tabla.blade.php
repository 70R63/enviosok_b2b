<div class="table-responsive">
    <table id="exportGeneralNoBuscar" class="table table-striped table-bordered text-nowrap " >
        <thead>
            <tr>
                <th>AJUSTE ID </th>
                <th>FECHA CREACION </th>
                <th>GUIA ID </th>
                <th>USUARIO XPERTA </th>  
                <th>CLIENTE XPERTA </th>  
                <th>FACTURA </th>
                <th>FECHA FACTURA </th>
                <th>IMPORTE </th>
                <th>NOTA DE</th>
                <th style="display:none;">NUMERO TRACKING </th>
                <th style="display:none;">FECHA PICKUP    </th>
                <th style="display:none;">MENSAJERIA</th>
                <th style="display:none;">MENSAJERIA</th>

            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <input rel="2" type="text" class="search" name="guiaId">
                </td>
                <td><input rel="3" type="text" class="search" name="usuario"></td>
                <td><input rel="4" type="text" class="search" name="cliente"></td>
                <td><input rel="5" type="text" class="search" name="factura"></td>
                <td></td>
                <td><input rel=7 type="text" class="search" name="importe"></td>
                <td></td>
                <td style="display:none;"></td>
                <td style="display:none;"></td>
                <td style="display:none;"></td>
                <td style="display:none;"></td>
            </tr>
        </thead>

        @foreach( $tabla  as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['created_at'] }}</td>
                    <td>{{ $row['guia_id'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['nombre'] }}</td>
                    <td>{{ $row['factura_id'] }}</td>
                    <td>{{ $row['fecha_deposito'] }}</td>
                    <td>{{ $row['importe'] }} </td>
                    <td>{{ $row['nota_de'] }} </td>
                    <td style="display:none;" >{{ $row['tracking_number'] }}</td>
                    <td style="display:none;">{{ $row['pickup_fecha'] }}</td>
                    <td style="display:none;">{{ $row['ltd_nombre'] }}</td>
                    <td style="display:none;">{{ $row['comentarios'] }}</td>
                </tr>
                    
            @endforeach
                                
        <tfoot>
            <tr>
                <th>AJUSTE ID </th>
                <th>FECHA CREACION </th>
                <th>GUIA ID </th>
                <th>USUARIO XPERTA </th>  
                <th>CLIENTE XPERTA </th>  
                <th>FACTURA </th>
                <th>FECHA DEPOSITO </th>
                <th>IMPORTE </th>
                <th>NOTA DE</th>
                <th style="display:none;">NUMERO TRACKING </th>
                <th style="display:none;">FECHA PICKUP    </th>
                <th style="display:none;">MENSAJERIA</th>
                <th style="display:none;"></th>
            </tr>
        </tfoot>
    </table>
</div>