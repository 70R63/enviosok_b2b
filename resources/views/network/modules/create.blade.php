@extends('network.layout') @section('title','Crear módulo - ZIGO Network') @section('content')
<div class="title">Crear módulo</div><div class="card"><form method="POST" action="{{ route('network.modules.store') }}">@csrf
@foreach(['code'=>'Código','name'=>'Nombre'] as $field=>$label)<div class="field"><label>{{ $label }}</label><input name="{{ $field }}" value="{{ old($field) }}" required></div>@endforeach
<div class="field"><label>Descripción</label><textarea name="description">{{ old('description') }}</textarea></div><div class="field"><label>Tipo</label><select name="type">@foreach(['channel','core','addon','integration'] as $type)<option value="{{ $type }}" @selected(old('type')===$type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
<div class="field"><label><input style="width:auto" type="checkbox" name="is_active" value="1" @checked(old('is_active',true))> Activo</label></div><div class="field"><label>Orden</label><input type="number" min="0" max="65535" name="sort_order" value="{{ old('sort_order',0) }}" required></div><button class="btn">Guardar</button></form></div>
@endsection
