<div class="table-responsive">
	<table id="exportGeneral" class="table table-striped table-bordered text-nowrap" >
		<thead>
			<tr>
				<th>ID</th>
				<th>CP</th>
				<th>ESTADOS</th>
				<th>MUNICIPIOS</th>
				<th>REEXPEDICION</th>
				<th>GARANTIA</th>
			</tr>
		</thead>
		<tbody>

			@foreach( $tabla  as $objeto)
					<tr>
						<td>{{ $objeto['id'] }}</td>
						<td>{{ $objeto['cp'] }}</td>
						<td>{{ $objeto['estado'] }}</td>
						<td>{{ $objeto['municipio'] }}</td>
						<td>{{ $objeto['reexpedicion'] }}</td>
						<td>{{ $objeto['garantia'] }}</td>
					</tr>
			@endforeach

		</tbody>
		<tfoot>
		    <tr>
		      <td colspan="6">Los datos son responsabilidad del cliente</td>
		    </tr>
		</tfoot>
		
	</table>
</div>