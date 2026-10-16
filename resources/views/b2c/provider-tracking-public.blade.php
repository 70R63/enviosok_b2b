@extends('layouts.b2c-public')
@section('title', 'Rastreo de envío | ZIGO')
@section('content')
<section class="public-card">
    <h1>Rastrea tu envío</h1>
    <form method="GET" action="{{ route('b2c.rastreo') }}">
        <label>Número de rastreo <input name="tracking_number" maxlength="100" value="{{ $tracking }}" required></label>
        <button type="submit">Rastrear</button>
    </form>
    @if($publicTracking)
        <p>Tracking: {{ $publicTracking['tracking_number'] }}</p>
        <p>Operador logístico: {{ $publicTracking['operator'] }}</p>
        <p>Servicio: {{ $publicTracking['service'] }}</p>
        <p role="status">{{ $publicTracking['status_label'] }}</p>
        <ol>
            @foreach($publicTracking['timeline'] as $event)
                <li>{{ $event['label'] }} · <time datetime="{{ $event['occurred_at'] }}">{{ \Carbon\Carbon::parse($event['occurred_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time></li>
            @endforeach
        </ol>
    @elseif($nationalTracking ?? null)
        <p>Tracking: {{ $nationalTracking['tracking_number'] }}</p>
        <p>Mensajería: {{ $nationalTracking['operator'] }}</p>
        <p role="status">{{ $nationalTracking['status_label'] }}</p>
    @elseif($tracking !== '')
        <p role="status">No encontramos información para el número de rastreo: {{ $tracking }}</p>
    @endif
</section>
@endsection
