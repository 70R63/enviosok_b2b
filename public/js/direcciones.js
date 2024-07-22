$(document).ready(function() {
    console.log("MenuDireccion")
})

var cpLista = 0;


$("#cp").on("change keyup paste", function (){
    
        if ( $("#cp").val().length > 2)
            listaColonias();
    });

function listaColonias(remitente = 1){
    var cp 
    var objeto 
    if (remitente) {
        cp = $("#cp").val()
        objeto = $('#colonia')
    } else {
        cp = $("#cp_d").val()
        objeto = $('#colonia_d')
    }

    $.ajax({
        url: route('api.cp.colonias'),
        type: 'GET',
        data: "cp="+cp
        /* send the csrf-token and the input to the controller */
        ,headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        
    }).done(function( response) {
        console.log( "done" );
        cpLista = response.data;
        console.log(remitente)

        
        objeto.empty();
        objeto.append('<option value=null> Seleccionar</option>');
        $.each(response.data,function(key, cps) {
            objeto.append('<option selector='+key+' value="'+cps.colonia+'" >'+cps.colonia+'</option>');
          });    
    
           
        
    }).fail( function( data,jqXHR, textStatus, errorThrown ) {
        console.log( "fail" );
        console.log(data);
        swal(
            "Error!",
            textStatus,
            "error"
          )

    }).always(function() {
            console.log( "complete" );
    });
}

//Busca las colonias despues la creacion manual
$(".buscaCP").click(function() {
    listaColonias(1);
});

$("#colonia").on("change keyup", function (){
    console.log( "cambio de colonia")
    
    var selector = $('#colonia option:selected').attr("selector")
    var registroCP = cpLista[selector];

    $("#ciudad").val(registroCP.municipio);
    $("#entidad_federativa").val(registroCP.entidad_federativa);
    
});

$("#colonia_d").on("change keyup", function (){
    console.log( "cambio de colonia")
    
    var selector = $('#colonia_d option:selected').attr("selector")
    var registroCP = cpLista[selector];

    $("#ciudad_d").val(registroCP.municipio);
    $("#entidad_federativa_d").val(registroCP.entidad_federativa);
    
});

//Busca las colonias despues la creacion manual
$(".buscaCP_d").click(function() {
    listaColonias(0);
});

