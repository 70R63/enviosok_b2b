<h1>Tu envío fue confirmado</h1>
<p>Folio / guía: {{ $confirmation['folio'] }}</p>
<p>Servicio: {{ $confirmation['service'] }}</p>
<p>Operado por {{ $confirmation['provider'] }}</p>
<p>La recolección será realizada en:</p>
@foreach($confirmation['pickup'] as $line)<p>{{ $line }}</p>@endforeach
<p>Destino:</p>
@foreach($confirmation['delivery'] as $line)<p>{{ $line }}</p>@endforeach
<p><a href="{{ $confirmation['url'] }}">Consultar envío y descargar guía</a></p>
