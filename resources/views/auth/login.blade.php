@extends('layouts.app')

@section('content')

<!-- Page -->
<div class="page main-signin-wrapper">
   <!-- Row -->
   <div class="row signpages text-center">
      <div class="col-md-12">
         <div class="card">
            <div class="row row-sm">
               <div class="col-lg-6 col-xl-5 d-none d-lg-block text-center bg-primary details">
                  <div class="mt-2 pt-4 p-2 pos-absolute">
                     <img  src="{{ url('spruha/img/brand/xpertaLogoTrans-138x142.png') }}" class="header-brand-img mb-1" alt="logo">
                     <div class="clearfix"></div>
                     <img src="{{ url('spruha/img/svgs/user.svg') }}" class="ht-90 mb-0" alt="user">
                     <h5 class="mt-4 text-white">Create Your Account</h5>
                     <span class="tx-white-6 tx-13 mb-5 mt-xl-0">Signup to create, discover and connect with the global community</span>
                  </div>
               </div>
               <div class="col-lg-6 col-xl-7 col-xs-12 col-sm-12 login_form ">
                  <div class="container-fluid">
                     <div class="row row-sm">
                        <div class="card-body mt-2 mb-2">
                           <img src="{{ url('spruha/img/brand/xpertaLogoHor.png') }}" class=" d-lg-none header-brand-img text-center float-center mb-4" alt="logo">
                           <div class="clearfix"></div>
                           <form method="POST" action="{{ route('login') }}">
                           	@csrf
                              <h5 class="text-left mb-2">Bienvenid@</h5>
                              <p class="mb-4 text-muted tx-13 ml-0 text-left">Inicie sesión para crear y descubrir una nueva experiencia</p>
                              <div class="form-group text-left">
                                 <label>Email</label>
                                 <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="Ingresa tu email">
                                 @error('email')
                                 <span class="invalid-feedback" role="alert">
                                 <strong>{{ $message }}</strong>
                                 </span>
                                 @enderror
                              </div>
                              <div class="form-group text-left">
                                 <label>Contraseña</label>
                                 <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="Ingresa tu password">
                                 @error('password')
                                 <span class="invalid-feedback" role="alert">
                                 <strong>{{ $message }}</strong>
                                 </span>
                                 @enderror
                              </div>
                              <button type="submit" class="btn ripple btn-main-primary btn-block">Ingresar</button>
                           </form>
                           <div class="text-center mt-5 ml-0">
                           	@if (Route::has('password.request'))
                              <div class="mb-1"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div>
                            @endif
                              <div>¿No tienes una cuenta? <a href="{{route('register')}}">Regístrate aquí</a></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <!-- End Row -->
</div>
		<!-- End Page -->

		@endsection
