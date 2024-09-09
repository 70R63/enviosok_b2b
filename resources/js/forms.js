window.tipoValidacion = function(inputInvalido,msgDefault) {
    if (inputInvalido.validity.valueMissing){
        return msgDefault ?? 'Dato obligatorio';
    }
    if(inputInvalido.validity.patternMismatch){
        if (inputInvalido.type === 'text'){
            if(inputInvalido.name==="rfc")
                return 'Escribe un RFC válido. Ejemplo: EJEM900101XXX.'
            else
                return 'Campo inválido';
        }
    }
    if (inputInvalido.validity.tooLong){
        return "El límite de caracteres debe ser menor o igual a "+inputInvalido.maxLength;
    }
    if (inputInvalido.validity.tooShort){
        return "El mínimo de caracteres es de "+inputInvalido.minLength;
    }
    if (inputInvalido.validity.rangeOverflow){
        if (inputInvalido.type === 'date')
            return "La fecha debe ser igual o anterior a la actual";
        if (inputInvalido.type === 'number'){
            return msgDefault??"Este debe ser menor a "+inputInvalido.max;
        }
    }
    if (inputInvalido.validity.rangeUnderflow){
        if (inputInvalido.type === 'date'){
            if(inputInvalido.min){
                return "La fecha debe ser posterior a "+inputInvalido.min;
            }else{
                return "La fecha debe ser posterior a la actual";
            }
        }
    }
    if (inputInvalido.validity.typeMismatch) {
        if (inputInvalido.type === 'email')
            return 'Correo electrónico inválido';
        if (inputInvalido.type === 'date')
            return 'Fecha inválida';
        if (inputInvalido.type === 'time')
            return 'Hora inválida';
        return 'Tipo de dato incorrecto';
    }
    return 'Campo inválido';
}

window.validarFormulario = function (event){
    let form = event.target;
    if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        var inputsInvalidos = form.querySelectorAll(':invalid');
        inputsInvalidos.forEach(function (inputInvalido) {
            inputInvalido.closest('.form-group').classList.add('invalid');
            var invalidFeedbacks = inputInvalido.closest('.form-group').querySelectorAll('.invalid-feedback');
            invalidFeedbacks.forEach(function(el){
                el.textContent = tipoValidacion(inputInvalido,el.dataset.default);
                el.style.removeProperty('display');
            });
        });
    }
    form.classList.add('was-validated');
}

window.cambiarTab = function(tabId){
    var triggerEl = document.querySelector(`a[href="#${tabId}"]`);
    triggerEl.dispatchEvent(new Event('click'));
}

var forms = document.querySelectorAll('.needs-validation');
Array.prototype.slice.call(forms)
    .forEach(function (form) {
        form.addEventListener('submit', function (event) {
            validarFormulario(event);
        }, false);
    });

var formularioDatos = document.getElementById('formularioDatos');
var formularioDocumentacion = document.getElementById('formularioDocumentacion');
if(formularioDatos){
    formularioDatos.addEventListener('submit',function(e){
        e.preventDefault();
        validarFormulario(e);
        if(formularioDatos.checkValidity()){
            //var tabDocumentacion = document.querySelector(`a[href="#tabDocumentacion"]`);
            //tabDocumentacion.classList.remove('disabled');
            //cambiarTab('tabDocumentacion');
            formularioDatos.submit();
        }
    },false);
    // formularioDocumentacion.addEventListener('submit',function(e){
    //     e.preventDefault();
    //     validarFormulario(e);
    //     if(formularioDocumentacion.checkValidity()){
    //         Array.from(formularioDatos.elements).forEach(function(element) {
    //             if (element.name) {
    //                 var inputHidden = document.createElement('input');
    //                 inputHidden.type = 'hidden';
    //                 inputHidden.name = element.name;
    //                 inputHidden.value = element.value;
    //                 formularioDocumentacion.appendChild(inputHidden);
    //             }
    //         });
    //         formularioDocumentacion.submit();
    //     }
    // },false);
}

var documentos = document.querySelectorAll('.documento-registro');
Array.prototype.slice.call(documentos)
    .forEach(function (el) {
        el.addEventListener('change', function (event) {
            var archivo = event.target.files[0];
            // Valida si el archivo es una imagen
            if (archivo && archivo.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function() {
                    var output = document.getElementById(el.dataset.preview);
                    output.parentElement.querySelector('.col-12').innerHTML='';
                    output.src = reader.result;
                    output.style.display = 'block'; // Muestra el elemento <img>
                };
                reader.readAsDataURL(archivo);
            } else {
                alert('Por favor, selecciona un archivo de imagen.');
                // Opcional: Limpia el input file si el archivo no es una imagen
                event.target.value = '';
                // Opcional: Oculta el elemento <img> si estaba visible de una selección anterior
                var output = document.getElementById(el.dataset.preview);
                if (output) {
                    output.style.display = 'none';
                    output.parentElement.querySelector('.col-12').innerHTML=`<div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <strong>Holy guacamole!</strong> Por favor, selecciona un archivo de imagen.
                          <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>`;
                }
            }
        }, false);
    });
