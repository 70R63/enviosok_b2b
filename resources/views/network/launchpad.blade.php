@extends('network.layout')
@section('title','Launchpad - ZIGO Network')
@section('content')
<header class="hero"><div><div class="eyebrow">ZIGO NETWORK</div><div class="title">Control Center</div><div class="subtitle">Ecosistema 360 · Logistics · SaaS · White Label</div></div><div class="legend">@foreach($statuses as $code=>$status)<x-network.status-badge :status="$code" :statuses="$statuses" />@endforeach</div></header>
<div class="launch-tools"><i class="fa fa-search"></i><input id="app-search" type="search" placeholder="Buscar aplicación..." aria-label="Buscar aplicación"></div>
@foreach($groups as $group=>$groupNodes)<h2 class="section-title">{{ str_replace('_',' ',$group) }}</h2><div class="grid">@foreach($groupNodes as $node)<x-network.app-card :node="$node" />@endforeach</div>@endforeach
<x-network.node-detail :nodes="$nodes" />
@endsection
@push('scripts')<script>(()=>{const q=document.getElementById('app-search');q?.addEventListener('input',()=>{const value=q.value.trim().toLowerCase();document.querySelectorAll('.app-card').forEach(tile=>tile.hidden=!tile.dataset.search.includes(value));document.querySelectorAll('.launch-group').forEach(group=>group.hidden=!group.querySelector('.app-card:not([hidden])'))})})();</script>@endpush
