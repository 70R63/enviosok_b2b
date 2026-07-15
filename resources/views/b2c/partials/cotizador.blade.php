@php
    $cotizadorAction = $cotizadorAction ?? route('b2c.cotizar');
    $cotizadorPublico = $cotizadorPublico ?? false;
    $cotizacionActual = $cotizacionActual ?? null;
    $limpiarRoute = $limpiarRoute ?? null;

    $origenVisible = old(
        'cp_origen',
        $cotizacionActual
            ? implode(' - ', array_filter([
                $cotizacionActual->cp_origen,
                $cotizacionActual->colonia_origen,
                $cotizacionActual->ciudad_origen,
                $cotizacionActual->estado_origen,
            ]))
            : ''
    );

    $destinoVisible = old(
        'cp_destino',
        $cotizacionActual
            ? implode(' - ', array_filter([
                $cotizacionActual->cp_destino,
                $cotizacionActual->colonia_destino,
                $cotizacionActual->ciudad_destino,
                $cotizacionActual->estado_destino,
            ]))
            : ''
    );

    $medidasActuales = old(
        'medidas',
        $cotizacionActual->medidas ?? ''
    );

    $partesMedidas = preg_split(
        '/x|\*|,|;|\s+/',
        strtolower((string) $medidasActuales)
    );

    $partesMedidas = array_values(
        array_filter(
            $partesMedidas,
            fn ($valor) => $valor !== ''
        )
    );

    $partesMedidas = array_pad($partesMedidas, 3, '');

    $largoActual = $partesMedidas[0] ?? '';
    $altoActual = $partesMedidas[1] ?? '';
    $anchoActual = $partesMedidas[2] ?? '';

    $tipoActual = old(
        'tipo_envio',
        $cotizacionActual->tipo_envio ?? 'caja'
    );
@endphp

<section
    class="quote-box"
    id="cotizar"
    data-zigo-cotizador
    data-cp-endpoint="{{ url('/b2c/cp/colonias') }}"
