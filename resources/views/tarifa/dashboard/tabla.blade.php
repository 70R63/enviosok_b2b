<div class="table-responsive">
	<table id="exportGeneral" class="table table-striped table-bordered text-nowrap" >
		<thead>
			<tr>
				<th>ID</th>
				<th>CLIENTE</th>
				<th>MENSAJERIA</th>
				<th>SERVICIO</th>
				<th>COSTO</th>
				<th>KG. INICIAL</th>
				<th>KG. FINAL</th>
				<th>KG. EXTRA</th>
				<th>AREA EXTENDIDA</th>
				<th>FECHA</th>
				<th>ACCIONES</th>
			</tr>
		</thead>
		<tbody>

			@foreach( $tabla  as $objeto)
				<tr>
					<td>{{ $objeto->id }}</td>
					<td>{{ $pluckEmpresa[$objeto->empresa_id] }}</td>
					<td>{{ $pluckLtd[$objeto->ltds_id] }}</td>
					<td>{{ $pluckServicio[$objeto->servicio_id] }}</td>
					<td>$ {{ $objeto->costo }}</td>
					<td>{{ $objeto->kg_ini }}</td>
					<td>{{ $objeto->kg_fin }}</td>
					<td>$ {{ $objeto->kg_extra }}</td>
					<td>$ {{ $objeto->extendida }}</td>
					<td>{{ $objeto->fecha }}</td>
					<td>
						<a href=" {{ route('tarifas.edit', $objeto->id) }} " class="text-info tx-20 ">
							<i class="fe fe-edit" alt="Editar"></i>
						</a>
						<a href="" class="remove-list text-danger tx-20 remove-button" data-toggle="modal" data-target="#modal{{ $objeto['id'] }}" >
	<i class="fa fa-trash" alt="Eliminar"></i>
</a>
						@include('tarifa.modals.eliminar')		
					</td>
				</tr>
					
			@endforeach

		</tbody>
		<tfoot>
		    <tr>
		      <td colspan="8">Los datos son responsabilidad del cliente</td>
		    </tr>
		</tfoot>
		
	</table>
</div>