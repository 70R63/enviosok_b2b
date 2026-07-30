<?php

return [
    /*
     * Activa el motor tarifario basado en:
     * fuente, convenio, LTD, servicio y tarifario.
     */
    'engine_v2_enabled' => filter_var(
        env(
            'ZIGO_PROVIDER_RATE_ENGINE_V2_ENABLED',
            false
        ),
        FILTER_VALIDATE_BOOL
    ),

    /*
     * Conserva Estafeta legacy cuando el motor V2
     * no encuentra una tarifa aplicable o falla.
     */
    'fallback_to_legacy' => filter_var(
        env(
            'ZIGO_PROVIDER_RATE_FALLBACK_TO_LEGACY',
            true
        ),
        FILTER_VALIDATE_BOOL
    ),

    /*
     * Xperta permanece desactivado para cotización B2C
     * hasta completar las pruebas funcionales.
     */
    'allow_xperta_b2c' => filter_var(
        env(
            'ZIGO_PROVIDER_RATE_ALLOW_XPERTA_B2C',
            false
        ),
        FILTER_VALIDATE_BOOL
    ),
];