>
    <div class="quote-card">
        <div class="quote-title">
            Cotiza gratis tu envío
        </div>

        @if($cotizadorPublico && session('login_required'))
            <div class="landing-alert">
                Para continuar con envíos tipo caja necesitas
                <a href="{{ route('login') }}">iniciar sesión</a>
                o
                <a href="{{ route('b2c.register') }}">crear una cuenta</a>.
            </div>
        @endif

        @if(session('error'))
            <div class="quote-error">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="quote-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form
            class="quote-form"
            method="POST"
            action="{{ $cotizadorAction }}"
        >
            @csrf

            <div class="field autocomplete-wrap">
                <label>Origen</label>

                <input
                    type="text"
                    id="cp_origen"
                    name="cp_origen"
                    value="{{ $origenVisible }}"
                    placeholder="Código postal origen"
                    maxlength="120"
                    autocomplete="off"
                    required
                >

                <input
                    type="hidden"
                    id="colonia_origen"
                    name="colonia_origen"
                    value="{{ old('colonia_origen', $cotizacionActual->colonia_origen ?? '') }}"
                >

                <input
                    type="hidden"
                    id="ciudad_origen"
                    name="ciudad_origen"
                    value="{{ old('ciudad_origen', $cotizacionActual->ciudad_origen ?? '') }}"
                >

                <input
                    type="hidden"
                    id="estado_origen"
                    name="estado_origen"
                    value="{{ old('estado_origen', $cotizacionActual->estado_origen ?? '') }}"
                >

                <div
                    id="colonias_origen_list"
                    class="suggestions"
                ></div>

                <small
                    id="cp_origen_msg"
                    class="cp-help"
                ></small>
            </div>

            <div class="field autocomplete-wrap">
                <label>Destino</label>

                <input
                    type="text"
                    id="cp_destino"
                    name="cp_destino"
                    value="{{ $destinoVisible }}"
                    placeholder="Código postal destino"
                    maxlength="120"
                    autocomplete="off"
                    required
                >

                <input
                    type="hidden"
                    id="colonia_destino"
                    name="colonia_destino"
                    value="{{ old('colonia_destino', $cotizacionActual->colonia_destino ?? '') }}"
                >

                <input
                    type="hidden"
                    id="ciudad_destino"
                    name="ciudad_destino"
                    value="{{ old('ciudad_destino', $cotizacionActual->ciudad_destino ?? '') }}"
                >

                <input
                    type="hidden"
                    id="estado_destino"
                    name="estado_destino"
                    value="{{ old('estado_destino', $cotizacionActual->estado_destino ?? '') }}"
                >

                <div
                    id="colonias_destino_list"
                    class="suggestions"
                ></div>

                <small
                    id="cp_destino_msg"
                    class="cp-help"
                ></small>
            </div>

            <div class="field">
                <label>Tipo de envío</label>

                <select
                    name="tipo_envio"
                    id="tipo_envio"
                    required
                >
                    <option
                        value="caja"
                        @selected($tipoActual === 'caja')
                    >
                        Caja
                    </option>

                    <option
                        value="sobre"
                        @selected($tipoActual === 'sobre')
                    >
                        Sobre
                    </option>
                </select>
            </div>

            <div class="field">
                <label>Peso (kg)</label>

                <input
                    type="number"
                    id="peso"
                    name="peso"
                    placeholder="Kg"
                    min="0.1"
                    step="0.1"
                    value="{{ old('peso', $cotizacionActual->peso ?? '') }}"
                    required
                >
            </div>

            <div class="field field-dimensions">
                <label>Tamaño de caja en (cm)</label>

                <div class="box-dimensions">
                    <input
                        type="number"
                        id="largo"
                        placeholder="Largo"
                        min="1"
                        step="0.1"
                        value="{{ old('largo', $largoActual) }}"
                    >

                    <input
                        type="number"
                        id="alto"
                        placeholder="Alto"
                        min="1"
                        step="0.1"
                        value="{{ old('alto', $altoActual) }}"
                    >

                    <input
                        type="number"
                        id="ancho"
                        placeholder="Ancho"
                        min="1"
                        step="0.1"
                        value="{{ old('ancho', $anchoActual) }}"
                    >
                </div>

                <input
                    type="hidden"
                    id="medidas"
                    name="medidas"
                    value="{{ $medidasActuales }}"
                >
            </div>

            <input
                type="hidden"
                id="peso_cotizar"
                name="peso_cotizar"
                value="{{ old('peso_cotizar') }}"
            >

            <div
                id="peso_volumetrico_box"
                class="peso-volumetrico-box"
                style="display:none;"
            >
                <div>
                    <strong>Peso real:</strong>
                    <span id="peso_real_text">0.00</span> kg
                </div>

                <div>
                    <strong>Peso volumétrico:</strong>
                    <span id="peso_vol_text">0.00</span> kg
                </div>

                <div>
                    <strong>Peso a cotizar:</strong>
                    <span id="peso_cotizar_text">0.00</span> kg
                </div>
            </div>

            <button
                class="btn-yellow"
                type="submit"
            >
                Cotizar envío
            </button>
        </form>

        @if(
            $limpiarRoute &&
            (
                $cotizacionActual ||
                session('login_required')
            )
        )
            <div class="quote-reset-wrap">
                <a
                    href="{{ $limpiarRoute }}"
                    class="quote-reset-link"
                >
                    Limpiar cotización
                </a>
            </div>
        @endif

        @if(
            $cotizadorPublico &&
            isset($cotizacion_id) &&
            isset($opciones)
        )
            <div class="quote-options-inline">
                <h2>Opciones disponibles</h2>

                @foreach($opciones as $opcion)
                    <form
                        method="POST"
                        action="{{ route('b2c.seleccionar', $cotizacion_id) }}"
                        class="quote-option-row"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="logistico"
                            value="{{ $opcion['logistico'] }}"
                        >

                        <input
                            type="hidden"
                            name="servicio"
                            value="{{ $opcion['servicio'] }}"
                        >

                        <div>
                            <strong>{{ $opcion['logistico'] }}</strong>
                            <div>{{ $opcion['servicio'] }}</div>
                        </div>

                        <div>{{ $opcion['entrega'] }}</div>

                        <div class="quote-option-price">
                            ${{ number_format($opcion['precio'], 2) }}
                        </div>

                        <button type="submit">
                            Seleccionar
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</section>