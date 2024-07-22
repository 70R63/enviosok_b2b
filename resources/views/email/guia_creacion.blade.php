<div class="container">
    <p>Hola Estimado Usuario {{$usuario}}</p>
    <p>
        Hemos recibido su peticion de Guia con Numero <b> {{$id}}</b>
    </p>
    <p>
        Los datos del Remitente <b>{{ $objeto->nombre}}</b> son:
        <p>Domicilio: <b> {{ $objeto->calle }}, {{ $objeto->numero }}, {{ $objeto->colonia }}, {{ $objeto->entidad_federativa }}, CP: {{ $objeto->cp }} </b> </p>
        <p>Telefono : <b>{{ $objeto->telefono }}</b> </p>
    </p>
    <p></p><br>
    <p>
        
        Con Destino <b>{{ $objeto->nombre_d}}</b> son:
        <p>Domicilio: <b> {{ $objeto->calle_d }}, {{ $objeto->numero_d }}, {{ $objeto->colonia_d }}, {{ $objeto->entidad_federativa_d }}, CP: {{ $objeto->cp_d }} </b> </p>
        <p>Telefono : <b>{{ $objeto->telefono_d }}</b> </p>
    </p>
    <p></p><br>
    Paquete enviado con <b>{{ $nombre }}</b>
    <br>

    
</div>