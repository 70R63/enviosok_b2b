<div class="table-responsive">
    <table id="exportGeneral" class="table table-striped table-bordered text-nowrap " >
        <thead>
            <tr>
                <th>EXTERNA ID </th>
                <th>FECHA CREACION </th>
                <th>USUARIO XPERTA </th>  
                <th>NO DE GUIAS </th>
                <th>IMPORTE TOTAL</th>
                <th>ARCHIVO</th>
                
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </thead>

        @foreach( $tabla  as $row)
            <tr>
                <td>{{ $row['id'] }}</td>
                <td>{{ $row['created_at'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['no_guias'] }}</td>
                <td>{{ $row['importe_total'] }}</td>
                <td>{{ $row['file_nombre'] }}</td>
            </tr>
                
        @endforeach

                                
        <tfoot>
            <tr>
                <th>EXTERNA ID </th>
                <th>FECHA CREACION </th>
                <th>USUARIO XPERTA </th>  
                <th>NO DE GUIAS </th>
                <th>IMPORTE TOTAL</th>
                <th>ARCHIVO</th>
            </tr>
        </tfoot>
    </table>
</div>