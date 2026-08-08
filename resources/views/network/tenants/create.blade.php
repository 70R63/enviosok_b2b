@extends('network.layout') @section('title','Crear tenant - ZIGO Network') @section('content')
<div class="title">Crear tenant</div><div class="card"><form method="POST" action="{{ route('network.tenants.store') }}">@csrf
<div class="field"><label>Nombre</label><input name="name" value="{{ old('name') }}" required maxlength="191"></div>
<div class="field"><label>Slug</label><input name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="100"><small class="muted">Lowercase; letras, números y guiones.</small></div>
<div class="field"><label>Estado</label><select name="status" required>@foreach(['active'=>'Activo','inactive'=>'Inactivo','suspended'=>'Suspendido'] as $value=>$label)<option value="{{ $value }}" @selected(old('status','active')===$value)>{{ $label }}</option>@endforeach</select></div><button class="btn">Guardar</button></form></div>
@endsection
