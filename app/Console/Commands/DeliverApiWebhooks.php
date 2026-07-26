<?php

namespace App\Console\Commands;

use App\Services\ApiHub\Webhooks\WebhookDeliveryService;
use Illuminate\Console\Command;

class DeliverApiWebhooks extends Command
{
    protected $signature = 'api-hub:webhooks:deliver
        {--limit=50 : Máximo de entregas a procesar}';

    protected $description =
        'Procesa entregas webhook pendientes del API Hub.';

    public function handle(
        WebhookDeliveryService $deliveryService
    ): int {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 500) {
            $this->error('El límite debe estar entre 1 y 500.');

            return self::FAILURE;
        }

        $result = $deliveryService->processDue($limit);

        $this->table(
            ['Seleccionadas', 'Entregadas', 'Reintento', 'Fallidas', 'Omitidas'],
            [[
                $result['selected'],
                $result['delivered'],
                $result['retry'],
                $result['failed'],
                $result['skipped'],
            ]]
        );

        return self::SUCCESS;
    }
}
