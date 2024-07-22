<div class="table-responsive">
	<table id="exportGeneral" class="table table-striped table-bordered text-nowrap" >
		<thead>
			<tr>
				<th>EMPRESA_ID</th>
				<th>CLIENTE</th>
				<th>MENSAJERIA</th>
				<th>SERVICIO</th>
				
				<th>COSTO MIN</th>
				<th>COSTO MAX</th>
				
			</tr>
		</thead>
		<tbody>

			@foreach( $tabla  as $objeto)
				<tr>
					<td>{{ $objeto->empresa_id }}</td>
					<td>
						<a href="{{route('tarifas.show', $objeto->empresa_id)}}" class="text-dark tx-16 ">
							
							{{ $pluckEmpresa[$objeto->empresa_id] }}
						</a>
						
					</td>
					<td>{{ $pluckLtd[$objeto->ltds_id] }}</td>
					<td>{{ $pluckServicio[$objeto->servicio_id] }}</td>
					<td>{{$objeto->costo_min}}</td>
					<td>{{$objeto->costo_max}}</td>
					
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