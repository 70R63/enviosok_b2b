document.addEventListener('DOMContentLoaded', function () {
    document
        .querySelectorAll('[data-zigo-cotizador]')
        .forEach(inicializarCotizador);
});

function inicializarCotizador(root) {
    const form = root.querySelector('.quote-form');

    if (!form) {
        return;
    }

    const cpEndpoint =
        root.dataset.cpEndpoint ||
        '/b2c/cp/colonias';

    const tipoEnvio = root.querySelector('#tipo_envio');
    const peso = root.querySelector('#peso');

    const largo = root.querySelector('#largo');
    const alto = root.querySelector('#alto');
    const ancho = root.querySelector('#ancho');

    const medidas = root.querySelector('#medidas');
    const pesoCotizar = root.querySelector('#peso_cotizar');

    const pesoBox =
        root.querySelector('#peso_volumetrico_box');

    const pesoRealText =
        root.querySelector('#peso_real_text');

    const pesoVolText =
        root.querySelector('#peso_vol_text');

    const pesoCotizarText =
        root.querySelector('#peso_cotizar_text');

    const dimensionesField =
        root.querySelector('.field-dimensions');

    function numero(valor) {
        return parseFloat(
            String(valor || '').replace(',', '.')
        ) || 0;
    }

    function ocultarPesoCalculado() {
        if (pesoBox) {
            pesoBox.style.display = 'none';
        }

        if (pesoCotizar) {
            pesoCotizar.value = '';
        }

        if (pesoRealText) {
            pesoRealText.textContent = '0.00';
        }

        if (pesoVolText) {
            pesoVolText.textContent = '0.00';
        }

        if (pesoCotizarText) {
            pesoCotizarText.textContent = '0.00';
        }
    }

    function configurarTipoEnvio() {
        const esSobre =
            tipoEnvio &&
            tipoEnvio.value === 'sobre';

        [largo, alto, ancho].forEach(function (input) {
            if (!input) {
                return;
            }

            input.disabled = esSobre;
            input.required = !esSobre;
            input.style.opacity = esSobre ? '0.55' : '1';
            input.style.cursor =
                esSobre ? 'not-allowed' : 'text';

            if (esSobre) {
                input.value = '';
            }
        });

        if (dimensionesField) {
            dimensionesField.classList.toggle(
                'is-disabled',
                esSobre
            );
        }

        if (esSobre) {
            if (peso) {
                if (
                    peso.dataset.tipoSobreActivo !== 'true'
                ) {
                    peso.dataset.pesoCaja = peso.value;
                }

                peso.dataset.tipoSobreActivo = 'true';
                peso.value = '1.00';
                peso.readOnly = true;
                peso.style.opacity = '0.75';
                peso.style.cursor = 'not-allowed';
            }

            if (medidas) {
                medidas.value = '';
            }

            ocultarPesoCalculado();

            if (pesoCotizar) {
                pesoCotizar.value = '1.00';
            }

            return;
        }

        if (peso) {
            if (
                peso.dataset.tipoSobreActivo === 'true'
            ) {
                peso.value =
                    peso.dataset.pesoCaja || '';
            }

            peso.dataset.tipoSobreActivo = 'false';
            peso.readOnly = false;
            peso.style.opacity = '1';
            peso.style.cursor = 'text';
        }

        calcularPeso();
    }

    function calcularPeso() {
        if (
            tipoEnvio &&
            tipoEnvio.value === 'sobre'
        ) {
            configurarTipoEnvio();
            return;
        }

        const pesoReal = numero(peso?.value);
        const largoValor = numero(largo?.value);
        const altoValor = numero(alto?.value);
        const anchoValor = numero(ancho?.value);

        if (medidas) {
            medidas.value =
                largoValor &&
                altoValor &&
                anchoValor
                    ? `${largoValor}x${altoValor}x${anchoValor}`
                    : '';
        }

        if (
            !pesoReal ||
            !largoValor ||
            !altoValor ||
            !anchoValor
        ) {
            ocultarPesoCalculado();
            return;
        }

        const pesoVolumetrico =
            (
                largoValor *
                altoValor *
                anchoValor
            ) / 5000;

        const pesoFinal = Math.ceil(
            Math.max(
                pesoReal,
                pesoVolumetrico
            )
        );

        if (pesoRealText) {
            pesoRealText.textContent =
                pesoReal.toFixed(2);
        }

        if (pesoVolText) {
            pesoVolText.textContent =
                pesoVolumetrico.toFixed(2);
        }

        if (pesoCotizarText) {
            pesoCotizarText.textContent =
                pesoFinal.toFixed(2);
        }

        if (pesoCotizar) {
            pesoCotizar.value =
                pesoFinal.toFixed(2);
        }

        if (pesoBox) {
            pesoBox.style.display = 'grid';
        }
    }

    async function cargarColonias(configuracion) {
        const input =
            root.querySelector(
                '#' + configuracion.inputId
            );

        const lista =
            root.querySelector(
                '#' + configuracion.listaId
            );

        const mensaje =
            root.querySelector(
                '#' + configuracion.mensajeId
            );

        const colonia =
            root.querySelector(
                '#' + configuracion.coloniaId
            );

        const ciudad =
            root.querySelector(
                '#' + configuracion.ciudadId
            );

        const estado =
            root.querySelector(
                '#' + configuracion.estadoId
            );

        if (
            !input ||
            !lista ||
            !mensaje ||
            !colonia
        ) {
            return;
        }

        const cp = input.value
            .replace(/\D/g, '')
            .substring(0, 5);

        lista.innerHTML = '';
        lista.style.display = 'none';

        mensaje.textContent = '';
        mensaje.style.display = 'none';
        mensaje.classList.remove('is-error');

        colonia.value = '';

        if (ciudad) {
            ciudad.value = '';
        }

        if (estado) {
            estado.value = '';
        }

        if (cp.length !== 5) {
            return;
        }

        try {
            const respuesta = await fetch(
                `${cpEndpoint}?cp=${encodeURIComponent(cp)}`,
                {
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            const json = await respuesta.json();

            const registros = Array.isArray(json)
                ? json
                : json.data || [];

            if (
                !respuesta.ok ||
                !Array.isArray(registros) ||
                registros.length === 0
            ) {
                mensaje.textContent =
                    'Código postal no encontrado';

                mensaje.classList.add('is-error');
                mensaje.style.display = 'block';

                return;
            }

            registros.forEach(function (item) {
                const codigoPostal =
                    item.d_codigo ||
                    item.codigo_postal ||
                    cp;

                const nombreColonia =
                    item.d_asenta ||
                    item.colonia ||
                    '';

                const municipio =
                    item.D_mnpio ||
                    item.d_mnpio ||
                    item.municipio ||
                    item.d_ciudad ||
                    '';

                const nombreEstado =
                    item.d_estado ||
                    item.estado ||
                    '';

                const texto = [
                    codigoPostal,
                    nombreColonia,
                    municipio,
                    nombreEstado
                ]
                    .filter(Boolean)
                    .join(' - ');

                const opcion =
                    document.createElement('div');

                opcion.className =
                    'suggestion-item';

                opcion.innerHTML = `
                    <strong>${texto}</strong>
                    <br>
                    <small>${municipio}, ${nombreEstado}</small>
                `;

                opcion.addEventListener(
                    'click',
                    function () {
                        input.value = texto;
                        colonia.value = nombreColonia;

                        if (ciudad) {
                            ciudad.value = municipio;
                        }

                        if (estado) {
                            estado.value =
                                nombreEstado;
                        }

                        mensaje.textContent = '';
                        mensaje.style.display = 'none';
                        mensaje.classList.remove(
                            'is-error'
                        );

                        lista.innerHTML = '';
                        lista.style.display = 'none';
                    }
                );

                lista.appendChild(opcion);
            });

            lista.style.display = 'block';
        } catch (error) {
            console.error(
                'Error consultando código postal:',
                error
            );

            mensaje.textContent =
                'No fue posible consultar el código postal';

            mensaje.classList.add('is-error');
            mensaje.style.display = 'block';
        }
    }

    const configuracionesCp = [
        {
            inputId: 'cp_origen',
            listaId: 'colonias_origen_list',
            mensajeId: 'cp_origen_msg',
            coloniaId: 'colonia_origen',
            ciudadId: 'ciudad_origen',
            estadoId: 'estado_origen'
        },
        {
            inputId: 'cp_destino',
            listaId: 'colonias_destino_list',
            mensajeId: 'cp_destino_msg',
            coloniaId: 'colonia_destino',
            ciudadId: 'ciudad_destino',
            estadoId: 'estado_destino'
        }
    ];

    configuracionesCp.forEach(function (configuracion) {
        const input =
            root.querySelector(
                '#' + configuracion.inputId
            );

        if (!input) {
            return;
        }

        input.addEventListener(
            'input',
            function () {
                const cp = input.value
                    .replace(/\D/g, '')
                    .substring(0, 5);

                if (cp.length === 5) {
                    cargarColonias(configuracion);
                }
            }
        );
    });

    document.addEventListener(
        'click',
        function (event) {
            if (
                !event.target.closest(
                    '[data-zigo-cotizador] .autocomplete-wrap'
                )
            ) {
                root
                    .querySelectorAll('.suggestions')
                    .forEach(function (lista) {
                        lista.style.display = 'none';
                    });
            }
        }
    );

    [
        tipoEnvio,
        peso,
        largo,
        alto,
        ancho
    ].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener(
            'input',
            function () {
                if (input === tipoEnvio) {
                    configurarTipoEnvio();
                } else {
                    calcularPeso();
                }
            }
        );

        input.addEventListener(
            'change',
            function () {
                if (input === tipoEnvio) {
                    configurarTipoEnvio();
                } else {
                    calcularPeso();
                }
            }
        );
    });

    form.addEventListener(
        'submit',
        function () {
            if (
                tipoEnvio &&
                tipoEnvio.value === 'sobre'
            ) {
                configurarTipoEnvio();

                if (peso) {
                    peso.value = '1.00';
                }

                if (pesoCotizar) {
                    pesoCotizar.value = '1.00';
                }
            } else {
                calcularPeso();
            }

            const boton =
                form.querySelector(
                    'button[type="submit"]'
                );

            if (boton) {
                boton.disabled = true;
                boton.textContent =
                    'Cotizando...';
                boton.style.opacity = '.75';
                boton.style.cursor =
                    'not-allowed';
            }
        }
    );

    configurarTipoEnvio();
}