<?php

namespace App\Console\Commands;

use App\Domain\Network\Billing\RecurringSubscriptionService;
use Illuminate\Console\Command;

final class ProcessAiBillingLifecycle extends Command
{
    protected $signature = 'ai:process-billing-lifecycle';
    protected $description = 'Process expired AI billing grace and period-end cancellations';

    public function handle(RecurringSubscriptionService $service): int
    {
        $this->info('Processed '.$service->processLifecycle().' AI billing subscription(s).');
        return self::SUCCESS;
    }
}
