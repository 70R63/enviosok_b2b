@extends('dashboard') 
@section('content')
   <div class="row row-sm">
      <div class="col-sm-12 col-md-12">
         <div class="card custom-card ">
            <div class="card-header border-bottom-0 custom-card-header">
               <div class="col-sm-12 col-md-10">
                  <h3 class="main-content-label mb-0">Edición de usuarios</h3>
               </div>
            </div>
            <div class="card-body">
               <form method="POST" action="/users/{{ $users->id }}" enctype="multipart/form-data" id="editarUsuario">
                  @method('PATCH')
                  @csrf()
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span for="usuario" class="input-group-text" id="basic-addon1">Usuario<span class="tx-danger">*</span></span>                  
                     </div>
                     <input type="text" name="name" class="form-control" id="name" placeholder="Name..." value="{{ $users->name }}" required>
                  </div>
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Email<span class="tx-danger">*</span></span> 
                     </div>
                     <input type="email" name="email" class="form-control" id="email" placeholder="Email..." value="{{ $users->email }}">
                  </div>
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Nueva contraseña</span>                   
                     </div>
                     <input type="password" name="password" class="form-control" id="password" placeholder="Password..." minlength="8">
                  </div>
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Confirmar contraseña</span> 
                     </div>
                     <input type="password" name="password_confirmation" class="form-control" placeholder="Password..." id="password_confirmation">
                  </div>
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Selecionar rol<span class="tx-danger">*</span></span>  
                     </div>
                     <select class="form-control" name="role" id="role">
                        <option value="">Seleccionar rol...</option>
                        @foreach ($roles as $role)
                           <option data-role-id="{{$role->id}}" data-role-slug="{{$role->slug}}" value="{{$role->id}}" {{ $users->roles->isEmpty() || $role->name != $userRole->name ? "" : "selected"}}>{{$role->name}}</option>                
                        @endforeach
                     </select>
                  </div>
                  <div class="input-group mb-3">
                     <div  class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Permisos<span class="tx-danger">*</span></span>  
                  <div id="permissions_box" >
                     <div id="permissions_ckeckbox_list">                    
                     </div>
                  </div>
                  @if($users->permisos->isNotEmpty())
                      @if($rolPermisos != null)
                      <div id="user_permissions_box" >
                         <div id="user_permissions_ckeckbox_list">
                            @foreach ($rolPermisos as $permiso)
                                 @if(in_array($permiso->id, $usersPermisos->pluck('id')->toArray()))
                                    <input  class="custom-control-input" type="checkbox" checked="" name="permisos[]"  id="{{$permiso->id}}" value="{{$permiso->id}}">                                                 
                                   <span class="tag tag-primary mt-1 mb-1">{{$permiso->slug}}</span>
                                 @endif
                            @endforeach
                         </div>
                      </div>
                      @endif
                  @endif  </div>
               </div>
                  <div class="form-group pt-2">
                     <input class="btn btn-success" type="submit" value="Submit">
                     <a href="{{ url()->previous() }}" class="btn btn-info">Volver</a>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </div>


   @section('js_user_page')
       <script>
           $(document).ready(function(){
               var permissions_box = $('#permissions_box');
               var permissions_ckeckbox_list = $('#permissions_ckeckbox_list');
               var user_permissions_box = $('#user_permissions_box');
               var user_permissions_ckeckbox_list = $('#user_permissions_ckeckbox_list');
              // permissions_box.hide(); // hide all boxes

               $('#role').on('change', function() {
                   var role = $(this).find(':selected');    
                   var role_id = role.data('role-id');
                   var role_slug = role.data('role-slug');
                   permissions_ckeckbox_list.empty();
                   user_permissions_box.empty();
                   $.ajax({
                       url: "/users/create",
                       method: 'get',
                       dataType: 'json',
                       data: {
                           role_id: role_id,
                           role_slug: role_slug,
                       }
                   }).done(function(data) {                 
                       permissions_box.show();                        
                       $.each(data, function(index, element){
                           $(permissions_ckeckbox_list).append(       
                                   '<input class="custom-control-input" type="checkbox" checked="" name="permisos[]"  id="'+ element.slug +'" value="'+ element.id +'">' +
                                 '<span class="tag tag-primary mt-1 mb-1">' + element.slug + '</span> '    
                           );
                       });
                   });
               });
           });
       </script>

   @endsection
@endsection