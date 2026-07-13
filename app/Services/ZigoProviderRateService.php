<?php

namespace App\Services;

use App\Models\B2cCotizacion;
use App\Negocio\Guias\EstafetaCreacion;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZigoProviderRateService
{
    public function getOptionsForCotizacion(B2cCotizacion $cotizacion): array
    {
        return [
            $this->getEstafetaOption($cotizacion),
        ];
    }

    private function getEstafetaOption(B2cCotizacion $cotizacion): array
    {
        $basePrice = $this->getEstafetaBasePrice($cotizacion);

        return [
            'logistico' => 'Estafeta',
            'logo' => 'img/estafeta.png',
            'servicio' => 'Terrestre',
            'entrega' => '2 a 5 días hábiles',
            'base_price' => $basePrice,
            'provider_source' => 'estafeta_solo_cotizacion',
        ];
    }

    private function getEstafetaBasePrice(B2cCotizacion $cotizacion): float
    {
        try {
            $data = $this->buildEstafetaCotizacionPayload($cotizacion);

            $cotizador = new EstafetaCreacion();
            $cotizador->soloCotizacion($data);

            $response = $cotizador->getResponse();

            Log::info('ZIGO Provider Rate - Estafeta response', [
                'cotizacion_id' => $cotizacion->id,
                'response' => $response,
            ]);

            $basePrice = $this->extractEstafetaTotal($response);

            Log::info('ZIGO Provider Rate - Estafeta base price', [
                'cotizacion_id' => $cotizacion->id,
                'base_price' => $basePrice,
            ]);

            if ($basePrice <= 0) {
                throw new RuntimeException('La cotización Estafeta no regresó un total válido.');
            }

            return $basePrice;
        } catch (\Throwable $e) {
            Log::error('ZIGO Provider Rate - Error cotizando Estafeta', [
                'cotizacion_id' => $cotizacion->id,
                'message' => $e->getMessage(),
            ]);

            /*
             * Fallback temporal para no romper el flujo en DEV.
             * Cuando la cotización real esté estable, este fallback se puede quitar
             * o cambiar por una excepción.
             */
            return $this->fallbackBasePrice($cotizacion);
        }
    }

    private function buildEstafetaCotizacionPayload(B2cCotizacion $cotizacion): array
    {
        [$largo, $ancho, $alto] = $this->parseMedidas($cotizacion->medidas);

        $valorDeclarado = (float) ($cotizacion->valor_declarado ?? 0);

        return [
            'canal' => 'API',
            'esManual' => 'API',
            'sucursal_id' => 0,
            'numero_solicitud' => now()->timestamp,

            // Estafeta
            'ltd_id' => 2,
            'servicio_id' => (int) env('B2C_ESTAFETA_SERVICIO_ID', 1),

            // Empresa ZIGO / cuenta base para cotizar
            'empresa_id' => (int) env('B2C_EMPRESA_ID', 1),

            // Origen / destino
            'cp' => $cotizacion->cp_origen,
            'cp_d' => $cotizacion->cp_destino,

            // Paquete
            'piezas' => 1,
            'peso' => (float) $cotizacion->peso,
            'largo' => $largo,
            'ancho' => $ancho,
            'alto' => $alto,

            // Seguro / declarado
            'valor_declarado' => $valorDeclarado,
            'valor_envio' => $valorDeclarado,

           /*
            * El cálculo legacy de Estafeta puede considerar seguro con base en valor_declarado.
            * Por ahora se conserva dentro del precio base proveedor, para que ZIGO aplique
            * su margen sobre el costo real total de la guía.
            */
            'seguro' => 0,
            'bSeguro' => 'false',

            // Pago no debe afectar cotización B2C pública
            'tipoPagoId' => 1,

            // Campos mínimos usados por algunos parseos legacy
            'contenido' => $cotizacion->contenido ?? 'Mercancía general',
        ];
    }

    private function parseMedidas(?string $medidas): array
    {
        $default = [10.0, 10.0, 10.0];

        if (!$medidas) {
            return $default;
        }

        $parts = preg_split('/x|\*|,|;|\s+/', strtolower($medidas));
        $parts = array_values(array_filter($parts, fn ($value) => $value !== ''));

        if (count($parts) < 3) {
            return $default;
        }

        return [
            max((float) $parts[0], 1),
            max((float) $parts[1], 1),
            max((float) $parts[2], 1),
        ];
    }

    private function extractEstafetaTotal($response): float
    {
        /*
         * EstafetaCreacion::resumenCotizacion() regresa un arreglo:
         * [
         *   [
         *     costo,
         *     sub_total,
         *     total
         *   ]
         * ]
         */

        if (is_array($response) && isset($response[0]['total'])) {
            return round((float) $response[0]['total'], 2);
        }

        if (is_array($response) && isset($response['total'])) {
            return round((float) $response['total'], 2);
        }

        throw new RuntimeException('No se encontró total en la respuesta de cotización Estafeta.');
    }

    private function fallbackBasePrice(B2cCotizacion $cotizacion): float
    {
        return 395.00;
    }
}