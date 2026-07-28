<?php

namespace App\Console\Commands;

use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\XpertaGuideService;
use Illuminate\Console\Command;
use Throwable;

class ProbeXpertaGuide extends Command
{
    protected $signature =
        'xperta:guide:probe
        {cotizacion_id : ID de la cotizaciÃ³n B2C}
        {--service= : terrestre o diasig}
        {--send : Enviar realmente la solicitud sandbox}';

    protected $description =
        'Previsualiza o envÃ­a una solicitud controlada de guÃ­a Xperta.';

    public function handle(
        XpertaGuideService $guideService
    ): int {
        $cotizacion = B2cCotizacion::query()
            ->find($this->argument('cotizacion_id'));

        if (!$cotizacion) {
            $this->error('CotizaciÃ³n no encontrada.');

            return self::FAILURE;
        }

        try {
            $service = $this->option('service') ?: null;

            $this->line(
                'Ruta: '
                . $guideService->resolvedPath(
                    $cotizacion,
                    $service
                )
            );

            if (!$this->option('send')) {
                $this->warn(
                    'MODO DRY-RUN: no se enviarÃ¡ ninguna guÃ­a.'
                );

                $this->line(
                    json_encode(
                        $guideService->buildPayload(
                            $cotizacion,
                            false
                        ),
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_PRESERVE_ZERO_FRACTION
                    )
                );

                return self::SUCCESS;
            }

            if (
                !config(
                    'services.xperta.guide_probe_enabled',
                    false
                )
            ) {
                $this->error(
                    'XPERTA_GUIDE_PROBE_ENABLED estÃ¡ desactivado.'
                );

                return self::FAILURE;
            }

            if (
                config('services.xperta.environment')
                !== 'sandbox'
            ) {
                $this->error(
                    'La prueba de guÃ­a solo estÃ¡ permitida en sandbox.'
                );

                return self::FAILURE;
            }

            if (
                strtoupper(
                    trim((string) $cotizacion->guia_estatus)
                ) === 'GENERADA'
                || trim(
                    (string) $cotizacion->tracking_number
                ) !== ''
            ) {
                $this->error(
                    'La cotizaciÃ³n ya tiene una guÃ­a registrada.'
                );

                return self::FAILURE;
            }

            if (
                !$this->confirm(
                    'Â¿Enviar una guÃ­a REAL al sandbox Xperta?',
                    false
                )
            ) {
                $this->warn('OperaciÃ³n cancelada.');

                return self::SUCCESS;
            }

            $response = $guideService->create(
                $cotizacion,
                $service
            );

            $this->info(
                'Xperta respondiÃ³ correctamente.'
            );

            $this->line(
                json_encode(
                    $response,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}