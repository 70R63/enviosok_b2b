	@extends('dashboard')
	@section('content')
   @include('roles.dashboard.header')
   <!--Row-->
<div class="row row-sm">
    <div class="col-lg-12">
        <div class="card custom-card mg-b-20">
            <div class="card-body">
                <div class="card-header border-bottom-0 pt-0 pl-0 pr-0 d-flex">
                    <div>
                        <label class="main-content-label mb-2">Tasks q</label> <span class="d-block tx-12 mb-3 text-muted">A task is accomplished by a set deadline, and must contribute toward work-related objectives.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Row end -->

<div class="row row-sm">
   <div class="col-sm-12 col-md-12">
      <div class="card custom-card ">
         <div class="card-header border-bottom-0 custom-card-header">
            <div class="col-sm-12 col-md-10">
               <h6 class="main-content-label mb-0">Tabla de roles del sistema</h6>
            </div>          
         </div>
         <div class="card-body">
            <div class="border">
            </div> 
            <div class="table-responsive roleslist-table">
               <table id="exportGeneral"  class="table table-striped table-bordered text-nowrap" >
                  <thead>
                     <tr>
                        <!-- <th>ID</th> -->
                        <th>ROL</th>
                        <th>SLUG</th>
                        <th>PERMISOS</th>
                        <th>HERRAMIENTAS</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($roles as $roles)
                     <tr>
                        <!-- <td>{{$roles['id']}}</td> -->
                        <td>{{$roles['name']}}</td>
                        <td>{{$roles['slug']}}</td>
                        <td>
                           @if ($roles->permisos != null)                                  
                           		@foreach ($roles->permisos as $permiso)
                           			<span class="badge badge-pill badge-success-light">
                           				{{ $permiso->name }}                                    
                           			</span>
                           		@endforeach                           
                           @endif
                        </td>
                        <td>
                           <a href="{{route('roles.edit',$roles['id']) }} " class="btn btn-sm btn-info"  ><i class="fe fe-edit-2"></i></a>	
                           <a href="#" data-toggle="modal" data-target="#deleteModalRole" data-rolid="{{ $roles['id'] }}" class="btn btn-sm btn-danger" ><i class="fe fe-trash"></i></a>							
                        </td>
                     </tr>
                     @endforeach
                  </tbody>
                  <tfoot>
                     <tr>
                        <td colspan="4"></td>
                     </tr>
                  </tfoot>
               </table>
            </div>
            <!-- delete Modal-->
            <div class="modal fade" id="deleteModalRole" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
               <div class="modal-dialog" role="document">
                  <div class="modal-content">
                     <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">¿Segur@ de borrar el Rol?</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                        </button>
                     </div>
                     <div class="modal-body">Confirma con el botón "eliminar".</div>
                     <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
                        <form method="POST" action="">
                           @method('DELETE')
                           @csrf
                           {{-- <input type="text" id="rol_id" name="rol_id" value=""> --}}
                           <a class="btn btn-success" onclick="$(this).closest('form').submit();">Eliminar</a>
                        </form>
                     </div>
                  </div>
               </div>
            </div>
            <!-- delete Modal-->
         </div>
      </div>
   </div>
</div>

	@section('js_rol_page')

	{{--	<script src="/vendor/chart.js/Chart.min.js"></script>
		<script src="/js/admin/demo/chart-area-demo.js"></script> --}}

		    <script>
		        $('#deleteModalRole').on('show.bs.modal', function (event) {
		            var button = $(event.relatedTarget) 
		            var rol_id = button.data('rolid') 
		            
		            var modal = $(this)
		            // modal.find('.modal-footer #user_id').val(user_id)
		            modal.find('form').attr('action','/roles/' + rol_id);
		        })
		    </script>

	@endsection   

	@endsection  
