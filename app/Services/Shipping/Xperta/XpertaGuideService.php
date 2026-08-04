<?php

namespace App\Services\Shipping\Xperta;

use App\Models\B2cCotizacion;
use RuntimeException;

class XpertaGuideService
{
    public function __construct(
        private XpertaApiClient $client,
        private XpertaTokenService $tokenService
    ) {
    }

    public function buildPayload(
        B2cCotizacion $cotizacion,
        bool $includeToken = true
    ): array {
        $this->validateCotizacion($cotizacion);

        [$length, $width, $height] =
            $this->parseDimensions($cotizacion);

        $weight = round(
            (float) (
                $cotizacion->peso_facturable
                ?: $cotizacion->peso_real
                ?: $cotizacion->peso
            ),
            2
        );

        $declaredValue = $cotizacion->requiere_seguro_envio
            ? round((float) $cotizacion->valor_declarado, 2)
            : null;

        return [
            'token' => $includeToken
                ? $this->tokenService->token()
                : '***TOKEN_BASE64***',

            'labelResponseOptions' => 'URL_ONLY',

            'requestedShipment' => [
                'shipper' => $this->party(
                    (string) $cotizacion->remitente_nombre,
                    (string) $cotizacion->remitente_telefono,
                    (string) $cotizacion->remitente_direccion,
                    (string) $cotizacion->remitente_num_ext,
                    (string) ($cotizacion->remitente_num_int ?? ''),
                    (string) $cotizacion->colonia_origen,
                    (string) $cotizacion->ciudad_origen,
                    (string) $cotizacion->estado_origen,
                    (string) $cotizacion->cp_origen
                ),

                'recipients' => [
                    $this->party(
                        (string) $cotizacion->destinatario_nombre,
                        (string) $cotizacion->destinatario_telefono,
                        (string) $cotizacion->destinatario_direccion,
                        (string) $cotizacion->destinatario_num_ext,
                        (string) ($cotizacion->destinatario_num_int ?? ''),
                        (string) $cotizacion->colonia_destino,
                        (string) $cotizacion->ciudad_destino,
                        (string) $cotizacion->estado_destino,
                        (string) $cotizacion->cp_destino
                    ),
                ],

                'requestedPackageLineItems' => [
                    [
                        'groupPackageCount' => '1',
                        'itemDescriptionForClearance' =>
                            trim((string) $cotizacion->contenido),

                        'declaredValue' => [
                            'amount' => $declaredValue,
                            'currency' => (string) config(
                                'services.xperta.currency',
                                'NMP'
                            ),
                        ],

                        'weight' => [
                            'units' => 'KG',
                            'value' => (string) $weight,
                        ],

                        'dimensiones' => [
                            'alto' => $height,
                            'ancho' => $width,
                            'largo' => $length,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function create(
        B2cCotizacion $cotizacion,
        ?string $service = null
    ): array {
        return $this->createWithMeta($cotizacion, $service)['data'];
    }

    public function createWithMeta(
        B2cCotizacion $cotizacion,
        ?string $service = null
    ): array {
        if (!config('zigo_b2c_xperta.guide_enabled', false)) {
            throw new RuntimeException(
                'La generación de guía Xperta está desactivada.'
            );
        }

        $service = $this->normalizeService(
            $service ?: (string) $cotizacion->servicio
        );

        $path = $this->resolvedPath($cotizacion, $service);

        return $this->client->sendWithMeta(
            'POST',
            $path,
            $this->buildPayload($cotizacion),
            $this->providerHeaders(),
            true
        );
    }

    public function resolvedPath(
        B2cCotizacion $cotizacion,
        ?string $service = null
    ): string {
        $service = $this->normalizeService(
            $service ?: (string) $cotizacion->servicio
        );

        return $this->client->resolvePath(
            (string) config(
                'services.xperta.guide_path',
                '/api/v1/empresas/{corporativo}/ltds/{ltd}/servicios/{service}/guia'
            ),
            [
                'corporativo' => config('services.xperta.corporativo'),
                'empresa' => config('services.xperta.corporativo'),
                'ltd' => config('services.xperta.ltd', 'estafeta'),
                'service' => $service,
            ]
        );
    }

    private function validateCotizacion(
        B2cCotizacion $cotizacion
    ): void {
        if (!$cotizacion->hasCompleteShippingAddresses()) {
            throw new RuntimeException(
                'La cotizaciÃ³n no tiene direcciones completas.'
            );
        }

        if (!$cotizacion->hasCompletePackageData()) {
            throw new RuntimeException(
                'La cotizaciÃ³n no tiene datos completos del paquete.'
            );
        }

        foreach (
            [
                'ciudad_origen' => $cotizacion->ciudad_origen,
                'estado_origen' => $cotizacion->estado_origen,
                'ciudad_destino' => $cotizacion->ciudad_destino,
                'estado_destino' => $cotizacion->estado_destino,
            ] as $field => $value
        ) {
            if (trim((string) $value) === '') {
                throw new RuntimeException(
                    'Falta completar el campo ' . $field . '.'
                );
            }
        }
    }

    private function party(
        string $name,
        string $phone,
        string $street,
        string $externalNumber,
        string $internalNumber,
        string $settlement,
        string $city,
        string $state,
        string $postalCode
    ): array {
        $streetLine = trim(
            implode(', ', array_filter([
                trim($street),
                trim($externalNumber) !== ''
                    ? 'Ext. ' . trim($externalNumber)
                    : null,
                trim($internalNumber) !== ''
                    ? 'Int. ' . trim($internalNumber)
                    : null,
                trim($settlement),
            ]))
        );

        return [
            'contact' => [
                'personName' => mb_substr(trim($name), 0, 70),
                'phoneNumber' => mb_substr(
                    preg_replace('/\D/', '', $phone),
                    0,
                    15
                ),
                'companyName' => mb_substr(trim($name), 0, 70),
            ],
            'address' => [
                'streetLines' => [
                    mb_substr($streetLine, 0, 150),
                ],
                'city' => mb_substr(trim($city), 0, 50),
                'stateOrProvinceCode' =>
                    $this->stateCode($state),
                'postalCode' => substr(
                    preg_replace('/\D/', '', $postalCode),
                    0,
                    5
                ),
                'countryCode' => 'MX',
            ],
        ];
    }

    private function stateCode(string $state): string
    {
        $source = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $state
        ) ?: $state;

        $normalized = mb_strtoupper(
            trim(str_replace('.', '', $source))
        );

        $codes = [
            'AGUASCALIENTES' => 'AG',
            'BAJA CALIFORNIA' => 'BC',
            'BAJA CALIFORNIA SUR' => 'BS',
            'CAMPECHE' => 'CM',
            'CHIAPAS' => 'CS',
            'CHIHUAHUA' => 'CH',
            'COAHUILA' => 'CO',
            'COAHUILA DE ZARAGOZA' => 'CO',
            'COLIMA' => 'CL',
            'CIUDAD DE MEXICO' => 'DF',
            'DISTRITO FEDERAL' => 'DF',
            'DURANGO' => 'DG',
            'GUANAJUATO' => 'GT',
            'GUERRERO' => 'GR',
            'HIDALGO' => 'HG',
            'JALISCO' => 'JA',
            'MEXICO' => 'ME',
            'ESTADO DE MEXICO' => 'ME',
            'MICHOACAN' => 'MI',
            'MICHOACAN DE OCAMPO' => 'MI',
            'MORELOS' => 'MO',
            'NAYARIT' => 'NA',
            'NUEVO LEON' => 'NL',
            'OAXACA' => 'OA',
            'PUEBLA' => 'PU',
            'QUERETARO' => 'QT',
            'QUINTANA ROO' => 'QR',
            'SAN LUIS POTOSI' => 'SL',
            'SINALOA' => 'SI',
            'SONORA' => 'SO',
            'TABASCO' => 'TB',
            'TAMAULIPAS' => 'TM',
            'TLAXCALA' => 'TL',
            'VERACRUZ' => 'VE',
            'VERACRUZ DE IGNACIO DE LA LLAVE' => 'VE',
            'YUCATAN' => 'YU',
            'ZACATECAS' => 'ZA',
        ];

        if (isset($codes[$normalized])) {
            return $codes[$normalized];
        }

        if (preg_match('/^[A-Z]{2,3}$/', $normalized)) {
            return $normalized;
        }

        throw new RuntimeException(
            'No existe cÃ³digo de estado Xperta para: ' . $state
        );
    }

    private function parseDimensions(
        B2cCotizacion $cotizacion
    ): array {
        if (
            strtolower((string) $cotizacion->tipo_envio) === 'sobre'
            || !$cotizacion->medidas
        ) {
            return [0.1, 0.1, 0.1];
        }

        $parts = preg_split(
            '/x|\*|,|;|\s+/',
            strtolower((string) $cotizacion->medidas)
        );

        $parts = array_values(
            array_filter(
                $parts,
                fn ($value) => $value !== ''
            )
        );

        if (count($parts) < 3) {
            throw new RuntimeException(
                'Las dimensiones no tienen formato largo x ancho x alto.'
            );
        }

        return [
            max(round((float) $parts[0], 2), 0.1),
            max(round((float) $parts[1], 2), 0.1),
            max(round((float) $parts[2], 2), 0.1),
        ];
    }

    private function normalizeService(string $service): string
    {
        $normalized = mb_strtolower(trim($service));

        return match (true) {
            str_contains($normalized, 'dia sig'),
            str_contains($normalized, 'dÃ­a sig'),
            str_contains($normalized, 'siguiente') => 'diasig',

            str_contains($normalized, 'terrestre') => 'terrestre',

            $normalized === 'diasig',
            $normalized === 'terrestre' => $normalized,

            default => throw new RuntimeException(
                'Servicio Xperta no reconocido: ' . $service
            ),
        };
    }

    private function providerHeaders(): array
    {
        $headers = [
            'Corporativo' => (string) config(
                'services.xperta.corporativo'
            ),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (
            config(
                'services.xperta.send_api_key_on_operations',
                true
            )
        ) {
            $headers['x-api-key'] =
                (string) config('services.xperta.api_key');
        }

        return $headers;
    }
}
