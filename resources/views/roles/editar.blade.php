@extends('dashboard')
@section('content')
@include('roles.dashboard.header')

<div class="row row-sm">
   <div class="col-sm-12 col-md-12">
      <div class="card custom-card ">
         <div class="card-header border-bottom-0 custom-card-header">
            <div class="col-sm-12 col-md-10">
               <h3 class="main-content-label mb-0">Edición de roles</h3>
            </div>
         </div>
         <div class="card-body">
            {!! Form::model($roles, ['route' => ['roles.update', $roles->id]]) !!}
            {!! Form::hidden('_method', 'put') !!}
            @csrf()
            <div class="input-group mb-3">
               <div class="input-group-prepend">
                  <span class="input-group-text" id="basic-addon1">Rol<span class="tx-danger">*</span></span>               
               </div>
               <input type="text" name="name" class="form-control" id="name" placeholder="Name..." value="{{ $roles->name }}" required>
            </div>
            <div class="input-group mb-3">
               <div class="input-group-prepend">
                  <span class="input-group-text" id="basic-addon1">Rol Slug<span class="tx-danger">*</span></span>               
               </div>
               <input type="text" name="slug" class="form-control" id="slug" readonly placeholder="Slug..." value="{{ $roles->slug }}">
            </div>
            <div class="input-group mb-3">
               <div class="input-group-prepend">
                  <span class="input-group-text" id="basic-addon1">Intercambiar permisos<span class="tx-danger">*</span></span>  
               </div>
            <div class="selectgroup selectgroup-pills" id="roles_permisos" name="roles_permisos">
                     <label class="selectgroup-item ">
                        <input type="checkbox" name="roles_permisos[]" value="crear" class="selectgroup-input">
                        <span class="selectgroup-button">Crear</span>
                     </label>
                     <label class="selectgroup-item">
                        <input type="checkbox" name="roles_permisos[]" value="editar" class="selectgroup-input">
                        <span class="selectgroup-button">Editar</span>
                     </label>
                     <label class="selectgroup-item">
                        <input type="checkbox" name="roles_permisos[]" value="leer" class="selectgroup-input">
                        <span class="selectgroup-button">Leer</span>
                     </label>
                     <label class="selectgroup-item">
                        <input type="checkbox" name="roles_permisos[]" value="borrar" class="selectgroup-input">
                        <span class="selectgroup-button">Borrar</span>
                     </label>
                  </div>
            </div>
            <div class="form-group pt-2">
               <input class="btn btn-success" type="submit" value="Enviar">
               <a href="{{route('roles.index')}}" class="btn btn-info">Inicio</a>
            </div>
            {!!Form::close()!!}
         </div>
      </div>
   </div>
</div>

@section('css_rol_page')
    <link rel="stylesheet" href="/css/bootstrap-tagsinput.css">

@endsection

@section('js_rol_page')
    <script src="/js/bootstrap-tagsinput.js"></script>

    <script>
        $(document).ready(function(){
            $('#name').keyup(function(e){
                var str = $('#name').val();
                str = str.replace(/\W+(?!$)/g, '-').toLowerCase();//reemplazar espacios en blanco con "-"
                $('#slug').val(str);
                $('#slug').attr('placeholder', str);
            });
        });
        
    </script>

@endsection

@endsection