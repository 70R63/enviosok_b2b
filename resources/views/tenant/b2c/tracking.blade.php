@extends('tenant.layout')
@section('title','Tracking')
@section('content')<section class="hero"><div class="eyebrow">TRACKING</div><h1>Seguimiento de envíos</h1><p>La consulta pública tenant-aware estará disponible cuando el tracking legacy pueda aislarse por operación. No se muestran datos simulados.</p><a href="{{ route('tenant.b2c.quote.create') }}">Volver a cotizar</a></section>@endsection
