<div class="table-responsive">
	<table id="exportGeneral" class="table table-striped table-bordered text-nowrap" >
		<thead>
			<tr>
				<th>ID</th>
				<th>LTD</th>
				<th>ESTADOS</th>
				<th>MUNICIPIOS</th>
				<th>REEXPEDICION</th>
				<th>GARANTIA</th>
				<th>ACCIONES</th>
			</tr>
		</thead>
		<tbody>

			@foreach( $tabla  as $objeto)
				<tr>
					<td>{{ $objeto['id'] }}</td>
					<td>{{ $pluckLtd[$objeto['ltds_id']] }}</td>
					<td>{{ $objeto['estado_num'] }}</td>
					<td>{{ $objeto['municipio_num'] }}</td>
					<td>{{ $objeto['reexpedicion_num'] }}</td>
					<td>{{ $objeto['garantia_num'] }}</td>
					<td>
						<a href=" {{ route('coberturas.show', $objeto['ltds_id']) }} " class="text-sm tx-20 ">
							<i class="fe fe-eye" alt="Mostrar"></i>
						</a>
						<a href="" class="remove-list text-danger tx-20 remove-button" data-toggle="modal" data-target="#modal{{ $objeto['id'] }}" >
	<i class="fa fa-trash" alt="Eliminar"></i>
</a>
						@include('coberturas.modals.eliminar')		
					</td>
				</tr>
					
			@endforeach

		</tbody>
		<tfoot>
		    <tr>
		      <td colspan="7">Los datos son responsabilidad del cliente</td>
		    </tr>
		</tfoot>
		
	</table>
</div>