<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Negocio\Guias\EstafetaCreacion;
use App\Services\Shipping\Xperta\XpertaQuoteService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ZigoProviderRateService
{
    public function __construct(
        private XpertaQuoteService $xpertaQuoteService,
        private ZigoProviderRateEngineService $rateEngine
    ) {
    }

    public function getOptionsForCotizacion(
        B2cCotizacion $cotizacion
    ): array {
        if (
            config(
                'zigo_provider_rates'
                . '.engine_v2_enabled',
                false
            )
        ) {
            try {
                $options =
                    $this->rateEngine
                        ->getOptionsForCotizacion(
                            $cotizacion
                        );

                if ($options !== []) {
                    return $options;
                }
            } catch (Throwable $exception) {
                Log::error(
                    'ZIGO Provider Rate - '
                    . 'Error motor tarifario V2',
                    [
                        'cotizacion_id' =>
                            $cotizacion->id,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );

                if (
                    !config(
                        'zigo_provider_rates'
                        . '.fallback_to_legacy',
                        true
                    )
                ) {
                    throw $exception;
                }

                Log::warning(
                    'ZIGO Provider Rate - '
                    . 'Fallback al flujo anterior',
                    [
                        'cotizacion_id' =>
                            $cotizacion->id,
                    ]
                );
            }
        }

        return $this->getPreviousProviderOptions(
            $cotizacion
        );
    }

    private function getPreviousProviderOptions(
        B2cCotizacion $cotizacion
    ): array {
        $provider = strtolower(
            trim(
                (string) config(
                    'services.shipping.provider',
                    'legacy_estafeta'
                )
            )
        );

        if ($provider === 'xperta') {
            if (
                !config(
                    'services.xperta.enabled',
                    false
                )
            ) {
                throw new RuntimeException(
                    'Xperta está seleccionado, '
                    . 'pero XPERTA_ENABLED '
                    . 'no está activo.'
                );
            }

            try {
                return $this
                    ->xpertaQuoteService
                    ->options(
                        $cotizacion
                    );
            } catch (Throwable $exception) {
                Log::error(
                    'ZIGO Provider Rate - '
                    . 'Error Xperta',
                    [
                        'cotizacion_id' =>
                            $cotizacion->id,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );

                if (
                    !config(
                        'services.xperta'
                        . '.fallback_to_legacy',
                        false
                    )
                ) {
                    throw $exception;
                }

                Log::warning(
                    'ZIGO Provider Rate - '
                    . 'Fallback legacy habilitado',
                    [
                        'cotizacion_id' =>
                            $cotizacion->id,
                    ]
                );
            }
        }

        return [
            $this->getLegacyEstafetaOption(
                $cotizacion
            ),
        ];
    }

    private function getLegacyEstafetaOption(
        B2cCotizacion $cotizacion
    ): array {
        $basePrice =
            $this->getLegacyEstafetaBasePrice(
                $cotizacion
            );

        return [
            'logistico' => 'Estafeta',
            'logo' => 'img/estafeta.png',
            'servicio' => 'Terrestre',
            'entrega' => '2 a 5 días hábiles',
            'base_price' => $basePrice,
            'provider_source' =>
                'estafeta_solo_cotizacion',
            'extended_area' => false,
            'extended_area_amount' => 0.0,
            'rate_engine' => 'legacy',
        ];
    }

    private function getLegacyEstafetaBasePrice(
        B2cCotizacion $cotizacion
    ): float {
        try {
            $data =
                $this->buildLegacyPayload(
                    $cotizacion
                );

            $cotizador =
                new EstafetaCreacion();

            $cotizador->soloCotizacion(
                $data
            );

            $response =
                $cotizador->getResponse();

            $basePrice =
                $this->extractLegacyTotal(
                    $response
                );

            if ($basePrice <= 0) {
                throw new RuntimeException(
                    'La cotización Estafeta '
                    . 'no regresó un total válido.'
                );
            }

            return $basePrice;
        } catch (Throwable $exception) {
            Log::error(
                'ZIGO Provider Rate - '
                . 'Error cotizando Estafeta legacy',
                [
                    'cotizacion_id' =>
                        $cotizacion->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return 395.00;
        }
    }

    private function buildLegacyPayload(
        B2cCotizacion $cotizacion
    ): array {
        [$largo, $ancho, $alto] =
            $this->parseDimensions(
                $cotizacion->medidas
            );

        $valorDeclarado = (float) (
            $cotizacion->valor_declarado
            ?? 0
        );

        return [
            'canal' => 'API',
            'esManual' => 'API',
            'sucursal_id' => 0,
            'numero_solicitud' =>
                now()->timestamp,
            'ltd_id' => 2,
            'servicio_id' => (int) env(
                'B2C_ESTAFETA_SERVICIO_ID',
                1
            ),
            'empresa_id' => (int) env(
                'B2C_EMPRESA_ID',
                1
            ),
            'cp' =>
                $cotizacion->cp_origen,
            'cp_d' =>
                $cotizacion->cp_destino,
            'piezas' => 1,
            'peso' =>
                (float) $cotizacion->peso,
            'largo' => $largo,
            'ancho' => $ancho,
            'alto' => $alto,
            'valor_declarado' =>
                $valorDeclarado,
            'valor_envio' =>
                $valorDeclarado,
            'seguro' => 0,
            'bSeguro' => 'false',
            'tipoPagoId' => 1,
            'contenido' =>
                $cotizacion->contenido
                ?? 'Mercancía general',
        ];
    }

    private function parseDimensions(
        ?string $dimensions
    ): array {
        if (!$dimensions) {
            return [
                10.0,
                10.0,
                10.0,
            ];
        }

        $parts = preg_split(
            '/x|\*|,|;|\s+/',
            strtolower($dimensions)
        );

        $parts = array_values(
            array_filter(
                $parts,
                fn ($value) =>
                    $value !== ''
            )
        );

        if (count($parts) < 3) {
            return [
                10.0,
                10.0,
                10.0,
            ];
        }

        return [
            max(
                (float) $parts[0],
                1
            ),
            max(
                (float) $parts[1],
                1
            ),
            max(
                (float) $parts[2],
                1
            ),
        ];
    }

    private function extractLegacyTotal(
        $response
    ): float {
        if (
            is_array($response)
            && isset(
                $response[0]['total']
            )
        ) {
            return round(
                (float) $response[0]['total'],
                2
            );
        }

        if (
            is_array($response)
            && isset(
                $response['total']
            )
        ) {
            return round(
                (float) $response['total'],
                2
            );
        }

        throw new RuntimeException(
            'No se encontró total en la '
            . 'respuesta Estafeta legacy.'
        );
    }
}