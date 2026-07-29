<?php

namespace App\Console\Commands;

use App\Models\B2cCotizacion;
use App\Services\Shipping\Xperta\XpertaContractComparatorService;
use Illuminate\Console\Command;
use Throwable;

class CompareXpertaContractRate extends Command
{
    protected $signature =
        'zigo:xperta-contract:compare
        {cotizacion_id : ID de cotización B2C}
        {--service=terrestre : terrestre o diasig}
        {--segment=b2c : Segmento comercial}
        {--send : Consultar Xperta sandbox}';

    protected $description =
        'Compara tarifa Xperta contra tarifa contractual ZIGO.';

    public function handle(
        XpertaContractComparatorService $comparator
    ): int {
        $cotizacion = B2cCotizacion::query()
            ->find(
                $this->argument('cotizacion_id')
            );

        if (!$cotizacion) {
            $this->error('Cotización no encontrada.');

            return self::FAILURE;
        }

        $service = (string) (
            $this->option('service')
            ?: 'terrestre'
        );

        $context = [
            'customer_segment' => (string) (
                $this->option('segment')
                ?: 'b2c'
            ),
            'user_id' => $cotizacion->user_id,
        ];

        try {
            if (!$this->option('send')) {
                $this->warn(
                    'MODO PREVIEW: no se consultará Xperta.'
                );

                $result = $comparator->previewContract(
                    $cotizacion,
                    $service,
                    $context
                );
            } else {
                $this->warn(
                    'CONSULTA REAL DE COTIZACIÓN AL SANDBOX XPERTA.'
                );

                $result = $comparator->compare(
                    $cotizacion,
                    $service,
                    $context
                );
            }

            $json = json_encode(
                $result,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );

            if ($json === false) {
                throw new \RuntimeException(
                    'No fue posible generar la salida JSON.'
                );
            }

            $this->line($json);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
