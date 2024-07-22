$(function() {
	'use strict'

	$('.select2').select2({
		placeholder: 'Selecciona ',
		
	});
	
	$('#generalForm').parsley();
	$('#enviosForm').parsley();
	$('#saldosPagosForm').parsley();
	$('#ajustesStoreForm').parsley();
	

});