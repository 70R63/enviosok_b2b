$(document).ready(function() {
    if ($('#remitenteTabla').length) {
        remitenteTabla()     
    }
   
})


function remitenteTabla(){
    $.ajax({
        url: 'api/direccion/remitente',
        type: 'GET',
        /* send the csrf-token and the input to the controller */
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        
    }).done(function( response) {
        console.log(response.data)
        table = $('#remitenteTabla').DataTable({
                "oLanguage": {
                    "sEmptyTable": "No se puede mostrar los registros"
                }
                ,processing: true
                ,serverSide: false 
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
                    { "data": "id" }
                    ,{ "data": "nombre"}
                    ,{ "data": "contacto" }
                    ,{ "data": "direccion" }
                    ,{ "data": "colonia" }
                    ,{ "data": "ciudad" }
                    ,{ "data": "cp" }
                    ,{ "data": "entidad_federativa" }
                    ,{ "data": "telefono" }
                    ,{ "data": "accion"
                            ,render: function(data, type, row){ 
                            return remitentAcciones(row); 
                        }
                    }

                ]
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


function remitentAcciones(row){

    var htmlEditar = '<a href="sucursales/'+row.id+'/edit" class="text-info tx-20 "> <i class="fe fe-edit" alt="Editar"></i></a>'

    var htmlEliminar = '<a href="" class="remove-list text-danger tx-20 remove-button" data-toggle="modal" \
                        data-target="#modal" ><i class="fa fa-trash" alt="Eliminar"></i> \
                        </a>'

    return htmlEditar+htmlEliminar
}

