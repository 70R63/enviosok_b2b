document.addEventListener('DOMContentLoaded', function () {
    function setupAutocomplete(inputId, hiddenId, listId, ciudadId, estadoId) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const list = document.getElementById(listId);
        const ciudadHidden = document.getElementById(ciudadId);
        const estadoHidden = document.getElementById(estadoId);

        const coloniaLabel = document.getElementById(hiddenId + '_label');
        const ciudadLabel = document.getElementById(ciudadId + '_label');
        const estadoLabel = document.getElementById(estadoId + '_label');

        if (!input || !hidden || !list) return;

        input.addEventListener('input', function () {
            const cp = input.value.trim().substring(0, 5);

            hidden.value = '';
            if (ciudadHidden) ciudadHidden.value = '';
            if (estadoHidden) estadoHidden.value = '';

            if (coloniaLabel) coloniaLabel.value = '';
            if (ciudadLabel) ciudadLabel.value = '';
            if (estadoLabel) estadoLabel.value = '';

            list.innerHTML = '';

            if (cp.length < 5) return;

            fetch(`/b2c/cp/colonias?cp=${encodeURIComponent(cp)}`)
                .then(response => response.json())
                .then(response => {
                    list.innerHTML = '';

                    const rows = Array.isArray(response) ? response : (response.data || []);

                    if (!rows.length) {
                        list.innerHTML = '<div class="suggestion-item">Sin colonias encontradas</div>';
                        return;
                    }

                    rows.forEach(item => {
                        const codigoPostal = item.d_codigo || cp;
                        const colonia = item.d_asenta || item.colonia || '';
                        const municipio = item.D_mnpio || item.d_mnpio || item.municipio || item.d_ciudad || '';
                        const estado = item.d_estado || item.estado || item.codigo_estado || '';

                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = `${codigoPostal} - ${colonia} - ${municipio} - ${estado}`;

                        div.addEventListener('click', function () {
                            input.value = codigoPostal;

                            hidden.value = colonia;
                            if (ciudadHidden) ciudadHidden.value = municipio;
                            if (estadoHidden) estadoHidden.value = estado;

                            if (coloniaLabel) coloniaLabel.value = colonia;
                            if (ciudadLabel) ciudadLabel.value = municipio;
                            if (estadoLabel) estadoLabel.value = estado;

                            list.innerHTML = '';
                        });

                        list.appendChild(div);
                    });
                })
                .catch(error => {
                    console.error('Error consultando colonias:', error);
                    list.innerHTML = '<div class="suggestion-item">Error consultando colonias</div>';
                });
        });
    }

    setupAutocomplete('cp_origen', 'colonia_origen', 'colonias_origen_list', 'ciudad_origen', 'estado_origen');
    setupAutocomplete('cp_destino', 'colonia_destino', 'colonias_destino_list', 'ciudad_destino', 'estado_destino');
});