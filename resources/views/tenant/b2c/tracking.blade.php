@extends('tenant.layout')
@section('title','Rastrear envío')
@section('content')<div style="max-width:720px;margin:2rem auto"><header class="z-page-header"><div><div class="z-eyebrow">SEGUIMIENTO</div><h1>Rastrea tu envío</h1><p class="z-muted">Consulta el estado usando el número de tu guía.</p></div></header><form class="z-card z-stack" data-tracking-form><label class="z-field">Número de rastreo<input name="tracking" maxlength="32" autocomplete="off" required></label><button class="z-btn">Rastrear</button></form></div>@endsection
@push('scripts')<script>document.querySelector('[data-tracking-form]').addEventListener('submit',function(e){e.preventDefault();const value=this.elements.tracking.value.trim();if(value)location.href='/rastreo/'+encodeURIComponent(value)})</script>@endpush
