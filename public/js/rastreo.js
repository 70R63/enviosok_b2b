$(document).ready(function() {
    console.log("Ready peticion ajax ")
    if ($('#rastreoTabla').length) {
        rastrearTabla()     
    }
   
}) 


function rastrearTabla(){
    $.ajax({
        url: 'api/guiasTabla',
        type: 'GET',
        /* send the csrf-token and the input to the controller */
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        
        
        /* remind that 'data' is the response of the AjaxController */
    }).done(function( response) {
        table = $('#rastreoTabla').DataTable({
                    "oLanguage": {
                        "sEmptyTable": "No se puede mostrar los registros"
                    }
                    ,processing: true
                    ,serverSide: false 
                    ,pagingType: "full_numbers"
                    ,deferRender: true
                    ,bDestroy: true
                    ,data: response.data.tabla
                    ,autoWidth: false
                    ,order: [[0, 'desc']]
                    ,lengthMenu: [
                        [ 10, 25, 50, -1 ],
                        [ '10', '25', '50', 'Todo' ]
                    ]
                    ,dom: 'Bfrtip'
                    ,buttons: [ 
                        'pageLength'
                      ,{ 
                         extend: 'excelHtml5'
                         , footer: true
                         , charset: 'utf-8' 
                         , fieldSeparator: ','
                         ,fieldBoundary: ''
                         ,exportOptions: {
                            columns: ':not(.notexport)'
                         }
                         ,customizeData: function(data) {
                           for(var i = 0; i < data.body.length; i++) {
                            console.log("custom")
                             data.body[i][2] = '\0' + data.body[i][2];
                           }
                         } 
                      }
                      ,{ 
                         extend: 'pdf'
                         ,orientation: 'landscape'
                         , footer: true 
                         ,exportOptions: {
                            columns: ':not(.notexport)'
                         } 
                      }
                      
                   ]
                    ,columnDefs: [
                        {  
                            targets: 3 
                            ,"createdCell": function(td, cellData, rowData, row, col) {
                                switch(cellData) {
                                case "CREADA":
                                        $(td).addClass('text-danger');
                                        break;
                                    case "RECOLECTADO":
                                        $(td).addClass('text-warning');
                                        break;
                                    case "TRANSITO":
                                        $(td).addClass('text-info');
                                        break;
                                    case "ENTREGADO":
                                        $(td).addClass('text-success');
                                        break;
                                }
                            }
                        }
                        ,{  
                            targets: 8 
                            ,"createdCell": function(td, cellData, rowData, row, col) {
                                switch(cellData) {
                                case "CREADA":
                                        $(td).addClass('text-danger');
                                        break;
                                    case "RECOLECTADO":
                                        $(td).addClass('text-warning');
                                        break;
                                    case "TRANSITO":
                                        $(td).addClass('text-info');
                                        break;
                                    case "ENTREGADO":
                                        $(td).addClass('text-success');
                                        break;
                                }
                            }
                        }
                        
                    ]
                    ,"columns": [
                        { "data": "id" }
                        ,{ "data": "mensajeria" }
                        ,{ "data": "tracking_number" }
                        ,{ "data": "rastreo_nombre" }
                        ,{ "data": "servicio_nombre" }
                        ,{ "data": "creada" }
                        ,{ "data": "pickup_fecha_f" }
                        ,{ "data": "ultima_fecha_f" }
                        ,{ "data": "tiempo_entrega"
                            ,render: function(data, type, row){ 
                                return garantia(row); 
                            } 
                        }
                        ,{ "data": "quien_recibio" }
                        ,{ "data": "nombre" }
                        ,{ "data": "ciudad" }
                        ,{ "data": "ciudad_d" }
                        
                       
                        ,{ "data": "rastreo_peso" }
                        ,{ "data": "largo_u" }
                        ,{ "data": "ancho_u" }
                        ,{ "data": "alto_u" }
                    ],
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

function rastreoActualizarAjax() {
$.ajax({
        url: 'api/rastreoActualizar',
        type: 'POST',
        async: false
        /* send the csrf-token and the input to the controller */
        ,headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        
        /* remind that 'data' is the response of the AjaxController */
    }).done(function( response) {
        console.log(response.data)
        
            
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

function garantia(row){

    var ahora = new Date();
    var pickupFecha = new Date(row.pickup_fecha_f);
    var ultimaFecha = new Date(row.ultima_fecha_f);
    var diaExtraControl = 1
    var garantiaFecha = new Date(pickupFecha.setDate(pickupFecha.getDate() + diaExtraControl+ row.tiempo_entrega))

   
    var diaLaboral = 0
    if (garantiaFecha.getDay() === 0 || garantiaFecha.getDay() === 6 )
        diaLaboral = 2

    var garantiaFechaLaboral = new Date(garantiaFecha.setDate(garantiaFecha.getDate() + diaLaboral))

    console.log("---------------------")
    console.log(garantiaFecha)
    console.log(diaLaboral)
    console.log(ahora)
    console.log(garantiaFechaLaboral)
    console.log(ultimaFecha)



    switch (row.rastreo_nombre) {
      case 'CREADA':
        html = '<i class="fe fe-download-cloud fs-29 "> EN TIEMPO</i>'; 
        break;
      case 'ENTREGADO':
        if ( ultimaFecha > garantiaFechaLaboral){
            html = '<i class="fe fe-download-cloud fs-29 text-danger"> DESFASADA</i>';
        } else {
            html = '<i class="fe fe-upload-cloud fs-29 text-success"> EN TIEMPO</i>';
        } 
        break;
      default:
        if ( ahora > garantiaFechaLaboral){
            html = '<i class="fe fe-download-cloud fs-29 text-danger"> DESFASADA</i>';
        } else {
            html = '<i class="fe fe-upload-cloud fs-29 text-success"> EN TIEMPO</i>';
        } 
    }

    
    return html;
       
}
