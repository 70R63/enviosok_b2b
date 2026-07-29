<?php

return [
    /*
     * provider:
     * Conserva el costo regresado por el proveedor actual.
     *
     * contract:
     * Utiliza zigo_contract_rates como costo logístico.
     */
    'base_rate_source' => strtolower(
        (string) env(
            'ZIGO_B2C_BASE_RATE_SOURCE',
            'provider'
        )
    ),

    /*
     * Cuando contract no encuentra una tarifa aplicable,
     * permite conservar la cotización del proveedor.
     */
    'contract_fallback_to_provider' => filter_var(
        env(
            'ZIGO_CONTRACT_FALLBACK_TO_PROVIDER',
            true
        ),
        FILTER_VALIDATE_BOOL
    ),
];
