@extends('tenant.admin.layout')
@section('title','Motoristas')
@section('content')
<div class="eyebrow">LAST MILE</div><h1>Motoristas</h1>
@if($canManage)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:24px">
<section class="card"><h2>Crear nuevo motorista</h2><p class="muted">Crea una identidad nueva con acceso exclusivo a este tenant.</p>
@if($errors->any() && !old('membership_id'))<div class="alert alert-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('tenant.admin.drivers.store',[],false) }}">@csrf
<label>Nombre<input class="input" name="name" value="{{ old('name') }}" autocomplete="name" required></label>
<label>Email<input class="input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
<label>Contraseña inicial<input class="input" type="password" name="password" autocomplete="new-password" required></label>
<label>Confirmación de contraseña<input class="input" type="password" name="password_confirmation" autocomplete="new-password" required></label>
<label>Código de motorista<input class="input" name="code" value="{{ old('code') }}" required></label>
<label>Vehículo (opcional)<input class="input" name="vehicle_label" value="{{ old('vehicle_label') }}"></label>
<button class="btn" type="submit">Crear motorista</button></form></section>
<section class="card"><h2>Habilitar usuario existente</h2><p class="muted">Convierte una membresía activa no administrativa en motorista; su contraseña actual no se modifica.</p>
<form method="POST" action="{{ route('tenant.admin.drivers.store',[],false) }}">@csrf
<select class="input" name="membership_id" required><option value="">Usuario</option>@foreach($memberships as $membership)<option value="{{ $membership->id }}">{{ $membership->user->name }} · {{ $membership->user->email }} · {{ $membership->role }}</option>@endforeach</select>
<input class="input" name="code" placeholder="Código de motorista" required><input class="input" name="vehicle_label" placeholder="Vehículo (opcional)"><button class="btn">Habilitar usuario</button></form></section>
</div>
@endif
<section class="card table-wrap"><table><thead><tr><th>Código</th><th>Usuario</th><th>Vehículo</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($drivers as $driver)<tr><td>{{ $driver->code }}</td><td>{{ $driver->user->name }}<br><span class="muted">{{ $driver->user->email }}</span></td><td>{{ $driver->vehicle_label??'—' }}</td><td>{{ $driver->status }}</td><td><a href="{{ route('tenant.admin.drivers.show',$driver->uuid,false) }}">Ver</a>@if($canManage)<form method="POST" action="{{ route('tenant.admin.drivers.toggle',$driver->uuid,false) }}">@csrf @method('PATCH')<button>Activar/desactivar</button></form>@endif</td></tr>@empty<tr><td colspan="5">Sin motoristas.</td></tr>@endforelse</tbody></table></section>
@endsection
