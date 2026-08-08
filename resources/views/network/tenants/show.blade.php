@extends('network.layout') @section('title',$tenant->name.' - ZIGO Network') @section('content')
<div class="title">{{ $tenant->name }}</div><div class="subtitle">{{ $tenant->slug }}</div><div class="card"><dl><dt><strong>UUID</strong></dt><dd>{{ $tenant->uuid }}</dd><dt><strong>Estado</strong></dt><dd>{{ ucfirst($tenant->status) }}</dd></dl></div>
@foreach(['DOMINIOS'=>'Pendiente ZN-02','SUSCRIPCIÓN'=>'Pendiente ZN-04','MÓDULOS'=>'Pendiente ZN-04','CONSUMO'=>'Pendiente Usage'] as $heading=>$pending)<div class="card"><h2>{{ $heading }}</h2><p class="muted">{{ $pending }}</p></div>@endforeach
@endsection
