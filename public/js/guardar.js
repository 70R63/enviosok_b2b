$(function(){
    $("#guardar").click(function (e) { 
        e.preventDefault();
        var forma = $( "#enviosForm" ).serialize();
        $.ajax({
            /* Usar el route  */
            url: "{{route('dashboard')}}",
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: forma,
           
            /* remind that 'data' is the response of the AjaxController */
            }).done(function( data ) {
                console.log("done");
                console.log(data.pieza)
                //form agrea la calve 
                $("#clave").val( data.clave );

                $("#spanTitulo").text( data.mensaje );
                $("#spanClave").text( data.clave );
                $("#spanMensajeria").text( data.mensajeria );
                $("#spanRemitente").text( data.remitente );
                $("#spanDestinatario").text( data.destinatario );
                $("#spanPieza").text( data.pieza );

                $("#guardarExito").modal("show");

            }).fail( function( jqXHR, textStatus, errorThrown ) {
                console.log( "error" );
                console.log(errorThrown);
                console.log(textStatus);
                alert("No se puede guardar intente otra vez o Consulte con su proveedor");
                console.log(jqXHR);
            }).always(function() {
                console.log( "complete" );
            });
    });
});