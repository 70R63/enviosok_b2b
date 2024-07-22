$(document).ready(function() {
    console.log("document ready empreesa")
   
    validaTipoPago()
});

function validaCheckAsignacion( elemento ){
    
    var check = elemento.is( ":checked" )

        
    var ltdName = elemento.attr("name"); 
    var comboActivo=$("select[name='clasificacion["+ltdName+"]']")
    console.log("validando combo")
    console.log(comboActivo.val())

    if ( check ) {
        comboActivo.attr("required","true");
    } else{
        comboActivo.removeAttr("required");
        comboActivo.prop('selectedIndex',0); 


    }
}

$(".btnAsignarLtd").click(function(e) {
    console.log("btnAsignarLtd")
    e.preventDefault();
    
    var idModal = "#asignarLtd"+$(this).attr('id');
    
    var data = $(idModal).find('Form').serialize();
    var accion = $(idModal).find('Form').attr("action");
   
    
    $(idModal+" .selectgroup-input").each(function(){
        validaCheckAsignacion( $(this) )
    })
    

    var form = $(idModal+' #generalForm').parsley().refresh();

    if ( form.validate() ){ 
        $(".modalAsignarLtd").modal('hide');
         $.ajax({
            /* Usar el route  */
            url: accion,
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: data
            
            /* remind that 'data' is the response of the AjaxController */
            }).done(function( response) {
                console.log("done")
                swal(
                    "Exito!",
                    response.data.mensaje,
                    "success"
                  )

            }).fail( function( data,jqXHR, textStatus, errorThrown ) {
                console.log( "fail" );
                console.log(textStatus);
                
                swal(
                    "Error!",
                    "Asignacion incorrecta!. Consulte con su proveedor",
                    "error"
                  )

            }).always(function() {
                console.log( "complete" );
            });
        
    } else {
        console.log( "enviosForm con errores" );
        //return false;
    }
    
});

$("#asignarRcPG").click(function(e) {
    console.log("asignarRcPG")
    e.preventDefault();

    $("#rfc").val("XAXX010101000");

});


$('.selectgroup-input').change(function () {
    
    validaCheckAsignacion($(this))

});

function ocultarMostrarHtmlPago(tipoPagoId){
    if ( tipoPagoId  ==2 ){
        console.log(tipoPagoId)
        $( ".tipo_pago" ).hide();
        $('#limite_credito').removeAttr('required')
        $('#plazo_credito_id').removeAttr('required')

    } else {
        $( ".tipo_pago" ).show();
        $('#limite_credito').attr('required', true); 
        $('#plazo_credito_id').attr('required', true); 
    }
}

$('#tipo_pago_id').change(function () {
    
    var tipoPagoId =$(this).val() 
    console.log(tipoPagoId)
    ocultarMostrarHtmlPago($(this).val())
});

function validaTipoPago(){
    var tipoPagoId = $('#tipo_pago_id').val()
    ocultarMostrarHtmlPago(tipoPagoId)
    
}

function obtenerEmpresaId() {

    $.ajax({
        /* Usar el route  */
        url: route('api.clientes'),
        type: 'GET',
        /* send the csrf-token and the input to the controller */
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        //data: "clienid="+id
        
        /* remind that 'data' is the response of the AjaxController */
        }).done(function( response) {
            console.log("done");
            //console.log(response.data);
           
            $('#empresa_id').empty();
            $("#empresa_id").append('<option selector="0" value="0"> TODOS</option>');
            
            $.each(response.data,function(key, empresa) {
                $("#empresa_id").append('<option selector='+key+' value="'+empresa.id+'" >'+empresa.nombre+'</option>');
              });   
            
        
        }).fail( function( data,jqXHR, textStatus, errorThrown ) {
            console.log( "fail" );
            console.log(textStatus);
            
            swal(
                "Error!",
                data.responseJSON.message,
                "error"
              );
            

        }).always(function() {
            console.log( "complete" );
        });

}