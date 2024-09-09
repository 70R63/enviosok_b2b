
document.addEventListener('DOMContentLoaded', function () {
    //$('.selects').selectpicker();
    var cp = document.getElementById("cp");
    if(cp)cp.addEventListener("keyup", function(){
        if(this.value.length==5){
            setDomicilioAjax(this.value);
        }
    }, false);
    /*Ajax buscar domicilio*/
    window.setDomicilioAjax = function(cp,colonia=null){
        var token = document.head.querySelector('meta[name="csrf-token"]');
        var url = url_base;


        axios.post(url+'/api/domicilio',{
            cp: cp,
            _token: token.content
        })
            .then(response => {
                var domicilio=response.data.domicilio;
                var select=response.data.colonias;
                if((response.data.mensaje)&&(response.data.mensaje=="resultados")){
                    document.getElementById('estado').value=domicilio.d_estado;
                    document.getElementById('codigo_estado').value=domicilio.codigo_estado;
                    document.getElementById('municipio_alcaldia').value=domicilio.d_mnpio;
                    document.querySelector('.div-colonia-cp').innerHTML='';
                    document.querySelector('.div-colonia-cp').innerHTML=select;
                    //$('.div-colonia-cp>select').selectpicker({liveSearch:true});
                    /*Habilita input al seleccionar otra colonia*/
                    $('.select-colonia').on('change',function(e) {
                        if($(this).val()=='otra'){
                            document.querySelector('.div-colonia-cp').innerHTML='';
                            document.querySelector('.div-colonia-cp')
                                .innerHTML='<input type="text" class="form-control" placeholder="Escribe el nombre de otra colonia" name="colonia" id="colonia">';
                            $("#tipo_asentamiento").val('Colonia');
                            $('#colonia').focus();
                        }else{
                            let value = $(this).val();
                            let selected = document.querySelector(`option[value='${value}']`)
                            if(selected)
                                $("#tipo_asentamiento").val(selected.dataset.tipoasenta);
                        }
                    });
                    if(colonia) document.querySelector('#colonia').value=colonia;
                }else{
                    document.getElementById('estado').value='';
                    document.getElementById('codigo_estado').value='';
                    document.getElementById('municipio_alcaldia').value='';
                    document.querySelector('.div-colonia-cp').innerHTML='';
                    document.querySelector('.div-colonia-cp')
                        .innerHTML='<input type="text" class="form-control" placeholder="Escribe el nombre de la colonia" name="colonia" id="colonia">';
                }
            })
            .catch(e => {
                console.log(e);
            });
    }
});

