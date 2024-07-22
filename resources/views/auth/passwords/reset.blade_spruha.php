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
								<div class="mt-3 pt-3 p-2 pos-absolute">
									<img src="{{ url('spruha/img/brand/ulalaPurpureLogo-134x49.png') }}" class="header-brand-img mb-4" alt="logo">{{ config('app.env', 'Ambiente') }}
									<div class="clearfix"></div>
									<img src="{{ url('spruha/img/svgs/user.svg') }}" class="ht-100 mb-0" alt="user">
									<h5 class="mt-4 text-white">Reset Your Password</h5>
									<span class="tx-white-6 tx-13 mb-5 mt-xl-0">Signup tcxzcxzo create, discover and connect with the global community</span>
								</div>
							</div>
							<div class="col-lg-6 col-xl-7 col-xs-12 col-sm-12 login_form ">
								<div class="container-fluid">
									<div class="row row-sm">
										<div class="card-body mt-2 mb-2">
											<img src="../../assets/img/brand/logo.png" class=" d-lg-none header-brand-img text-left float-left mb-4" alt="logo">
											<div class="clearfix"></div>
											<h5 class="text-left mb-2">Restaurar contraseña</h5>
											<p class="mb-4 text-muted tx-13 ml-0 text-left">No te preocupes, solo ocupa una contraseña que puedas recordar.</p>
											@if (session('status'))
							                    <div class="alert alert-success" role="alert">
							                        {{ session('status') }}
							                    </div>
							                @endif
											<form  method="POST" action="{{ route('password.update') }}">
												@csrf
												<input type="hidden" name="token" value="{{ $token }}">
												<div class="form-group text-left">
													<label>Email</label>
													<input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus readonly="readonly">
													@error('email')
					                                 	<span class="invalid-feedback" role="alert">
					                                 		<strong>{{ $message }}</strong>
					                                 	</span>
					                                @enderror
												</div>


												<div class="form-group text-left">
													<label>Nueva Contraseña</label>
													<input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
													@error('password')
					                                 	<span class="invalid-feedback" role="alert">
					                                 		<strong>{{ $message }}</strong>
					                                 	</span>
					                                @enderror
												</div>


												<div class="form-group text-left">
													<label>Confirmar Contraseña</label>
                                					<input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
												</div>


												<button  type="submit"  class="btn ripple btn-main-primary btn-block">Cambiar password</button>
											</form>
											<div class="card-footer border-top-0 pl-0 mt-3 text-left ">
												<p>¿Recordaste tu contraseña?</p>
												<p class="mb-0">Intenta <a href="{{ route('login') }}">Ingresar</a></p>
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