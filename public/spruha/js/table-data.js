$(function() {
	'use strict'

   //Data tabla Clientes (empresa)
   var tablaClienteDashboard = $('#tablaClienteDashboard').DataTable( {
      procesing :true
      ,serverSide:false
      ,paging:true
      ,pagingType: "full_numbers"
      ,pageLength: 25
      ,lengthChange: true
      ,"deferRender": true
      ,"paging": true
      ,buttons: [ 
                  { 
                     extend: 'excelHtml5'
                     , footer: true
                     , charset: 'utf-8'
                     , bom: true
                     , fieldSeparator: ','
                     ,fieldBoundary: ''
                     ,exportOptions: {
                        columns: ':not(.notexport)'
                     }
                     ,customizeData: function(data) {
                       for(var i = 0; i < data.body.length; i++) {
                        console.log("custom")
                         data.body[i][12] = '\0' + data.body[i][12];
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
      
      , order: [[0, 'desc']],
   } );

   tablaClienteDashboard.buttons().container()
   .appendTo( '#tablaClienteDashboard_wrapper .col-md-6:eq(0)' );

	
	//Data tabla general
   var exportGeneral = $('#exportGeneral').DataTable( {
      procesing :true
      ,serverSide:false
      ,pagingType: "full_numbers"
      ,deferRender: true
      ,bDestroy: true
      ,paging:true
      ,lengthChange: true
      ,dom: 'Bfrtip'
      ,buttons: [ 
            'pageLength'
            ,{  
               extend: 'excelHtml5'
               , footer: true
               , charset: 'utf-8'
               , bom: true
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
      
      , order: [[0, 'desc']],
   } );

   exportGeneral.buttons().container()
   .appendTo( '#exportGeneral_wrapper .col-md-6:eq(0)' );
   //Data tabla general

   //Data table exportCotizaciones
   var tableCotizacion = $('#exportCotizaciones').DataTable( {
      lengthChange: false,
      buttons: [ 'excel', 'pdf', 'colvis' ]
   } );

   tableCotizacion.buttons().container()
   .appendTo( '#exportCotizaciones_wrapper .col-md-6:eq(0)' );

   //Data table configPrecio
   var tableConfigPrecio = $('#configPrecio').DataTable( {
      lengthChange: false,
      buttons: [ 'excel', 'pdf' ]
      ,"paging":   false
   } );

   tableConfigPrecio.buttons().container()
   .appendTo( '#configPrecio_wrapper .col-md-6:eq(0)' );

   //Data table grupoTabla
   var grupoTabla = $('#grupoTabla').DataTable( {
      lengthChange: false,
      buttons: [ 'excel', 'pdf' ]
      ,"paging":   false
   } );

   grupoTabla.buttons().container()
   .appendTo( '#grupoTabla_wrapper .col-md-6:eq(0)' );
    //Fin Data table grupoTabla


   //Data tabla general sin buscador
   var exportGeneralNoBuscar = $('#exportGeneralNoBuscar').DataTable( {
      procesing :true
      ,bSortCellsTop: true
      ,responsive: false
      ,serverSide:false
      ,paging:true
      ,pagingType: "full_numbers"
      ,pageLength: 25
      ,lengthChange: true
      ,deferRender: true
      ,bDestroy: true
      ,dom: 'Brtip'
      ,bFilter: true 
      ,buttons: [ 
                  { 
                     extend: 'excelHtml5'
                     , footer: true
                     , charset: 'utf-8'
                     , bom: true
                     , fieldSeparator: ','
                     ,fieldBoundary: ''
                     ,exportOptions: {
                        columns: ':not(.notexport)'
                     }
                     ,customizeData: function(data, type, row) {
                           //console.log(data)
                           for(var i = 0; i < data.body.length; i++) {
                            console.log("custom")
                             data.body[i][9] = '\0' + data.body[i][9];
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
      
      , order: [[0, 'desc']],
   } );


   $('input.search').on('keyup change', function () {
      //e.preventDefault();
      console.log("Buscador");
      var rel = $(this).attr("rel");
      exportGeneralNoBuscar.columns(rel).search(this.value).draw();
   });

  
   


	$('#example1').DataTable({
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			lengthMenu: '_MENU_ items/page',
		}
	});
	$('#example2').DataTable({
		responsive: true,
		language: {
			searchPlaceholder: 'Search...',
			sSearch: '',
			lengthMenu: '_MENU_ items/page',
		}
	});
	$('#example3').DataTable( {
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal( {
                    header: function ( row ) {
                        var data = row.data();
                        return 'Details for '+data[0]+' '+data[1];
                    }
                } ),
                renderer: $.fn.dataTable.Responsive.renderer.tableAll( {
                    tableClass: 'table'
                } )
            }
        }
    } );
	
	/*Input Datatable*/
	 var table = $('#example-input').DataTable({
      'columnDefs': [
         {
            'targets': [1, 2, 3, 4, 5],
            'render': function(data, type, row, meta){
               if(type === 'display'){
                  var api = new $.fn.dataTable.Api(meta.settings);

                  var $el = $('input, select, textarea', api.cell({ row: meta.row, column: meta.col }).node());

                  var $html = $(data).wrap('<div/>').parent();

                  if($el.prop('tagName') === 'INPUT'){
                     $('input', $html).attr('value', $el.val());
                     if($el.prop('checked')){
                        $('input', $html).attr('checked', 'checked');
                     }
                  } else if ($el.prop('tagName') === 'TEXTAREA'){
                     $('textarea', $html).html($el.val());

                  } else if ($el.prop('tagName') === 'SELECT'){
                     $('option:selected', $html).removeAttr('selected');
                     $('option', $html).filter(function(){
                        return ($(this).attr('value') === $el.val());
                     }).attr('selected', 'selected');
                  }

                  data = $html.html();
               }

               return data;
            }
         }
      ],
      'responsive': true
   });
   $('#example-input tbody').on('keyup change', '.child input, .child select, .child textarea', function(e){
       var $el = $(this);
       var rowIdx = $el.closest('ul').data('dtr-index');
       var colIdx = $el.closest('li').data('dtr-index');
       var cell = table.cell({ row: rowIdx, column: colIdx }).node();
       $('input, select, textarea', cell).val($el.val());
       if($el.is(':checked')){
          $('input', cell).prop('checked', true);
       } else {
          $('input', cell).removeProp('checked');
       }
   });
   
   $('table').on('draw.dt', function() {
		$('.select2-no-search').select2({
			minimumResultsForSearch: Infinity,
			placeholder: 'Choose one'
		});
	});
	
});




