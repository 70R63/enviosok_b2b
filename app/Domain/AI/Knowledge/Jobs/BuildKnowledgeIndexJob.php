<?php
namespace App\Domain\AI\Knowledge\Jobs;use App\Domain\AI\Jobs\TenantAwareAiJob;use App\Domain\AI\Knowledge\Services\BuildKnowledgeIndexService;use Illuminate\Container\Container;
final class BuildKnowledgeIndexJob extends TenantAwareAiJob{public function __construct(int$tenantId,private int$knowledgeIndexId){parent::__construct($tenantId);}protected function execute(Container$container):mixed{return$container->make(BuildKnowledgeIndexService::class)->build($this->knowledgeIndexId);}}
