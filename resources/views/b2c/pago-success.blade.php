<form method="POST" action="{{ route('b2c.guia.generar', $cotizacion->id) }}">
    @csrf
    <button type="submit">Generar guía</button>
</form>