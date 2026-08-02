<?php

namespace App\Console\Commands;

use App\Services\ProductionCleanup\ZigoProductionCleanupReport;
use App\Services\ProductionCleanup\ZigoProductionCleanupService;
use Illuminate\Console\Command;
use Throwable;

class ZigoProductionCleanup extends Command
{
    protected $signature = 'zigo:prd-cleanup
        {--dry-run : Inventariar sin eliminar; comportamiento predeterminado}
        {--execute : Ejecutar el plan de eliminación}
        {--keep-admin=* : Correo de administrador que debe conservarse}
        {--purge-commercial-clients : Autoriza incluir clientes comerciales no protegidos}
        {--purge-api-clients : Autoriza incluir clientes API no protegidos}
        {--confirm= : Debe ser LIMPIAR-ZIGO para ejecutar}
        {--backup-confirmed : Confirma que existe respaldo verificado}';

    protected $description =
        'Inventaría y elimina datos de prueba de producción de forma controlada.';

    public function handle(
        ZigoProductionCleanupService $service,
        ZigoProductionCleanupReport $reportWriter
    ): int {
        $execute = (bool) $this->option('execute');
        $keepAdmins = (array) $this->option('keep-admin');

        if ($execute && $this->option('dry-run')) {
            $this->error('No combines --dry-run y --execute.');
            return self::FAILURE;
        }

        try {
            $report = $service->inventory(
                $keepAdmins,
                (bool) $this->option('purge-commercial-clients'),
                (bool) $this->option('purge-api-clients')
            );
            $report['mode'] = $execute ? 'execute-requested' : 'dry-run';

            if ($execute) {
                $guardBlocks = $this->executionGuardBlocks($keepAdmins);
                if ($guardBlocks !== []) {
                    $report['blocks'] = array_values(array_unique(array_merge(
                        $report['blocks'],
                        $guardBlocks
                    )));
                    $report['READY_TO_EXECUTE'] = false;
                }
            }

            $this->renderReport($report);

            if ($execute && $report['READY_TO_EXECUTE']) {
                $report = $service->execute($report);
                $this->info('Transacción confirmada; archivos candidatos procesados después del commit.');
            }

            $path = $reportWriter->save($report);
            $this->line('Reporte JSON: ' . $path);

            return $execute && !$report['READY_TO_EXECUTE']
                ? self::FAILURE
                : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Limpieza abortada: ' . $exception->getMessage());
            return self::FAILURE;
        }
    }

    private function executionGuardBlocks(array $keepAdmins): array
    {
        $blocks = [];
        if (!app()->environment('production')) $blocks[] = '--execute solo está permitido en ambiente production.';
        if ($this->option('confirm') !== 'LIMPIAR-ZIGO') $blocks[] = 'Falta --confirm="LIMPIAR-ZIGO".';
        if (!$this->option('backup-confirmed')) $blocks[] = 'Falta --backup-confirmed.';
        if ($keepAdmins === []) $blocks[] = 'Falta --keep-admin.';
        return $blocks;
    }

    private function renderReport(array $report): void
    {
        $this->newLine();
        $this->info('PROTEGIDOS');
        $this->table(['Entidad', 'Conteo'], $this->rows($report['protected_counts']));
        foreach ($report['protected_administrators'] as $email) $this->line('Administrador protegido: ' . $email);

        $this->newLine();
        $this->info('CANDIDATOS');
        $this->table(['Entidad', 'Conteo'], $this->rows($report['candidate_counts']));

        $this->newLine();
        $this->info('REVISAR');
        $report['review_counts'] === []
            ? $this->line('Sin entidades pendientes de revisión.')
            : $this->table(
                ['Entidad', 'Conteo'],
                $this->rows($report['review_counts'])
            );

        $this->newLine();
        $this->info('BLOQUEADOS');
        $report['blocks'] === [] ? $this->line('Sin bloqueos.') : collect($report['blocks'])->each(fn ($block) => $this->error('- ' . $block));

        $this->newLine();
        $this->info('ARCHIVOS');
        $this->line('Archivos candidatos: ' . count($report['candidate_files']));

        $this->newLine();
        $this->info('ADVERTENCIAS');
        $report['warnings'] === [] ? $this->line('Sin advertencias.') : collect($report['warnings'])->each(fn ($warning) => $this->warn('- ' . $warning));

        $this->newLine();
        $this->info('RESUMEN');
        $this->line('Ambiente: ' . $report['environment']);
        $equation = $report['user_equation'];
        $this->line(
            'users: protected=' . $equation['protected']
            . ' + candidate_internal=' . $equation['candidate_internal']
            . ' + candidate_b2b=' . $equation['candidate_b2b']
            . ' + candidate_b2c=' . $equation['candidate_b2c']
            . ' + blocked=' . $equation['blocked_unclassified']
            . ' = ' . $equation['partition_total']
            . ' / users_total=' . $equation['users_total']
        );
        $this->line('users_next_id = ' . $report['users_next_id']);
        $this->line('READY_TO_EXECUTE=' . ($report['READY_TO_EXECUTE'] ? 'true' : 'false'));
    }

    private function rows(array $values): array
    {
        return collect($values)->map(fn ($count, $name) => [$name, $count])->values()->all();
    }
}
