@extends('layouts.app')
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-4 bg-primary rounded-left d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 100%;">
                <img  src="{{ url('spruha/img/brand/xpertaLogoTrans-138x142.png') }}" class="header-brand-img mb-1" alt="logo">
                <div class="clearfix"></div>
                <img src="{{ url('spruha/img/svgs/user.svg') }}" class="ht-90 mb-0" alt="user">
                <h5 class="mt-4 text-white">Create Your Account</h5>
                <span class="tx-white-6 tx-13 mb-5 mt-xl-0">Signup to create, discover and connect with the global community</span>
            </div>
            <div class="col-md-8 bg-white rounded-right p-4 registro">
                <div class="row">
                    @if($errors->any())
                        <div class="w-100 alert alert-danger alert-dismissible fade show my-2" role="alert">
                            <div class="d-flex justify-content-start fs-small">
                                <i class="fa-solid fa-circle-exclamation my-1 me-2"></i>
                                <div>
                                    <small>
                                        <strong class="fw-semibold">¡Error!</strong> {{ $errors->first() }}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </small>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="row">
                    <div class="col-12 text-center">
                        <h5>Crea tu cuenta</h5>
                    </div>
                </div>
                <div class="row registro justify-content-center">
                    <ul class="nav nav-tabs">
                        <li class="nav-item rounded">
                            <a class="nav-link tx-13 active" aria-current="page" href="#tabInformacion" data-bs-toggle="tab">1. Información</a>
                        </li>
                        <li class="nav-item rounded">
                            <a class="nav-link disabled tx-13" href="#tabDocumentacion" data-bs-toggle="tab">2. Documentación</a>
                        </li>
                    </ul>
                    <div class="tab-content mt-2">
                        <div class="tab-pane fade p-2 show active" id="tabInformacion">
                            <form class="row" novalidate id="formularioDatos">
                                <div class="col-12">
                                    <p class="text-muted tx-13">Introduce tus datos personales</p>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="name">Nombre(s): <span class="text-danger">*</span></label>
                                    <input type="text" placeholder="Escribe tu nombre" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('name')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="apellido_paterno">Apellido Paterno: <span class="text-danger">*</span></label>
                                    <input type="text" placeholder="Escribe tu apellido" class="form-control @error('apellido_paterno') is-invalid @enderror" name="apellido_paterno" value="{{ old('apellido_paterno') }}" required>
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('apellido_paterno')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="apellido_materno">Apellido Materno:</label>
                                    <input type="text" placeholder="Escribe tu apellido" class="form-control" name="apellido_materno" value="{{ old('apellido_materno') }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="rfc">RFC: <span class="text-danger">*</span></label>
                                    <input type="text" placeholder="Escribe tu RFC" onkeyup="$(this).val($(this).val().toUpperCase());" class="form-control @error('rfc') is-invalid @enderror" name="rfc" value="{{ old('rfc') }}"

                                           minlength="13" maxlength="13" pattern="^([A-ZÑ&]{3,4})[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])([A-Z0-9]{3})?$" required>
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('rfc')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Email: <span class="text-danger">*</span></label>
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}"
                                           required autocomplete="email" autofocus placeholder="Escribe tu email">
                                    <div class="invalid-feedback">
                                        Introduce un email válido
                                    </div>
                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Contraseña: <span class="text-danger">*</span></label>
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" minlength="8" required placeholder="Escribe tu contraseña" value="{{old('password')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <p class="text-muted tx-13">Introduce los datos de tu domicilio</p>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="cp">Código postal: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('cp') is-invalid @enderror" placeholder="00000" name="cp" id="cp" maxlength="5" required
                                           value="{{@$modelo->domicilio->cp??old('cp')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('cp')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="estado">Estado: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('estado') is-invalid @enderror" placeholder="Escribe el estado" name="estado" id="estado" required readonly
                                           value="{{@$modelo->domicilio->estado??old('estado')}}">
                                    @error('estado')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                    <input type="hidden" name="codigo_estado" id="codigo_estado">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="municipio_alcaldia">Municipio / Alcaldía: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('municipio_alcaldia') is-invalid @enderror" placeholder="Escribe el municipio" name="municipio_alcaldia" id="municipio_alcaldia" required
                                           value="{{@$modelo->domicilio->municipio_alcaldia??old('municipio_alcaldia')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('municipio_alcaldia')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="colonia">Colonia: <span class="text-danger">*</span></label>
                                    <div class="div-colonia-cp">
                                        <input type="text" class="form-control @error('colonia') is-invalid @enderror" placeholder="Escribe la colonia" name="colonia" id="colonia" required
                                               value="{{@$modelo->domicilio->colonia??old('colonia')}}">
                                        <div class="invalid-feedback">
                                            Dato obligatorio
                                        </div>
                                        @error('colonia')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror

                                    </div>
                                </div>
                                <div class="form-group col-md-6 d-none">
                                    <label for="otra_colonia">Otra colonia:</label>
                                    <input type="text" class="form-control" placeholder="Escribe la colonia" name="otra_colonia" id="otra_colonia"
                                           value="{{@$modelo->domicilio->otra_colonia??old('otra_colonia')}}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="calle">Calle: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('calle') is-invalid @enderror" placeholder="Escribe la calle" name="calle" id="calle" required
                                           value="{{@$modelo->domicilio->calle??old('calle')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('calle')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="numero_exterior">Número exterior: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('no_exterior') is-invalid @enderror" placeholder="Escribe el número exterior" name="no_exterior" id="no_exterior" required
                                           value="{{@$modelo->domicilio->no_exterior??old('no_exterior')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('no_exterior')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="numero_interior">Número interior:</label>
                                    <input type="text" class="form-control" placeholder="Escribe el no. Interior" name="no_interior" id="no_interior"
                                           value="{{@$modelo->domicilio->no_interior??old('no_interior')}}">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="numero_exterior">Tipo vialidad: <span class="text-danger">*</span></label>
                                    <select name="tipo_vialidad_id" class="form-control" id="tipo_vialidad_id" required>
                                        <option value="">Selecciona una opción</option>
                                        @foreach($tiposVialidad as $i)
                                            <option value="{{$i->id}}">{{$i->nombre}}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('tipo_vialidad_id')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-12">
                                    <label for="referencias">Referencias:</label>
                                    <input type="text" class="form-control" placeholder="Ej. Entre las calles Benito Juárez y Miguel Hidalgo" name="referencias" id="referencias"
                                           value="{{@$modelo->domicilio->referencias??old('referencias')}}">
                                </div>
                                <div class="col-12 text-right">
                                    <button type="submit" class="btn ripple btn-main-primary">Siguiente <i class="fa fa-arrow-circle-right"></i></button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade p-2" id="tabDocumentacion">
                            <form method="POST" action="{{route('register')}}" class="row justify-content-center" enctype="multipart/form-data" novalidate id="formularioDocumentacion" >
                                @csrf
                                <input type="hidden" name="datos" id="datos">
                                <div class="col-12">
                                    <p class="text-muted tx-13">Adjunta los siguientes documentos</p>
                                </div>
                                <div class="form-group col-md-8">
                                    <label for="ine_anverso">INE Anverso: <span class="text-danger">*</span></label>
                                    <div class="row justify-content-center">
                                        <div class="col-12"></div>
                                        <img id="previewIneAnverso" src="" alt="Preview de la imagen" style="display: none; max-width: 300px;">
                                    </div>
                                    <input class="form-control documento-registro" data-preview="previewIneAnverso" name="ine_anverso" type="file" id="ine_anverso" required accept="image/*" capture="environment">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('ine_anverso')
                                    <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-8">
                                    <label for="ine_reverso">INE Reverso: <span class="text-danger">*</span></label>
                                    <div class="row justify-content-center">
                                        <div class="col-12"></div>
                                        <img id="previewIneReverso" src="" alt="Preview de la imagen" style="display: none; max-width: 300px;">
                                    </div>
                                    <input class="form-control documento-registro" data-preview="previewIneReverso" name="ine_reverso" type="file" id="ine_reverso" required accept="image/*" capture="environment">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('ine_reverso')
                                    <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="form-group col-md-8">
                                    <label for="selfie">Selfie sosteniendo tu INE: <span class="text-danger">*</span></label>
                                    <div class="row justify-content-center">
                                        <div class="col-12"></div>
                                        <img id="previewSelfie" src="" alt="Preview de la imagen" style="display: none; max-width: 300px;">
                                    </div>
                                    <input class="form-control documento-registro" data-preview="previewSelfie" name="selfie" type="file" id="selfie" required accept="image/*" capture="user">
                                    <div class="invalid-feedback">
                                        Dato obligatorio
                                    </div>
                                    @error('selfie')
                                    <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-12 d-flex justify-content-between">
                                    <button type="button" onclick="cambiarTab('tabInformacion')" class="btn ripple btn-main-primary">
                                        <i class="fa fa-arrow-circle-left"></i> Atrás
                                    </button>
                                    <button type="submit" class="btn ripple btn-main-primary">
                                        Completar registro
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-1">
                    @if (Route::has('password.request'))
                        <div class="mb-1"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div>
                    @endif
                    <div>¿Ya tienes cuenta? <a href="{{route('login')}}">Inicia sesión aquí</a></div>
                </div>
            </div>
        </div>
    </div>
@endsection
