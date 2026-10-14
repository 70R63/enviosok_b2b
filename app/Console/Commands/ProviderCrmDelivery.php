<?php

namespace App\Console\Commands;

use App\Domain\Network\Providers\Crm\ProviderCrmAdapterResolver;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Providers\ProviderCrmOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProviderCrmDelivery extends Command
{
    protected $signature = 'network:provider-crm:deliver {--limit=100} {--resume=}';

    protected $description = 'Reconcile durable provider events and deliver registered adapters';

    public function handle(ProviderCrmOutbox $outbox): int
    {
        if (! Schema::hasTable('network_provider_integrations')) {
            return self::SUCCESS;
        }
        $limit = max(1, min(1000, (int) $this->option('limit')));
        foreach (ProviderIntegration::where('status', 'active')->get() as $i) {
            if ($this->option('resume') === $i->uuid && app(ProviderCrmAdapterResolver::class)->resolve($i)->enabled()) {
                DB::table('tenant_api_webhook_deliveries')->where('provider_integration_id', $i->id)->where('status', 'PR_DISABLED')->update(['status' => 'PR_PENDING', 'next_retry_at' => now()]);
            }
            ProviderFulfillment::whereIn('quote_snapshot_id', ProviderQuoteSnapshot::where('provider_connection_id', $i->provider_connection_id)->select('id'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tenant_api_webhook_deliveries')->whereColumn('provider_fulfillment_id', 'network_provider_fulfillments.id')->where('provider_integration_id', $i->id))
                ->orderBy('id')->limit($limit)->get()->each(fn ($b) => $outbox->publish($b));
        }
        foreach (DB::table('tenant_api_webhook_deliveries')->whereNotNull('provider_integration_id')->whereIn('status', ['PR_PENDING', 'PR_RETRY', 'PR_SENDING'])
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now()))
            ->orderBy('id')->limit($limit)->get() as $row) {
            $outbox->deliver($row->id);
        }

        return self::SUCCESS;
    }
}
