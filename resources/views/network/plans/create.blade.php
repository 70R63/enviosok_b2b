@extends('network.layout') @section('title','Crear plan - ZIGO Network') @section('content')
<div class="title">Crear plan</div><div class="card"><form method="POST" action="{{ route('network.plans.store') }}">@csrf
@foreach(['code'=>'Código','name'=>'Nombre'] as $field=>$label)<div class="field"><label>{{ $label }}</label><input name="{{ $field }}" value="{{ old($field) }}" required></div>@endforeach
<div class="field"><label>Descripción</label><textarea name="description">{{ old('description') }}</textarea></div><div class="field"><label>Estado</label><select name="status"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
<div class="field"><label>Precio mensual</label><input type="number" min="0" step="0.01" name="monthly_price" value="{{ old('monthly_price') }}"></div><div class="field"><label>Precio anual</label><input type="number" min="0" step="0.01" name="annual_price" value="{{ old('annual_price') }}"></div>
<div class="field"><label>Moneda</label><input name="currency" maxlength="3" value="{{ old('currency','MXN') }}" required></div><div class="field"><label>Operaciones incluidas</label><input type="number" min="0" name="included_operations" value="{{ old('included_operations') }}"></div><button class="btn">Guardar</button></form></div>
@endsection
