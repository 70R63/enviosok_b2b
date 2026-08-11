<?php

namespace App\Console\Commands;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\SaasTenantProvisioningService;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileSaasOnboarding extends Command
{
    protected $signature = 'zigo:onboarding:reconcile
        {--uuid= : Procesa una solicitud específica}
        {--failed : Procesa sólo FAILED}
        {--paid : Procesa sólo PAID}
        {--limit=50 : Máximo de solicitudes}';

    protected $description = 'Reintenta provisioning SaaS ya pagado sin modificar pagos ni solicitudes canceladas.';

    public function handle(SaasTenantProvisioningService $provisioning): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $uuid = trim((string) $this->option('uuid'));
        if ($uuid !== '') {
            $applications = SaasOnboardingApplication::where('uuid', $uuid)->get();
        } else {
            $statuses = match (true) {
                (bool) $this->option('failed') && !(bool) $this->option('paid') => [SaasOnboardingApplication::FAILED],
                (bool) $this->option('paid') && !(bool) $this->option('failed') => [SaasOnboardingApplication::PAID],
                default => [SaasOnboardingApplication::PAID, SaasOnboardingApplication::FAILED],
            };
            $applications = SaasOnboardingApplication::whereIn('status', $statuses)
                ->orderBy('id')->limit($limit)->get();
        }

        if ($applications->isEmpty()) {
            $this->info('No hay onboardings elegibles.');
            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($applications as $application) {
            if ($application->status === SaasOnboardingApplication::CANCELLED) {
                $this->warn("{$application->uuid}: CANCELLED no es reintentable.");
                $failed++;
                continue;
            }
            try {
                $result = $provisioning->provision($application);
                $this->line("{$application->uuid}: {$result->status}");
                if ($result->status === SaasOnboardingApplication::FAILED) $failed++;
            } catch (Throwable $exception) {
                $this->error("{$application->uuid}: no procesable");
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
