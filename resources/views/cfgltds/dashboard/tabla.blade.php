<div class="table-responsive">
	<table id="exportGeneral" class="table table-striped table-bordered text-info" >
		<thead>
			<tr>
				<th>ID</th>
				<th>NOMBRE</th>
				<th>VERSION</th>
				<th>DIRECCION</th>
				<th>LOGO</th>
				<th class='notexport'>ACCIONES</th>
			</tr>
		</thead>
		<tbody>

			@foreach( $tabla  as $objeto)
				<tr>
					<td>{{ $objeto->id }}</td>
					<td>{{ $objeto->nombre }}</td>
					<td>{{ $objeto->version }}</td>
					<td>{{ $objeto->descripcion }}</td>
					<td>
						<img src="{{ asset($objeto->imagen_ruta) }}" alt="Girl in a jacket" width="80" height="60">
					</td>
					<td>
						<a href=" {{ route('mensajerias.edit', $objeto->id) }} " class="text-info tx-20 ">
							<i class="fe fe-edit" alt="Editar"></i>
						</a>
						<!-- include('cfgltds.modals.eliminar')	-->
					</td>
				</tr>
					
			@endforeach

		</tbody>
		<tfoot>
		    <tr>
		      <td colspan="">Los datos son responsabilidad del cliente</td>
		    </tr>
		</tfoot>
	</table>
</div>