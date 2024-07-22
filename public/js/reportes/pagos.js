$(document).ready(function() {
    console.log("document ready reportes pagos")
    // empresa.js   
    obtenerEmpresaId();
    if ($('#tablaReportePagosAjax').length) {
        console.log("document ready tablaReportePagosAjax")
        tablaReportePagos()  
        
    }

});

function documentoPagosCsv(row){
    
    
    html = '<a href="../'+row.ruta_csv+'" target="_blank"> \
                <i class="text-info tx-24 fa fa-archive" data-toggle="tooltip" \
                title="'+row.registros_cantidad+'" \
                data-original-title="fa fa-archive"> \
                </i>\
            </a>\
                ';
                
                            
                        
    return html;
}


$("#generarReportePagos").click(function(e) {
    console.log("generarReportePagos")
    e.preventDefault();

    var form = $('#reportePagosForm').parsley().refresh();
    var action = $('#reportePagosForm').attr("action"); 
    console.log(action);

    swal(
        "Generando!",
        "Preparando el reporte",
        "success"
      )
    if ( form.validate() ){
        $.ajax({
            /* Usar el route  */
            url: action,
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: $('#reportePagosForm').serialize()
            
            /* remind that 'data' is the response of the AjaxController */
            }).done(function( response) {
                console.log("done");

                tablaReportePagos();
            }).fail( function( data,jqXHR, textStatus, errorThrown ) {
                console.log( "fail" );
                swal(
                    "Error!",
                    textStatus,
                    "error"
                  )

            }).always(function() {
                console.log( "complete" );
            });
        
    } else {
        console.log( "reportePagosForm con errores" );
        return false;
    }

});


function tablaReportePagos(){

    $.ajax({
        url: '../api/reportes/pagos/index',
        type: 'GET',
        /* send the csrf-token and the input to the controller */
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        data: $('#reporteVentasForm').serialize()
        
        /* remind that 'data' is the response of the AjaxController */
    }).done(function( response) {
        console.log(response.data)
        table = $('#tablaReportePagosAjax').DataTable({
                    "oLanguage": {
                        "sEmptyTable": "No se puede mostrar los registros"
                    }
                    ,processing: true
                    ,pagingType: "full_numbers"
                    ,deferRender: true
                    ,bDestroy: true
                    ,data: response.data
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

                    ,columns: [
                        { "data": "id" } //0
                        ,{ "data": "ruta_csv"
                            ,render: function(data, type, row){   
                                return documentoPagosCsv(row); 
                            }
                        }
                        ,{ "data": "creada" 
                        }
                        ,{ "data": "user_nombre" }
                        ,{ "data": "empresa_nombre" }
                        ,{ "data": "banco_nombre" }//4
                        ,{ "data": "fecha_ini" }
                        ,{ "data": "fecha_fin" }
                        

                    ],
                });
        //table.columns( [12] ).visible( false );
            
    }).fail( function( data,jqXHR, textStatus, errorThrown ) {
        console.log( "fail" );
        console.log(data);
        swal(
            "Error!",
            textStatus,
            "error"
          )


    }).always(function() {
            console.log( "complete tablaReporteVentasAjax" );
    });
} 
