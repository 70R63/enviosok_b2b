@extends('dashboard') 
@section('content')   
@include('perfil.dashboard.header')


<!-- Main Content-->
<div class="container-fluid">
    <div class="inner-body">
        <div class="row square">
            <div class="col-lg-12 col-md-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="panel profile-cover">
                            <div class="profile-cover__img">
                                <img src="{{  url('spruha/img/users/user.png')}}" alt="img" />
                                <h3 class="h3">{{ Auth::user()->name }}</h3>
                            </div>
                            <br />
                            <br />
                            <br />
                            <br />
                            <div class="btn-profile"></div>
                            <div class="profile-cover__action bg-img"></div>
                            <div class="profile-cover__info"></div>
                        </div>
                        <div class="profile-tab tab-menu-heading">
                            <nav class="nav main-nav-line p-3 tabs-menu profile-nav-line bg-gray-100">
                                <a class="nav-link active" data-toggle="tab" href="#settings">Configuración de usuario</a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Row -->
        <div class="row row-sm">
            <div class="col-lg-12 col-md-12">
                <div class="card custom-card main-content-body-profile">
                    <div class="tab-content">
                        <div class="main-content-body tab-pane p-4 border-top-0 active" id="settings">
                            <div class="card-body border" data-select2-id="12">
                                <form class="form-horizontal" data-select2-id="11">
                                    <div class="mb-4 main-content-label">Usuario</div>
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">Nombre de usuario</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" placeholder="User Name" value="{{$users->name}}" readonly="" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">Email</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" placeholder="Email" value="{{$users->email}}" readonly="" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3">
                                                <label class="form-label">Rol Asignado</label>
                                            </div>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" placeholder="Designation" value="{{Auth::user()->roles->isNotEmpty() ? Auth::user()->roles->first()->name : ""}}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-md-3"></div>
                                            <div class="col-md-9">
                                                <a href="#" data-toggle="modal" data-target="#cambiarPassModal" data-userid="{{$users['id']}}">Cambiar Contraseña</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <!-- delete Modal-->
                                <div class="modal fade" id="cambiarPassModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">¿Segur@ deseas cambiar tu contraseña?</h5>
                                                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">Confirma con el botón "Cambiar".</div>
                                            <div class="modal-footer">
                                                <form method="POST" action="">
                                                    @method('PATCH') @csrf
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text" id="basic-addon1">Contraseña actual</span>
                                                        </div>
                                                        <input type="password" name="old_password" class="form-control" id="old_password" placeholder="Password actual" minlength="8" required />
                                                    </div>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text" id="basic-addon1">Nueva contraseña</span>
                                                        </div>
                                                        <input type="password" name="password" class="form-control" id="password" placeholder="Password..." minlength="8" required/>
                                                    </div>
                                                    <div class="input-group mb-3">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text" id="basic-addon1">Confirmar contraseña</span>
                                                        </div>
                                                        <input type="password" name="password_confirmation" class="form-control" placeholder="Password..." id="password_confirmation" required/>
                                                    </div>
                                                    <input type="hidden" id="user_id" name="user_id" value="{{$users->id}}" />
                                                    <input type="hidden" id="name" name="name" value="{{$users->name}}" />
                                                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
                                                    <a class="btn btn-success" onclick="$(this).closest('form').submit();">Cambiar</a>
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
            </div>
        </div>
        <!-- End Row -->
    </div>
</div>
<!-- End Main Content-->

	@section('js_user_page')
	<script>

	    $("#cambiarPassModal").on("show.bs.modal", function (event) {
	        var button = $(event.relatedTarget);
	        var user_id = button.data("userid");

	        var modal = $(this);
	        // modal.find('.modal-footer #user_id').val(user_id)
	        modal.find("form").attr("action", "/profile/" + user_id);
	    });
	</script>

	@endsection 
@endsection
