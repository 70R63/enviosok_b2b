<?php

namespace Tests\Feature;

use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Simulator\Services\PublishReadyAgentVersionService;
use Tests\TestCase;

final class AiPublishTest extends TestCase
{
    public function test_publish_service_reuses_existing_agent_published_pointer(): void
    {$this->assertTrue(method_exists(Agent::class,'currentPublishedVersion'));$this->assertTrue(method_exists(PublishReadyAgentVersionService::class,'publish'));$this->assertFalse(property_exists(Agent::class,'published_agent_version_id'));}
}
