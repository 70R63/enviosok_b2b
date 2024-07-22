@extends('layouts.app') 

@section('content')

<div class="page main-signin-wrapper">
   <!-- Row -->
   <div class="row signpages text-center" >
      <div class="col-md-12">
         <div class="card">
            <div class="row row-sm">
               <div class="col-lg-6 col-xl-5 d-none d-lg-block text-center bg-primary details">
                  <div class="mt-0 pt-4 p-2 pos-absolute">
                     <img  src="{{ url('spruha/img/brand/xpertaLogoTrans-138x142.png') }}" class="header-brand-img mb-1" alt="logo">{{ config('app.env', 'Ambiente') }}
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
                           <img src="{{ url('spruha/img/brand/ulalaPurpureLogo-134x49.png') }}" class=" d-lg-none header-brand-img text-center float-center mb-4" alt="logo">
                           <div class="clearfix"></div>
                            <form method="POST" action="{{ route('password.update') }}">
                                    @csrf
                                    <h5 class="text-left mb-2">{{ __('Restaurar contraseña') }}</h5>
                                              <p class="mb-4 text-muted tx-13 ml-0 text-left">{{ __('No te preocupes, solo tomará unos minutos.') }}</p>
                                              <br>
                                    <input type="hidden" name="token" value="{{ $request->route('token') }}">
                                    <div class="form-group row">
                                        <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail') }}</label>
                                        <div class="col-md-6">
                                            <x-input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" :value="old('email', $request->email)"  required autocomplete="email" autofocus readonly="readonly"/>
                                            @error('email')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Constraseña') }}</label>
                                        <div class="col-md-6">
                                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                                            @error('password')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="password-confirm" class="col-md-4 col-form-label text-md-right">{{ __('Confirmar contraseña') }}</label>
                                        <div class="col-md-6">
                                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <div class="col-md-6 offset-md-4  row justify-content-center">
                                            <button type="submit" class="btn btn-primary">
                                                {{ __('Cambiar Contraseña') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <br>
                              <!-- <div>Don't have an account? <a href="#">Register Here</a></div> -->
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