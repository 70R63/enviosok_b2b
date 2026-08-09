@extends('tenant.layout')
@section('title','Tracking')
@section('content')
<section class="hero"><div class="eyebrow">TRACKING</div><h1>{{ $trackingResult['tracking_number'] }}</h1><p>Estado actual: <strong>{{ $trackingResult['status']==='DELIVERY_FAILED' ? 'Intento de entrega no completado' : str_replace('_',' ',$trackingResult['status']) }}</strong></p></section>
<section class="results"><h2>Historial</h2>@foreach($trackingResult['events']->sortByDesc('occurred_at') as $event)<article class="quote-option"><div><strong>{{ $event['status']==='DELIVERY_FAILED' ? 'Intento de entrega no completado' : str_replace('_',' ',$event['status']) }}</strong></div><time>{{ $event['occurred_at']->format('d/m/Y H:i') }}</time></article>@endforeach</section>
@endsection
