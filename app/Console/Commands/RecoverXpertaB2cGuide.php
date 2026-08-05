<?php

namespace App\Console\Commands;

use App\Models\B2cCotizacion;
use App\Services\Shipping\B2cXpertaGuideFlowService;
use Illuminate\Console\Command;
use Throwable;

final class RecoverXpertaB2cGuide extends Command
{
    protected $signature = 'zigo:xperta-guide-recover
        {cotizacion_id : ID de la cotización B2C}
        {--dry-run : Validar sin generar la guía}
        {--confirm : Confirmar explícitamente la generación}';

    protected $description = 'Recupera de forma controlada una guía Xperta B2C pagada';

    public function handle(B2cXpertaGuideFlowService $flow): int
    {
        if (!$this->environmentAllowed()) {
            $this->error('Este comando sólo puede ejecutarse en production, stage o staging.');
            return self::FAILURE;
        }

        $cotizacion = B2cCotizacion::query()->find($this->argument('cotizacion_id'));
        if (!$cotizacion) {
            $this->error('Cotización no encontrada.');
            return self::FAILURE;
        }

        $this->line('Cotización: ' . $cotizacion->id);
        $this->line('Pago: ' . ($cotizacion->hasAccreditedPayment() ? 'confirmado' : 'no confirmado'));
        $this->line('Guía: ' . ($cotizacion->hasGeneratedGuide() ? 'existente' : 'ausente'));
        $this->line('Intentos: ' . (int) $cotizacion->guia_generation_attempts);

        if (!$cotizacion->hasAccreditedPayment()) {
            $this->error('La cotización no tiene un pago confirmado.');
            return self::FAILURE;
        }
        if ($cotizacion->hasGeneratedGuide()) {
            $this->info('La guía ya existe; no se generó una segunda guía.');
            return self::SUCCESS;
        }
        if ((int) $cotizacion->guia_generation_attempts >= (int) config('zigo_b2c_xperta.guide_max_attempts', 3)) {
            $this->error('La cotización alcanzó el máximo de intentos.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run completado; no se llamó a Xperta ni se modificó la cotización.');
            return self::SUCCESS;
        }

        if (!$this->option('confirm')) {
            $this->error('Agrega --confirm para ejecutar la recuperación.');
            return self::FAILURE;
        }

        try {
            $result = $flow->generateAfterConfirmedPayment($cotizacion);
            $result->forceFill(['guia_recovered_at' => now()])->save();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Guía disponible: ' . $result->tracking_number);
        return self::SUCCESS;
    }

    private function environmentAllowed(): bool
    {
        $appEnvironment = strtolower((string) app()->environment());
        $xpertaEnvironment = strtolower((string) config('services.xperta.environment'));
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            return in_array($xpertaEnvironment, ['production', 'stage', 'staging'], true);
        }
        return !in_array($appEnvironment, ['local', 'testing'], true)
            && in_array($xpertaEnvironment, ['production', 'stage', 'staging'], true);
    }
}
