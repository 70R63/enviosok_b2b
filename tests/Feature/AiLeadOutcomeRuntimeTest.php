<?php

namespace Tests\Feature;

use App\Domain\AI\Agents\Enums\AgentContractStatus;
use App\Domain\AI\Agents\Enums\AgentContractVersionStatus;
use App\Domain\AI\Agents\Enums\AgentStatus;
use App\Domain\AI\Agents\Enums\AgentType;
use App\Domain\AI\Agents\Enums\AgentVersionStatus;
use App\Domain\AI\Agents\Models\Agent;
use App\Domain\AI\Agents\Models\AgentContract;
use App\Domain\AI\Agents\Models\AgentContractVersion;
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\AI\Agents\Services\AiLifecycleAuthorization;
use App\Domain\AI\Conversations\Data\SendConversationMessageData;
use App\Domain\AI\Conversations\Models\Conversation;
use App\Domain\AI\Conversations\Models\ConversationMessage;
use App\Domain\AI\Conversations\Services\SendInternalConversationMessageService;
use App\Domain\AI\Conversations\Services\StartInternalTestConversationService;
use App\Domain\AI\Knowledge\Data\KnowledgeContentData;
use App\Domain\AI\Knowledge\Services\ApproveKnowledgeVersionService;
use App\Domain\AI\Knowledge\Services\AttachKnowledgeToAgentVersionService;
use App\Domain\AI\Knowledge\Services\BuildKnowledgeIndexService;
use App\Domain\AI\Knowledge\Services\CreateKnowledgeSourceService;
use App\Domain\AI\Knowledge\Services\RequestKnowledgeIndexingService;
use App\Domain\AI\Leads\Models\Lead;
use App\Domain\AI\Leads\Models\OutcomeEvent;
use App\Domain\AI\Leads\Services\RecordLeadOutcomeCandidatesService;
use App\Domain\AI\Runtime\Models\RuntimeRun;
use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantDomain;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiLeadOutcomeRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'ai.enabled' => true, 'ai.default_provider' => 'openai', 'ai.providers.openai.api_key' => 'synthetic-test-key', 'ai.providers.openai.model' => 'gpt-5.6-luna', 'app.key' => 'base64:'.base64_encode(str_repeat('l', 32))]);
        $this->schema();
        (require base_path('database/migrations/2026_08_27_110000_add_provider_observability_to_ai_runtime_runs.php'))->up();
        (require base_path('database/migrations/2026_08_28_100000_create_ai_conversations.php'))->up();
        (require base_path('database/migrations/2026_08_29_100000_create_ai_leads_and_outcome_events.php'))->up();
        app(TenantContext::class)->clear();
        Http::preventStrayRequests();
    }

    public function test_full_runtime_flow_persists_encrypted_lead_and_both_outcomes_outside_provider_transaction(): void
    {
        [$tenant,$owner] = $this->authorized('ia07-runtime');
        [$agent,$version,$contract] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'email', 'phone'], 'required_fields' => ['name', 'phone'], 'max_length' => 191], 'outcomes' => ['valid_lead', 'resolved_consultation']]);
        $this->ready($owner, $agent, 'Información comercial verificable para atender la consulta.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        $name = 'IA07_SECRET_NAME_93742';
        $email = 'ia07.secret.93742@example.invalid';
        $phone = '5559374200';
        Http::fake(function () use ($name, $email, $phone) {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response($this->response(['answer' => 'Solicitud registrada.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => $name], ['key' => 'email', 'value' => $email], ['key' => 'phone', 'value' => $phone]]], 'resolved_candidate' => true]), 200);
        });
        $turn = app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Quiero información'));
        $this->assertSame('completed', $turn->assistantMessage->status->value);
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 2);
        $this->assertDatabaseCount('ai_conversation_message_citations', 1);
        $this->assertDatabaseCount('ai_runtime_runs', 1);
        $lead = Lead::firstOrFail();
        $this->assertSame([$tenant->id, $agent->id, $version->id, $contract->id, $conversation->id], [$lead->tenant_id, $lead->agent_id, $lead->agent_version_id, $lead->agent_contract_version_id, $lead->conversation_id]);
        $this->assertSame($name, $lead->data['name']);
        $raw = (string) DB::table('ai_leads')->value('data');
        $this->assertStringNotContainsString($name, $raw);
        $this->assertStringNotContainsString($email, $raw);
        $this->assertStringNotContainsString($phone, $raw);
        $valid = OutcomeEvent::where('outcome_type', 'valid_lead')->firstOrFail();
        $resolved = OutcomeEvent::where('outcome_type', 'resolved_consultation')->firstOrFail();
        $this->assertSame('verified', $valid->status->value);
        $this->assertNotNull($valid->verified_at);
        $this->assertSame($lead->id, $valid->lead_id);
        $this->assertSame($turn->assistantMessage->id, $valid->source_message_id);
        $this->assertSame($turn->assistantMessage->runtime_run_id, $valid->runtime_run_id);
        $this->assertSame('detected', $resolved->status->value);
        $this->assertNull($resolved->verified_at);
        $evidence = (string) DB::table('ai_outcome_events')->pluck('evidence')->implode(' ');
        foreach ([$name, $email, $phone] as $pii) {
            $this->assertStringNotContainsString($pii, $evidence);
        }
    }

    public function test_contract_incomplete_candidate_is_detected_and_unauthorized_candidate_rolls_back_tx2(): void
    {
        [$tenant,$owner] = $this->authorized('ia07-contract');
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        Http::fakeSequence()
            ->push($this->response(['answer' => 'Registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Nombre incompleto']]], 'resolved_candidate' => false]), 200)
            ->push($this->response(['answer' => 'No debe finalizar.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'credit_card', 'value' => 'synthetic-invalid-value']]], 'resolved_candidate' => false]), 200);
        app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Información'));
        $this->assertSame('detected', Lead::firstOrFail()->status->value);
        $this->assertSame('detected', OutcomeEvent::firstOrFail()->status->value);
        $this->assertNull(OutcomeEvent::first()->verified_at);
        $other = app(StartInternalTestConversationService::class)->start($owner, $agent);
        try {
            app(SendInternalConversationMessageService::class)->send($owner, $other, SendConversationMessageData::from('Información'));
            $this->fail('Unauthorized field must fail.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 1);
        $failed = ConversationMessage::where('conversation_id', $other->id)->where('role', 'assistant')->firstOrFail();
        $this->assertSame('failed', $failed->status->value);
        $this->assertDatabaseMissing('ai_conversation_message_citations', ['conversation_message_id' => $failed->id]);
    }

    public function test_same_runtime_is_idempotent_and_new_runtime_is_a_distinct_outcome_but_not_a_second_lead(): void
    {
        [$tenant,$owner] = $this->authorized('ia07-idempotent');
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        $output = ['answer' => 'Registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Prospecto Uno'], ['key' => 'phone', 'value' => '5550001111']]], 'resolved_candidate' => false];
        Http::fake(['api.openai.com/*' => Http::response($this->response($output), 200)]);
        $first = app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Información uno'));
        $lead = Lead::firstOrFail();
        $run = RuntimeRun::findOrFail($first->assistantMessage->runtime_run_id);
        app(RecordLeadOutcomeCandidatesService::class)->record($conversation->fresh(), $first->assistantMessage, $run, ['name' => 'Prospecto Uno', 'phone' => '5550001111'], false);
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 1);
        Http::fake(['api.openai.com/*' => Http::response($this->response($output), 200)]);
        app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información dos'));
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 2);
        $this->assertSame($lead->id, Lead::first()->id);
    }

    public function test_fresh_and_stale_pending_turns_never_create_evidence_for_abandoned_assistant(): void
    {
        [$tenant,$owner] = $this->authorized('ia07-stale');
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        $pending = $this->pending($owner, $conversation);
        $output = ['answer' => 'Nuevo turno.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Nuevo Prospecto'], ['key' => 'phone', 'value' => '5550002222']]], 'resolved_candidate' => false];
        Http::fake(['api.openai.com/*' => Http::response($this->response($output), 200)]);
        try {
            app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Bloqueado'));
            $this->fail('Fresh pending must block.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }$this->assertCount(0, Http::recorded());
        $this->assertDatabaseCount('ai_leads', 0);
        $this->assertDatabaseCount('ai_outcome_events', 0);
        DB::table('ai_conversation_messages')->where('id', $pending->id)->update(['created_at' => now()->subSeconds(121), 'updated_at' => now()->subSeconds(121)]);
        $turn = app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información recuperada'));
        $this->assertSame('failed', $pending->fresh()->status->value);
        $this->assertSame('abandoned_turn', $pending->fresh()->safe_error_code);
        $this->assertSame('completed', $turn->assistantMessage->status->value);
        $this->assertSame($turn->assistantMessage->id, Lead::firstOrFail()->source_message_id);
        $this->assertDatabaseMissing('ai_leads', ['source_message_id' => $pending->id]);
        $this->assertDatabaseMissing('ai_outcome_events', ['source_message_id' => $pending->id]);
    }

    public function test_tenant_admin_navigation_list_detail_xss_cross_tenant_and_gates(): void
    {
        [$tenant, $owner] = $this->authorized('ia07-ui');
        TenantDomain::create(['tenant_id' => $tenant->id, 'domain' => 'ia07-ui.test', 'type' => 'subdomain', 'environment' => 'sandbox', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        $xss = "<script>alert('IA07-XSS')</script>";
        Http::fake(['api.openai.com/*' => Http::response($this->response(['answer' => 'Registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => $xss], ['key' => 'phone', 'value' => '5550004444']]], 'resolved_candidate' => false]), 200)]);
        app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Información'));
        $lead = Lead::firstOrFail();
        $base = 'http://ia07-ui.test';
        $this->get($base.'/admin/ai-leads')->assertRedirect();
        $this->actingAs($owner)->get($base.'/admin/ai-leads')->assertOk()->assertSee('Leads')->assertSee('Agente IA07')->assertSee('Verificado')->assertSee('IA07-XSS')->assertDontSee($xss, false)->assertSee('/admin/ai-leads/'.$lead->uuid, false)->assertDontSee('Lead ID')->assertDontSee('UUID:')->assertDontSee('OpenAI')->assertDontSee('gpt-5.6-luna')->assertDontSee('tokens')->assertDontSee('costos')->assertDontSee('{&quot;', false);
        $this->get($base.'/admin/ai-leads/'.$lead->uuid)->assertOk()->assertSee('Agente IA07')->assertSee('Verificado')->assertSee('Datos requeridos completos')->assertSee('Ver transcript')->assertSee('&lt;script&gt;', false)->assertDontSee($xss, false)->assertDontSee((string) $lead->runtimeRun->uuid)->assertDontSee('payload')->assertDontSee('provider');
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer-ui@test', 'password' => 'hashed', 'empresa_id' => 1]);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($viewer)->get($base.'/admin/ai-leads')->assertForbidden();
        $tenant->subscriptions()->first()->entitlements()->where('code', 'AI_CORE')->update(['is_enabled' => false]);
        $this->actingAs($owner)->get($base.'/admin/ai-leads')->assertForbidden();
        [$other,$otherOwner] = $this->authorized('ia07-ui-other');
        TenantDomain::create(['tenant_id' => $other->id, 'domain' => 'ia07-ui-other.test', 'type' => 'subdomain', 'environment' => 'sandbox', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $this->actingAs($otherOwner)->get('http://ia07-ui-other.test/admin/ai-leads/'.$lead->uuid)->assertNotFound();
        $this->get('http://ia07-ui-other.test/admin/ai-leads')->assertOk()->assertDontSee('IA07-XSS');
    }

    public function test_canonical_lead_is_conservatively_enriched_and_verified_from_persisted_data(): void
    {
        [$tenant, $owner] = $this->authorized('ia07-enrichment');
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone', 'company'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        Http::fakeSequence()
            ->push($this->response(['answer' => 'Nombre registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Ana']]], 'resolved_candidate' => false]), 200)
            ->push($this->response(['answer' => 'Teléfono registrado.', 'citation_ids' => ['K1'], 'confidence' => 'medium', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'phone', 'value' => '5550001111']]], 'resolved_candidate' => false]), 200)
            ->push($this->response(['answer' => 'No debe finalizar.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => '   ']]], 'resolved_candidate' => false]), 200);

        app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Información inicial'));
        $lead = Lead::firstOrFail();
        $uuid = $lead->uuid;
        $this->assertSame(['name' => 'Ana'], $lead->data);
        $this->assertSame('detected', $lead->status->value);
        $this->assertSame('detected', OutcomeEvent::firstOrFail()->status->value);

        app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información adicional'));
        $lead = $lead->fresh();
        $this->assertSame($uuid, $lead->uuid);
        $this->assertSame(['name' => 'Ana', 'phone' => '5550001111'], $lead->data);
        $this->assertSame('verified', $lead->status->value);
        $this->assertNotNull($lead->verified_at);
        $this->assertSame('verified', OutcomeEvent::latest('id')->firstOrFail()->status->value);
        $this->assertSame(['name', 'phone'], array_keys($lead->data));

        try {
            app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información inválida'));
            $this->fail('An empty replacement must be rejected.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(['name' => 'Ana', 'phone' => '5550001111'], $lead->fresh()->data);
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 2);
        $failed = ConversationMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('failed', $failed->status->value);
        $this->assertDatabaseMissing('ai_conversation_message_citations', ['conversation_message_id' => $failed->id]);
    }

    public function test_incomplete_enrichment_and_unauthorized_field_preserve_the_existing_lead_atomically(): void
    {
        [$tenant, $owner] = $this->authorized('ia07-enrichment-negative');
        [$agent] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name', 'phone', 'company'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agent, 'Información autorizada.');
        $conversation = app(StartInternalTestConversationService::class)->start($owner, $agent);
        Http::fakeSequence()
            ->push($this->response(['answer' => 'Nombre.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Ana']]], 'resolved_candidate' => false]), 200)
            ->push($this->response(['answer' => 'Empresa.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'company', 'value' => 'Empresa sintética']]], 'resolved_candidate' => false]), 200)
            ->push($this->response(['answer' => 'Inválido.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'secret_note', 'value' => 'synthetic-invalid-value']]], 'resolved_candidate' => false]), 200);
        app(SendInternalConversationMessageService::class)->send($owner, $conversation, SendConversationMessageData::from('Información uno'));
        $lead = Lead::firstOrFail();
        app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información dos'));
        $this->assertSame($lead->uuid, $lead->fresh()->uuid);
        $this->assertSame(['name' => 'Ana', 'company' => 'Empresa sintética'], $lead->fresh()->data);
        $this->assertSame('detected', $lead->fresh()->status->value);
        $this->assertSame('detected', OutcomeEvent::latest('id')->firstOrFail()->status->value);
        try {
            app(SendInternalConversationMessageService::class)->send($owner, $conversation->fresh(), SendConversationMessageData::from('Información tres'));
            $this->fail('An unauthorized enrichment field must be rejected.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(['name' => 'Ana', 'company' => 'Empresa sintética'], $lead->fresh()->data);
        $this->assertDatabaseCount('ai_leads', 1);
        $this->assertDatabaseCount('ai_outcome_events', 2);
        $failed = ConversationMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('failed', $failed->status->value);
        $this->assertDatabaseMissing('ai_conversation_message_citations', ['conversation_message_id' => $failed->id]);
    }

    public function test_same_tenant_incoherent_aggregate_references_are_rejected_without_partial_evidence(): void
    {
        [$tenant, $owner] = $this->authorized('ia07-coherence');
        [$agentA] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name'], 'required_fields' => ['name']], 'outcomes' => ['valid_lead']]);
        [$agentB, $versionB, $contractB] = $this->agent($owner, ['lead' => ['allowed_fields' => ['name'], 'required_fields' => ['name']], 'outcomes' => ['valid_lead']]);
        $this->ready($owner, $agentA, 'Información autorizada A.');
        $this->ready($owner, $agentB, 'Información autorizada B.');
        $conversationA = app(StartInternalTestConversationService::class)->start($owner, $agentA);
        $conversationB = app(StartInternalTestConversationService::class)->start($owner, $agentA);
        Http::fake(['api.openai.com/*' => Http::response($this->response(['answer' => 'Registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Prospecto']]], 'resolved_candidate' => false]), 200)]);
        $turnA = app(SendInternalConversationMessageService::class)->send($owner, $conversationA, SendConversationMessageData::from('Información A'));
        $turnB = app(SendInternalConversationMessageService::class)->send($owner, $conversationB, SendConversationMessageData::from('Información B'));
        $runA = RuntimeRun::findOrFail($turnA->assistantMessage->runtime_run_id);
        $runB = RuntimeRun::findOrFail($turnB->assistantMessage->runtime_run_id);
        $service = app(RecordLeadOutcomeCandidatesService::class);
        foreach ([
            [$conversationA, $turnB->assistantMessage, $runB],
            [$conversationA, $turnA->assistantMessage, $runB],
        ] as [$conversation, $message, $run]) {
            try {
                DB::transaction(fn () => $service->record($conversation, $message, $run, ['name' => 'No persistir'], false));
                $this->fail('Incoherent message/runtime references must be rejected.');
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('ai_leads', 2);
        $this->assertDatabaseCount('ai_outcome_events', 2);

        $leadA = Lead::where('conversation_id', $conversationA->id)->firstOrFail();
        DB::table('ai_leads')->where('id', $leadA->id)->update(['agent_contract_version_id' => $contractB->id]);
        try {
            DB::transaction(fn () => $service->record($conversationA->fresh(), $turnA->assistantMessage, $runA, ['name' => 'No persistir'], false));
            $this->fail('A canonical Lead with an incompatible Contract Version must be rejected.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('ai_outcome_events', 2);

        $conversationC = app(StartInternalTestConversationService::class)->start($owner, $agentA);
        try {
            DB::table('ai_conversations')->insert(array_merge((array) DB::table('ai_conversations')->where('id', $conversationC->id)->first(), ['id' => $conversationC->id + 100, 'uuid' => (string) Str::uuid(), 'agent_id' => $agentA->id, 'agent_version_id' => $versionB->id]));
            $this->fail('Agent and Agent Version from different agents must be rejected by the database.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('ai_agent_versions')->where('id', $conversationA->agent_version_id)->update(['agent_contract_version_id' => $contractB->id]);
        try {
            DB::transaction(fn () => $service->record($conversationA->fresh(), $turnA->assistantMessage, $runA, ['name' => 'No persistir'], false));
            $this->fail('An incompatible Contract Version must be rejected.');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('ai_leads', 2);
        $this->assertDatabaseCount('ai_outcome_events', 2);
    }

    public function test_database_uniques_tenant_aware_foreign_keys_and_restrict_policy(): void
    {
        [$tenantA,$ownerA] = $this->authorized('ia07-db-a');
        [$agentA] = $this->agent($ownerA, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($ownerA, $agentA, 'Información autorizada.');
        $conversationA = app(StartInternalTestConversationService::class)->start($ownerA, $agentA);
        $output = ['answer' => 'Registrado.', 'citation_ids' => ['K1'], 'confidence' => 'high', 'needs_handoff' => false, 'handoff_reason' => 'none', 'lead_candidate' => ['fields' => [['key' => 'name', 'value' => 'Tenant sintético'], ['key' => 'phone', 'value' => '5550005555']]], 'resolved_candidate' => false];
        Http::fake(['api.openai.com/*' => Http::response($this->response($output), 200)]);
        app(SendInternalConversationMessageService::class)->send($ownerA, $conversationA, SendConversationMessageData::from('Información'));
        $leadA = Lead::firstOrFail();
        $outcomeA = OutcomeEvent::firstOrFail();
        [$tenantB,$ownerB] = $this->authorized('ia07-db-b');
        [$agentB] = $this->agent($ownerB, ['lead' => ['allowed_fields' => ['name', 'phone'], 'required_fields' => ['name', 'phone']], 'outcomes' => ['valid_lead']]);
        $this->ready($ownerB, $agentB, 'Información autorizada.');
        $conversationB = app(StartInternalTestConversationService::class)->start($ownerB, $agentB);
        Http::fake(['api.openai.com/*' => Http::response($this->response($output), 200)]);
        app(SendInternalConversationMessageService::class)->send($ownerB, $conversationB, SendConversationMessageData::from('Información'));
        $leadB = Lead::firstOrFail();
        $outcomeB = OutcomeEvent::firstOrFail();
        foreach ([
            ['lead_id' => $leadA->id],
            ['conversation_id' => $conversationA->id],
            ['runtime_run_id' => $leadA->runtime_run_id],
        ] as $override) {
            $row = ['uuid' => (string) Str::uuid(), 'tenant_id' => $tenantB->id, 'agent_id' => $agentB->id, 'agent_version_id' => $conversationB->agent_version_id, 'agent_contract_version_id' => $leadB->agent_contract_version_id, 'conversation_id' => $conversationB->id, 'source_message_id' => $leadB->source_message_id, 'runtime_run_id' => $leadB->runtime_run_id, 'lead_id' => $leadB->id, 'outcome_type' => 'resolved_consultation', 'status' => 'detected', 'evidence' => '{}', 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            try {
                DB::table('ai_outcome_events')->insert(array_replace($row, $override));
                $this->fail('Cross-tenant outcome FK must reject.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            DB::table('ai_leads')->insert(array_merge(DB::table('ai_leads')->where('id', $leadB->id)->first() ? (array) DB::table('ai_leads')->where('id', $leadB->id)->first() : [], ['id' => $leadB->id + 100, 'uuid' => (string) Str::uuid()]));
            $this->fail('One lead per conversation must be enforced.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::table('ai_outcome_events')->insert(array_merge((array) DB::table('ai_outcome_events')->where('id', $outcomeB->id)->first(), ['id' => $outcomeB->id + 100, 'uuid' => (string) Str::uuid()]));
            $this->fail('Logical outcome identity must be unique.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::table('ai_conversations')->where('id', $conversationB->id)->delete();
            $this->fail('Conversation history must be restricted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(2, (int) DB::table('ai_leads')->count());
        $this->assertSame(2, (int) DB::table('ai_outcome_events')->count());
    }

    private function pending(User $owner, Conversation $conversation): ConversationMessage
    {
        return DB::transaction(function () use ($owner, $conversation) {
            $auth = app(AiLifecycleAuthorization::class)->authorize($owner);
            $c = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            [$u,$a] = $c->reserveTurn($auth);
            $user = new ConversationMessage;
            $user->conversation_id = $c->id;
            $user->sequence = $u;
            $user->role = 'user';
            $user->status = 'completed';
            $user->content = 'anterior';
            $user->completed_at = now();
            $user->save();
            $assistant = new ConversationMessage;
            $assistant->conversation_id = $c->id;
            $assistant->sequence = $a;
            $assistant->role = 'assistant';
            $assistant->status = 'pending';
            $assistant->needs_handoff = false;
            $assistant->save();

            return $assistant;
        });
    }

    private function response(array $output): array
    {
        return ['id' => 'resp_ia07', 'model' => 'gpt-5.6-luna', 'status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($output, JSON_THROW_ON_ERROR)]]]], 'usage' => ['input_tokens' => 10, 'input_tokens_details' => ['cached_tokens' => 0], 'output_tokens' => 5, 'total_tokens' => 15]];
    }

    private function ready(User $u, Agent $a, string $body): void
    {
        $s = app(CreateKnowledgeSourceService::class)->create($u, 'Fuente', KnowledgeContentData::fromArray(['type' => 'manual_text', 'body' => $body]));
        $v = app(ApproveKnowledgeVersionService::class)->approve($u, $s->versions->first());
        app(AttachKnowledgeToAgentVersionService::class)->attach($u, $a, $v);
        Bus::fake();
        $i = app(RequestKnowledgeIndexingService::class)->request($u, $s, $v);
        app(BuildKnowledgeIndexService::class)->build($i->id);
    }

    private function authorized(string $s): array
    {
        $t = Tenant::create(['name' => $s, 'slug' => $s, 'status' => 'active']);
        $u = User::create(['name' => 'User', 'email' => $s.'@test', 'password' => 'hashed', 'empresa_id' => 1]);
        TenantMembership::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'role' => 'owner', 'status' => 'active']);
        $m = Module::firstOrCreate(['code' => 'AI_CORE'], ['name' => 'AI Core', 'type' => 'core', 'is_active' => true, 'sort_order' => 1]);
        $p = Plan::create(['code' => 'P'.$t->id, 'name' => 'AI', 'status' => 'active', 'currency' => 'MXN']);
        $sub = Subscription::create(['tenant_id' => $t->id, 'plan_id' => $p->id, 'status' => 'active', 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        Entitlement::create(['subscription_id' => $sub->id, 'tenant_id' => $t->id, 'module_id' => $m->id, 'code' => 'AI_CORE', 'is_enabled' => true, 'source' => 'plan']);
        app(TenantContext::class)->set($t);

        return [$t, $u];
    }

    private function agent(User $u, array $policy): array
    {
        $a = new Agent;
        $a->code = 'L'.uniqid();
        $a->name = 'Agente IA07';
        $a->type = AgentType::Sales;
        $a->status = AgentStatus::Draft;
        $a->created_by_user_id = $u->id;
        $a->save();
        $c = new AgentContract;
        $c->agent_id = $a->id;
        $c->status = AgentContractStatus::Draft;
        $c->created_by_user_id = $u->id;
        $c->save();
        $cv = new AgentContractVersion;
        $cv->agent_contract_id = $c->id;
        $cv->version_number = 1;
        $cv->status = AgentContractVersionStatus::Draft;
        $cv->job_to_be_done = 'Atender';
        foreach (['objectives', 'allowed_capabilities', 'prohibited_capabilities', 'channels', 'knowledge_requirements', 'allowed_actions', 'handoff_policy', 'capacity_policy', 'sla_policy', 'privacy_policy', 'pricing_policy'] as $f) {
            $cv->$f = [];
        }$cv->outcome_policy = $policy;
        $cv->created_by_user_id = $u->id;
        $cv->save();
        $v = new AgentVersion;
        $v->agent_id = $a->id;
        $v->agent_contract_version_id = $cv->id;
        $v->version_number = 1;
        $v->status = AgentVersionStatus::Draft;
        $v->schema_version = '1.0';
        $v->configuration = ['capabilities' => [], 'guardrails' => [], 'handoff' => [], 'outcomes' => [], 'capacity' => [], 'sla' => [], 'privacy' => [], 'channels' => []];
        $v->created_by_user_id = $u->id;
        $v->save();

        return [$a, $v, $cv];
    }

    private function schema(): void
    {
        DB::statement('PRAGMA foreign_keys=ON');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->unsignedBigInteger('empresa_id');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('network_tenants', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('network_tenant_domains', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('domain')->unique();
            $t->string('type');
            $t->string('environment');
            $t->boolean('is_primary');
            $t->string('status');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_brandings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->unique();
            $t->string('brand_name')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('favicon_path')->nullable();
            $t->string('primary_color')->nullable();
            $t->string('secondary_color')->nullable();
            $t->string('accent_color')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_memberships', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role');
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('network_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status');
            $t->char('currency', 3);
            $t->timestamps();
        });
        Schema::create('network_modules', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type');
            $t->boolean('is_active');
            $t->unsignedSmallInteger('sort_order');
            $t->timestamps();
        });
        Schema::create('network_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('plan_id');
            $t->string('status');
            foreach (['started_at', 'current_period_start', 'current_period_end', 'trial_ends_at', 'grace_ends_at', 'canceled_at', 'ended_at'] as $c) {
                $t->timestamp($c)->nullable();
            }$t->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('subscription_id');
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('module_id');
            $t->string('code');
            $t->boolean('is_enabled');
            $t->string('source');
            $t->timestamps();
        });
        foreach (['2026_08_22_100000_create_ai_agent_domain_tables.php', '2026_08_23_100000_add_ai_agent_lifecycle.php', '2026_08_25_100000_create_ai_knowledge_foundation.php', '2026_08_26_100000_create_ai_knowledge_indexing.php', '2026_08_27_100000_create_ai_runtime_runs.php'] as $m) {
            (require base_path('database/migrations/'.$m))->up();
        }
    }
}
