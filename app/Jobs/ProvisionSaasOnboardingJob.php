<?php

namespace App\Jobs;

use App\Domain\Network\Onboarding\Services\SaasTenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProvisionSaasOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(public readonly int $onboardingApplicationId) {}

    public function handle(SaasTenantProvisioningService $provisioning): void
    {
        $provisioning->provision($this->onboardingApplicationId);
    }
}
