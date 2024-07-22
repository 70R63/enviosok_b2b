<div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
	<div class="card custom-card">
		<div class="card-body">
			<div class="card-item">
				<div class="card-item-icon ">
					<a href="{{ route('ltds.edit', $row->id) }}" class="text-info tx-20" data-abc="true">
						<i class="fe fe-edit"></i>
					</a>
					@include('ltd.modals.eliminar')	
				</div>
				<div class="card-item-title mb-2">
					<label class="main-content-label tx-20 font-weight-bold mb-1">{{ $row->nombre}}</label>
					<span class="d-block tx-12 mb-0 text-muted">{{ $row->responsable_legal}}</span>
					<span class="d-block tx-12 mb-0 text-muted">{{ $row->email}}</span>
				</div>
				<div class="card-item-body">
					<div class="card-item-stat">
						<small><b class="text-success">{{ $row->descuento}}%</b> descuento</small>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>


