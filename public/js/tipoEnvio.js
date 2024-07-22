var costoSeguroGlobal = 0;
            // Calculo de peso Bascula
            function calculo(argument) {
                
                var pieza = $("#pieza").val()
                var peso = $("#peso").val()
                var alto = $("#alto").val()
                var ancho = $("#ancho").val()
                var largo = $("#largo").val()

                var bascula = peso*pieza
                var dimensional = ((alto*ancho*largo)/5000)*pieza

                $('#bascula').val(bascula)
                $('#dimensional').val(dimensional)
            }

            function calculoSeguro () {
                // EL seguro es el valor del envio sin I.V.A por el 20%
                costoSeguroGlobal = ( $("#valorEnvio").val() *0.02);
                $('#costoSeguro').val('$'+costoSeguroGlobal)
            }

            $(function(){
                $("#peso1").on("change keyup paste", function (){
                    calculo();
                });

                $("#alto1").on("change keyup paste", function (){
                    calculo();
                });

                $("#ancho1").on("change keyup paste ", function (){
                    calculo();
                });

                $("#largo1").on("change keyup paste", function (){
                    calculo();
                });

            });

            $(function(){
                $("#embalaje1").on("change", function (){
                    var embalaje = $(this).val()
                    
                    if (embalaje == 'sobre') {
                        $(".embalaje").hide()
                        $("#peso").removeAttr("required")
                        $("#alto").removeAttr("required")
                        $("#ancho").removeAttr("required")
                        $("#largo").removeAttr("required")
                    } else {
                        $(".embalaje").show()
                        $("#peso").attr("required","true");
                        $("#alto").attr("required","true");
                        $("#ancho").attr("required","true");
                        $("#largo").attr("required","true");
                    }
                });

            });

            // Seguro de envio
            $(function() {
                $('#checkSeguro').change(function() {
                    var checkSeguro = $(this).is( ":checked" )
                    if ( checkSeguro ) {
                        $(".seguro").show()
                        $("#valorEnvio").attr("required","true");
                    } else {
                        $(".seguro").hide()
                        $("#valorEnvio").removeAttr("required")
                    }
                  });
            });
            // fin Seguro de envio

            $("#valorEnvio").on("change keyup paste ", function (){
                calculoSeguro();
            });