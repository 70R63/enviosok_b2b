<?php

namespace Tests\Feature;

use App\Domain\AI\Leads\Data\LeadOutcomePolicy;
use App\Domain\AI\Runtime\Data\AgentRuntimeResponseData;
use App\Domain\AI\Runtime\Exceptions\InvalidModelResponseException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AiLeadOutcomeFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::statement('PRAGMA foreign_keys=ON');
        $this->prerequisites();
        $this->migration()->up();
    }

    public function test_migration_up_down_up_and_tenant_aware_constraints(): void
    {
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertContains('ai_leads_conversation_uq', collect(DB::select("PRAGMA index_list('ai_leads')"))->pluck('name'));
        $this->assertContains('ai_leads_outcome_identity_uq', collect(DB::select("PRAGMA index_list('ai_leads')"))->pluck('name'));
        $this->assertNotEmpty(DB::select("PRAGMA foreign_key_list('ai_outcome_events')"));
        $migration = $this->migration();
        $migration->down();
        $migration->up();
        $this->assertTrue(Schema::hasTable('ai_leads'));
        $this->assertTrue(Schema::hasTable('ai_outcome_events'));
    }

    public function test_outcome_lead_reference_requires_same_tenant_and_conversation_but_remains_nullable(): void
    {
        DB::table('network_tenants')->insert(['id' => 1]);
        DB::table('ai_agents')->insert(['id' => 1, 'tenant_id' => 1]);
        DB::table('ai_agent_versions')->insert(['id' => 1, 'tenant_id' => 1, 'agent_id' => 1]);
        DB::table('ai_agent_contract_versions')->insert(['id' => 1, 'tenant_id' => 1]);
        DB::table('ai_conversations')->insert([['id' => 1, 'tenant_id' => 1], ['id' => 2, 'tenant_id' => 1]]);
        DB::table('ai_conversation_messages')->insert([['id' => 1, 'tenant_id' => 1], ['id' => 2, 'tenant_id' => 1]]);
        DB::table('ai_runtime_runs')->insert([['id' => 1, 'tenant_id' => 1], ['id' => 2, 'tenant_id' => 1], ['id' => 3, 'tenant_id' => 1]]);
        DB::table('ai_leads')->insert([
            $this->leadRow(1, 1),
            $this->leadRow(2, 2),
        ]);

        DB::table('ai_outcome_events')->insert($this->outcomeRow(1, 'valid_lead', 1));
        DB::table('ai_outcome_events')->insert($this->outcomeRow(1, 'resolved_consultation', null, 2));
        $this->assertSame(2, DB::table('ai_outcome_events')->count());

        try {
            DB::table('ai_outcome_events')->insert($this->outcomeRow(1, 'valid_lead', 2, 3));
            $this->fail('An Outcome cannot reference a Lead from another Conversation in the same tenant.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(2, DB::table('ai_outcome_events')->count());
    }

    public function test_contract_policy_is_strict_and_has_no_billing_semantics(): void
    {
        $policy = LeadOutcomePolicy::from(['lead' => ['allowed_fields' => ['name', 'email'], 'required_fields' => ['email'], 'max_length' => 120], 'outcomes' => ['valid_lead', 'resolved_consultation']]);
        $this->assertSame(['name', 'email'], $policy->allowedFields);
        $this->assertSame(['email'], $policy->requiredFields);
        $this->assertTrue($policy->permits('valid_lead'));
        $this->expectException(\DomainException::class);
        LeadOutcomePolicy::from(['lead' => ['allowed_fields' => ['email', 'billing_amount'], 'required_fields' => []], 'outcomes' => ['valid_lead']]);
    }

    public function test_structured_candidate_cannot_supply_verified_and_rejects_extra_keys(): void
    {
        $valid = AgentRuntimeResponseData::validate(['answer' => 'Respuesta', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'email', 'value' => 'safe@example.test']]], 'resolved_candidate' => true], ['K1']);
        $this->assertSame(['email' => 'safe@example.test'], $valid->leadCandidate);
        $this->assertTrue($valid->resolvedCandidate);
        $this->expectException(InvalidModelResponseException::class);
        AgentRuntimeResponseData::validate(['answer' => 'Respuesta', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => []], 'resolved_candidate' => true, 'verified' => true], ['K1']);
    }

    private function migration(): object
    {
        return require base_path('database/migrations/2026_08_29_100000_create_ai_leads_and_outcome_events.php');
    }

    private function leadRow(int $id, int $conversationId): array
    {
        return ['id' => $id, 'uuid' => "00000000-0000-0000-0000-00000000000{$id}", 'tenant_id' => 1, 'agent_id' => 1, 'agent_version_id' => 1, 'agent_contract_version_id' => 1, 'conversation_id' => $conversationId, 'source_message_id' => $conversationId, 'runtime_run_id' => $conversationId, 'status' => 'detected', 'data' => 'encrypted-test-value', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    }

    private function outcomeRow(int $conversationId, string $type, ?int $leadId, int $runId = 1): array
    {
        return ['uuid' => "10000000-0000-0000-0000-00000000000{$runId}", 'tenant_id' => 1, 'agent_id' => 1, 'agent_version_id' => 1, 'agent_contract_version_id' => 1, 'conversation_id' => $conversationId, 'source_message_id' => 1, 'runtime_run_id' => $runId, 'lead_id' => $leadId, 'outcome_type' => $type, 'status' => 'detected', 'evidence' => '{}', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    }

    private function prerequisites(): void
    {
        Schema::create('network_tenants', fn (Blueprint $t) => $t->id());
        Schema::create('ai_agents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_agent_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('agent_id');
            $t->unique(['tenant_id', 'agent_id', 'id']);
        });
        Schema::create('ai_agent_contract_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_conversation_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
        Schema::create('ai_runtime_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unique(['tenant_id', 'id']);
        });
    }
}
