<?php
namespace App\Domain\AI\Channels\WhatsApp\Jobs;use App\Domain\AI\Jobs\TenantAwareAiJob;use App\Domain\AI\Channels\WhatsApp\Services\ProcessWhatsAppInboundService;use Illuminate\Container\Container;
final class ProcessWhatsAppInboundJob extends TenantAwareAiJob{public function __construct(int$tenantId,private int$receiptId){parent::__construct($tenantId);$this->tries=1;}protected function execute(Container$c):mixed{return$c->make(ProcessWhatsAppInboundService::class)->process($this->receiptId);}}
