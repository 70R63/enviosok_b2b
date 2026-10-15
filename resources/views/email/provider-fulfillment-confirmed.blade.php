<h1>Tu compra fue confirmada</h1>
<p>Folio / guía: {{ $confirmation['folio'] }}</p>
<p>Servicio: {{ $confirmation['service'] }}</p>
<p>Operado por {{ $confirmation['provider'] }}</p>
<p>Solicitud enviada al operador logístico. Consulta el envío para conocer su aceptación y la confirmación de recolección.
Dirección solicitada de recolección:</p>
@foreach($confirmation['pickup'] as $line)<p>{{ $line }}</p>@endforeach
<p>Destino:</p>
@foreach($confirmation['delivery'] as $line)<p>{{ $line }}</p>@endforeach
<p><a href="{{ $confirmation['url'] }}">Consultar envío y descargar guía</a></p>
