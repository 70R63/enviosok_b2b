<?php

namespace Tests\Feature;

use App\Domain\AI\Agents\Data\{AgentConfigurationData,AgentContractDraftData,CreateAgentDraftData};
use App\Domain\AI\Agents\Enums\{AgentContractStatus,AgentContractVersionStatus,AgentStatus,AgentType,AgentVersionStatus};
use App\Domain\AI\Agents\Exceptions\{AgentContractNotAcceptedException,AgentVersionConflictException,AiReviewRequiredException,InvalidAgentTransitionException,StaleAiContentHashException};
use App\Domain\AI\Agents\Models\{Agent,AgentContractVersion,AgentLifecycleEvent,AgentVersion};
use App\Domain\AI\Agents\Services\{AgentAggregateLifecycleService,AgentContractVersionLifecycleService,AgentVersionLifecycleService,CreateAgentDraftService,CreateAgentVersionService};
use App\Domain\AI\Support\CanonicalJsonHasher;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\AI\Support\Exceptions\{AiImmutableAttributeException,AiTenantContextException,AiTenantMismatchException};
use App\Domain\AI\Support\Exceptions\{AiDisabledException,AiEntitlementException};
use App\Domain\Network\Billing\Models\{Entitlement,Subscription};
use App\Domain\Network\Catalog\Models\{Module,Plan};
use App\Domain\Network\Tenancy\Models\{Tenant,TenantMembership};
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;

final class AiAgentLifecycleTest extends TestCase
{
    protected function setUp():void{parent::setUp();config(['ai.enabled'=>true,'ai.entitlement.module_code'=>'AI_CORE','ai.capacity.defaults.MAX_AGENTS'=>100,'ai.capacity.defaults.MAX_WEBCHAT_CHANNELS'=>100,'ai.capacity.defaults.MAX_WHATSAPP_CHANNELS'=>100,'ai.capacity.defaults.MONTHLY_RUNTIME_UNITS'=>100000,'ai.capacity.defaults.MONTHLY_ACTION_RUNS'=>100000,'ai.capacity.defaults.MONTHLY_CONVERSATIONS'=>100000]);$this->schema();app(TenantContext::class)->clear();}

    public function test_incremental_migration_up_down_foreign_keys_indexes_and_restrict():void
    {
        foreach(['current_published_version_id','activated_at','paused_at','retired_at']as$column)$this->assertTrue(Schema::hasColumn('ai_agents',$column));
        $this->assertTrue(Schema::hasTable('ai_agent_lifecycle_events'));$this->assertSame(1,(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        foreach(['ai_agents','ai_agent_contracts','ai_agent_contract_versions','ai_agent_versions','ai_agent_lifecycle_events']as$table)$this->assertNotEmpty(DB::select("PRAGMA foreign_key_list('{$table}')"));
        $this->assertNotEmpty(DB::select("PRAGMA index_list('ai_agent_lifecycle_events')"));$this->lifecycleMigration()->down();$this->assertSame(1,(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);$this->assertFalse(Schema::hasTable('ai_agent_lifecycle_events'));foreach(['current_published_version_id','activated_at','paused_at','retired_at']as$column)$this->assertFalse(Schema::hasColumn('ai_agents',$column));foreach(['content_hash','offered_by_user_id','accepted_by_user_id','terminal_by_user_id']as$column)$this->assertFalse(Schema::hasColumn('ai_agent_contract_versions',$column));foreach(['configuration_hash','approved_by_user_id','published_by_user_id','retired_by_user_id']as$column)$this->assertFalse(Schema::hasColumn('ai_agent_versions',$column));$this->lifecycleMigration()->up();$this->assertSame(1,(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);$triggers=collect(DB::select("SELECT name FROM sqlite_master WHERE type='trigger'"))->pluck('name');foreach(['ai_agents_pointer_insert_guard','ai_agents_pointer_update_guard','ai_contracts_pointer_insert_guard','ai_contracts_pointer_update_guard']as$name)$this->assertTrue($triggers->contains($name));
    }

    public function test_canonical_hash_is_stable_and_detects_value_and_list_order():void
    {
        $hasher=app(CanonicalJsonHasher::class);$a=['z'=>'á','nested'=>['b'=>2.0,'a'=>1],'list'=>['one','two']];$b=['list'=>['one','two'],'nested'=>['a'=>1,'b'=>2.0],'z'=>'á'];
        $this->assertSame($hasher->hash($a),$hasher->hash($b));$this->assertNotSame($hasher->hash($a),$hasher->hash(array_replace($a,['z'=>'é'])));$this->assertNotSame($hasher->hash($a),$hasher->hash(array_replace($a,['list'=>['two','one']])));
    }

    public function test_offer_accept_and_publish_complete_valid_lifecycle():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('happy');$contracts=app(AgentContractVersionLifecycleService::class);$versions=app(AgentVersionLifecycleService::class);
        $offered=$contracts->offer($actor,$created->contractVersion);$this->assertSame(AgentContractVersionStatus::Offered,$offered->status);$this->assertSame(64,strlen($offered->content_hash));
        $accepted=$contracts->accept($actor,$offered,$offered->content_hash,['method'=>'manual','acknowledged'=>true]);$this->assertSame(AgentContractVersionStatus::Accepted,$accepted->status);$this->assertSame($accepted->id,$created->contract->refresh()->current_accepted_version_id);$this->assertSame(AgentContractStatus::Active,$created->contract->status);
        try{$accepted->delete();$this->fail('Accepted pointed history must be restricted.');}catch(QueryException){$this->assertDatabaseHas('ai_agent_contract_versions',['id'=>$accepted->id]);}
        $testing=$versions->startTesting($actor,$created->agentVersion);$this->assertSame(64,strlen($testing->configuration_hash));$approved=$versions->approve($actor,$testing,['reviewer'=>'tenant_admin','decision'=>'approved']);$published=$versions->publish($actor,$approved);
        $this->assertSame(AgentVersionStatus::Published,$published->status);$this->assertSame($published->id,$created->agent->refresh()->current_published_version_id);$this->assertSame(AgentStatus::Active,$created->agent->status);$this->assertSame($tenant->id,app(TenantContext::class)->id());
    }

    public function test_stale_hash_content_freeze_invalid_transitions_and_terminal_contract_states():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('stale');$service=app(AgentContractVersionLifecycleService::class);$offered=$service->offer($actor,$created->contractVersion);
        try{$service->accept($actor,$offered,str_repeat('0',64),['method'=>'manual']);$this->fail('Stale hash should fail.');}catch(StaleAiContentHashException){$this->assertSame(AgentContractVersionStatus::Offered,$offered->refresh()->status);}
        try{$service->accept($actor,$offered,$offered->content_hash,['ipAddress'=>'192.0.2.1']);$this->fail('Raw IP evidence should fail.');}catch(\InvalidArgumentException){$this->assertSame(AgentContractVersionStatus::Offered,$offered->refresh()->status);}
        $offered->job_to_be_done='changed';try{$offered->save();$this->fail('Offered content is frozen.');}catch(AiImmutableAttributeException){$offered->refresh();}
        $rejected=$service->reject($actor,$offered,'Commercial terms declined');$this->assertSame(AgentContractVersionStatus::Rejected,$rejected->status);try{$service->accept($actor,$rejected,$rejected->content_hash,['method'=>'manual']);$this->fail('Rejected is terminal.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentContractVersionStatus::Rejected,$rejected->refresh()->status);}
        [$t2,$a2,$other]=$this->draftAggregate('withdrawn');$withdrawn=$service->withdraw($a2,$service->offer($a2,$other->contractVersion),'Offer expired');$this->assertSame(AgentContractVersionStatus::Withdrawn,$withdrawn->status);try{$service->offer($a2,$withdrawn);$this->fail('Withdrawn is terminal.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentContractVersionStatus::Withdrawn,$withdrawn->refresh()->status);}
    }

    public function test_testing_return_to_draft_review_and_publication_guards():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('review');$versions=app(AgentVersionLifecycleService::class);$testing=$versions->startTesting($actor,$created->agentVersion);
        try{$versions->approve($actor,$testing,[]);$this->fail('Evidence required.');}catch(AiReviewRequiredException){$this->assertSame(AgentVersionStatus::Testing,$testing->refresh()->status);}
        $draft=$versions->returnToDraft($actor,$testing,'Configuration needs correction');$this->assertSame(AgentVersionStatus::Draft,$draft->status);$draft->configuration=$this->configurationArray(['behavior'=>['tone'=>'formal']]);$draft->save();$approved=$versions->approve($actor,$versions->startTesting($actor,$draft),['decision'=>'approved']);
        try{$versions->publish($actor,$approved);$this->fail('Accepted contract required.');}catch(AgentContractNotAcceptedException){$this->assertSame(AgentVersionStatus::Approved,$approved->refresh()->status);}
    }

    public function test_publication_retires_previous_and_is_idempotent():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('republish');$factory=app(CreateAgentVersionService::class);$versions=app(AgentVersionLifecycleService::class);$v2=$factory->createAgentVersion($actor,$created->agent,$this->configuration(),$created->contractVersion);$v2=$versions->publish($actor,$versions->approve($actor,$versions->startTesting($actor,$v2),['decision'=>'approved']));
        $this->assertSame(2,$v2->version_number);$this->assertSame(AgentVersionStatus::Retired,$created->agentVersion->refresh()->status);$events=AgentLifecycleEvent::where('event','agent_version_published')->count();$versions->publish($actor,$v2);$this->assertSame($events,AgentLifecycleEvent::where('event','agent_version_published')->count());$this->assertSame($v2->id,$created->agent->refresh()->current_published_version_id);
    }

    public function test_new_contract_acceptance_pauses_agent_using_old_terms():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('new-terms');$factory=app(CreateAgentVersionService::class);$contracts=app(AgentContractVersionLifecycleService::class);$v2=$factory->createContractVersion($actor,$created->contract,$this->contract('New commercial scope'));$v2=$contracts->offer($actor,$v2);$contracts->accept($actor,$v2,$v2->content_hash,['method'=>'manual']);
        $this->assertSame(AgentContractVersionStatus::Superseded,$created->contractVersion->refresh()->status);$this->assertSame(AgentStatus::Paused,$created->agent->refresh()->status);$this->assertSame($v2->id,$created->contract->refresh()->current_accepted_version_id);
    }

    public function test_version_creation_allocates_next_number_and_enforces_single_work_version():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('versions');$factory=app(CreateAgentVersionService::class);$agentV2=$factory->createAgentVersion($actor,$created->agent,$this->configuration(),$created->contractVersion);$contractV2=$factory->createContractVersion($actor,$created->contract,$this->contract('Version two'));
        $this->assertSame(2,$agentV2->version_number);$this->assertSame(2,$contractV2->version_number);$this->assertSame(AgentVersionStatus::Draft,$agentV2->status);$this->assertSame(AgentContractVersionStatus::Draft,$contractV2->status);
        foreach([fn()=>$factory->createAgentVersion($actor,$created->agent,$this->configuration()),fn()=>$factory->createContractVersion($actor,$created->contract,$this->contract('Conflict'))]as$create){try{$create();$this->fail('Only one work version allowed.');}catch(AgentVersionConflictException $exception){$this->assertStringContainsString('work version',$exception->getMessage());}}
        $this->assertSame(2,AgentVersion::where('agent_id',$created->agent->id)->count());$this->assertSame(2,AgentContractVersion::where('agent_contract_id',$created->contract->id)->count());
    }

    public function test_authorization_cross_tenant_and_bulk_protection_fail_closed():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('auth');$viewer=$this->user('viewer@life.local');$this->membership($tenant,$viewer,'viewer');$suspended=$this->user('suspended@life.local');$membership=$this->membership($tenant,$suspended,'admin');$membership->status='suspended';$membership->save();
        foreach([$viewer,$suspended]as$user){try{app(AgentContractVersionLifecycleService::class)->offer($user,$created->contractVersion);$this->fail('Unauthorized actor.');}catch(AuthorizationException $exception){$this->assertSame(AgentContractVersionStatus::Draft,$created->contractVersion->refresh()->status);}}
        [$other,$otherActor,$otherCreated]=$this->draftAggregate('auth-other');app(TenantContext::class)->set($tenant);try{app(AgentContractVersionLifecycleService::class)->offer($actor,$otherCreated->contractVersion);$this->fail('Cross tenant resource.');}catch(\Illuminate\Database\Eloquent\ModelNotFoundException){$this->assertSame(AgentContractVersionStatus::Draft->value,DB::table('ai_agent_contract_versions')->where('id',$otherCreated->contractVersion->id)->value('status'));}
        foreach([fn()=>Agent::query()->update(['status'=>'active']),fn()=>Agent::query()->update(['current_published_version_id'=>1]),fn()=>AgentVersion::query()->update(['configuration_hash'=>str_repeat('a',64)])]as$write){try{$write();$this->fail('Lifecycle bulk update should fail.');}catch(AiImmutableAttributeException $exception){$this->assertStringContainsString('immutable',$exception->getMessage());}}
        app(TenantContext::class)->clear();$this->expectException(AiTenantContextException::class);AgentLifecycleEvent::count();
    }

    public function test_cross_agent_contract_link_cannot_be_published():void
    {
        [$tenant,$actor,$first]=$this->publishedAggregate('cross-one');[$same,$sameActor,$second]=$this->draftAggregate('cross-two',$tenant,$actor);$contracts=app(AgentContractVersionLifecycleService::class);$versions=app(AgentVersionLifecycleService::class);$offered=$contracts->offer($actor,$second->contractVersion);$contracts->accept($actor,$offered,$offered->content_hash,['method'=>'manual']);$approved=$versions->approve($actor,$versions->startTesting($actor,$second->agentVersion),['decision'=>'approved']);DB::table('ai_agent_versions')->where('id',$approved->id)->update(['agent_contract_version_id'=>$first->contractVersion->id]);$approved->refresh();$this->expectException(\App\Domain\AI\Agents\Exceptions\InconsistentAgentPublicationException::class);$versions->publish($actor,$approved);
    }

    public function test_suspend_resume_end_and_retire_are_coordinated():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('aggregate');$service=app(AgentAggregateLifecycleService::class);$service->suspendContract($actor,$created->contract,'Operational hold');$this->assertSame(AgentContractStatus::Suspended,$created->contract->refresh()->status);$this->assertSame(AgentStatus::Paused,$created->agent->refresh()->status);$service->resumeContract($actor,$created->contract,'Terms restored');$this->assertSame(AgentContractStatus::Active,$created->contract->refresh()->status);$this->assertSame(AgentStatus::Paused,$created->agent->refresh()->status);$service->resume($actor,$created->agent,'Resume operation');$this->assertSame(AgentStatus::Active,$created->agent->refresh()->status);$service->endContract($actor,$created->contract,'Relationship ended');$this->assertSame(AgentContractStatus::Ended,$created->contract->refresh()->status);$this->assertSame(AgentStatus::Retired,$created->agent->refresh()->status);$this->assertSame(AgentVersionStatus::Retired,$created->agentVersion->refresh()->status);
        [$tenant2,$actor2,$created2]=$this->publishedAggregate('agent-retire');$service->retire($actor2,$created2->agent,'Agent withdrawn');$this->assertSame(AgentStatus::Retired,$created2->agent->refresh()->status);$this->assertSame(AgentContractStatus::Ended,$created2->contract->refresh()->status);$this->assertSame(AgentVersionStatus::Retired,$created2->agentVersion->refresh()->status);
    }

    public function test_retired_agent_releases_real_creation_capacity(): void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 1]);
        [$tenant, $actor, $created] = $this->publishedAggregate('capacity-retired');
        $capacity = app(AiCapacityService::class);
        $this->assertSame(1, $capacity->used($tenant, AiCapacityService::MAX_AGENTS));
        app(AgentAggregateLifecycleService::class)->retire($actor, $created->agent, 'Capacity released');
        $this->assertSame(0, $capacity->used($tenant, AiCapacityService::MAX_AGENTS));
        $replacement = app(CreateAgentDraftService::class)->create($actor, new CreateAgentDraftData('replacement', 'Replacement', null, AgentType::Sales, $this->contract(), $this->configuration()));
        $this->assertNotSame($created->agent->id, $replacement->agent->id);
    }

    public function test_audit_is_tenant_scoped_append_only_and_contains_no_full_content():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('audit');app(AgentContractVersionLifecycleService::class)->offer($actor,$created->contractVersion);$event=AgentLifecycleEvent::firstOrFail();$this->assertSame($tenant->id,$event->tenant_id);$this->assertArrayNotHasKey('configuration',$event->metadata);$this->assertArrayNotHasKey('job_to_be_done',$event->metadata);try{$event->delete();$this->fail('Audit is append only.');}catch(AiImmutableAttributeException){$this->assertDatabaseHas('ai_agent_lifecycle_events',['id'=>$event->id]);}
    }

    public function test_lifecycle_authorization_fails_closed_for_all_gate_conditions():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('gates');config(['ai.enabled'=>false]);try{app(AgentContractVersionLifecycleService::class)->offer($actor,$created->contractVersion);$this->fail('Disabled AI must fail.');}catch(AiDisabledException){$this->assertSame(AgentContractVersionStatus::Draft,$created->contractVersion->refresh()->status);}config(['ai.enabled'=>true]);
        $inactive=Tenant::create(['name'=>'Inactive','slug'=>'inactive-life','status'=>'inactive']);$inactiveActor=$this->user('inactive@life.local');$this->membership($inactive,$inactiveActor,'owner');$this->entitle($inactive);app(TenantContext::class)->set($inactive);try{app(CreateAgentVersionService::class)->createAgentVersion($inactiveActor,$created->agent,$this->configuration());$this->fail('Inactive tenant must fail.');}catch(AiTenantContextException){$this->assertSame('inactive',$inactive->refresh()->status);}
        $plain=$this->tenant('no-core');$plainActor=$this->user('no-core@life.local');$this->membership($plain,$plainActor,'owner');app(TenantContext::class)->set($plain);try{app(CreateAgentDraftService::class)->create($plainActor,new CreateAgentDraftData('no-core','No Core',null,AgentType::Sales,$this->contract(),$this->configuration()));$this->fail('Entitlement required.');}catch(AiEntitlementException){$this->assertSame(0,DB::table('ai_agents')->where('tenant_id',$plain->id)->count());}
        app(TenantContext::class)->clear();$this->expectException(AiTenantContextException::class);app(AgentContractVersionLifecycleService::class)->offer($actor,$created->contractVersion);
    }

    public function test_instance_permission_is_isolated_and_cleared_after_success_and_exception():void
    {
        $this->assertFalse(class_exists(\App\Domain\AI\Agents\Support\AiLifecycleMutation::class));[$tenant,$actor,$one]=$this->publishedAggregate('instance-one');[$same,$sameActor,$two]=$this->draftAggregate('instance-two',$tenant,$actor);$authorized=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class)->authorize($actor);$agent=Agent::findOrFail($one->agent->id);DB::transaction(fn()=>$agent->pauseLifecycle($authorized));$agent->status=AgentStatus::Active;try{$agent->save();$this->fail('Permission must clear after success.');}catch(AiImmutableAttributeException){$agent->refresh();}
        $throw=true;$otherBlocked=false;Agent::saving(function(Agent$model)use(&$throw,&$otherBlocked,$two){if($throw&&$model->id!==$two->agent->id){$throw=false;$two->agent->status=AgentStatus::Paused;try{$two->agent->save();}catch(AiImmutableAttributeException){$otherBlocked=true;}throw new \RuntimeException('forced lifecycle failure');}});try{DB::transaction(fn()=>$agent->resumeLifecycle($authorized));$this->fail('Forced failure expected.');}catch(\RuntimeException){$this->assertTrue($otherBlocked);}$agent->refresh();$agent->status=AgentStatus::Active;$this->expectException(AiImmutableAttributeException::class);$agent->save();
    }

    public function test_direct_combined_state_and_content_changes_are_rejected():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('combined');$contracts=app(AgentContractVersionLifecycleService::class);$offered=$contracts->offer($actor,$created->contractVersion);$offered->status=AgentContractVersionStatus::Draft;$offered->job_to_be_done='tampered';try{$offered->save();$this->fail('Combined contract mutation must fail.');}catch(AiImmutableAttributeException){$this->assertSame(AgentContractVersionStatus::Offered,$offered->refresh()->status);}
        $testing=app(AgentVersionLifecycleService::class)->startTesting($actor,$created->agentVersion);$testing->status=AgentVersionStatus::Draft;$testing->configuration=$this->configurationArray(['behavior'=>['tone'=>'unsafe']]);try{$testing->save();$this->fail('Combined configuration mutation must fail.');}catch(AiImmutableAttributeException){$this->assertSame(AgentVersionStatus::Testing,$testing->refresh()->status);}
    }

    public function test_builder_blocks_remaining_bulk_and_append_only_paths():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('builder');app(AgentContractVersionLifecycleService::class)->offer($actor,$created->contractVersion);$event=AgentLifecycleEvent::firstOrFail();$operations=[fn()=>Agent::query()->increment('current_published_version_id'),fn()=>Agent::query()->decrement('id',1,['current_published_version_id'=>2]),fn()=>Agent::query()->insertGetId(['tenant_id'=>$tenant->id]),fn()=>AgentLifecycleEvent::query()->delete(),fn()=>$event->deleteQuietly(),fn()=>AgentLifecycleEvent::query()->update(['event'=>'forged']),fn()=>$event->updateQuietly(['event'=>'forged']),fn()=>AgentLifecycleEvent::query()->increment('actor_user_id'),fn()=>AgentLifecycleEvent::query()->truncate()];foreach($operations as$operation){try{$operation();$this->fail('Protected builder operation must fail.');}catch(AiImmutableAttributeException){$this->assertSame(1,AgentLifecycleEvent::count());}}
        $direct=new AgentLifecycleEvent();$this->expectException(AiImmutableAttributeException::class);$direct->save();
    }

    public function test_database_enforces_same_aggregate_pointers():void
    {
        [$tenant,$actor,$first]=$this->publishedAggregate('pointer-a');[$same,$sameActor,$second]=$this->draftAggregate('pointer-b',$tenant,$actor);$contracts=app(AgentContractVersionLifecycleService::class);$versions=app(AgentVersionLifecycleService::class);$offer=$contracts->offer($actor,$second->contractVersion);$contracts->accept($actor,$offer,$offer->content_hash,['method'=>'manual']);$versions->publish($actor,$versions->approve($actor,$versions->startTesting($actor,$second->agentVersion),['decision'=>'approved']));try{DB::table('ai_agents')->where('id',$first->agent->id)->update(['current_published_version_id'=>$second->agentVersion->id]);$this->fail('Cross-agent pointer must fail.');}catch(QueryException){$this->assertSame($first->agentVersion->id,$first->agent->refresh()->current_published_version_id);}try{DB::table('ai_agent_contracts')->where('id',$first->contract->id)->update(['current_accepted_version_id'=>$second->contractVersion->id]);$this->fail('Cross-contract pointer must fail.');}catch(QueryException){$this->assertSame($first->contractVersion->id,$first->contract->refresh()->current_accepted_version_id);}
    }

    public function test_terminalization_closes_work_versions_pointers_and_future_operations():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('terminal');$factory=app(CreateAgentVersionService::class);$agentWork=$factory->createAgentVersion($actor,$created->agent,$this->configuration(),$created->contractVersion);$contractWork=$factory->createContractVersion($actor,$created->contract,$this->contract('Pending terms'));app(AgentAggregateLifecycleService::class)->retire($actor,$created->agent,'Lifecycle complete');$this->assertSame(AgentStatus::Retired,$created->agent->refresh()->status);$this->assertNull($created->agent->current_published_version_id);$this->assertSame(AgentContractStatus::Ended,$created->contract->refresh()->status);$this->assertNull($created->contract->current_accepted_version_id);$this->assertSame(AgentVersionStatus::Retired,$agentWork->refresh()->status);$this->assertSame(AgentContractVersionStatus::Cancelled,$contractWork->refresh()->status);
        foreach([fn()=>$factory->createAgentVersion($actor,$created->agent,$this->configuration()),fn()=>$factory->createContractVersion($actor,$created->contract,$this->contract()),fn()=>app(AgentContractVersionLifecycleService::class)->offer($actor,$contractWork),fn()=>app(AgentVersionLifecycleService::class)->startTesting($actor,$agentWork)]as$operation){try{$operation();$this->fail('Terminal aggregate operation must fail.');}catch(AgentVersionConflictException|InvalidAgentTransitionException){$this->assertSame(AgentStatus::Retired,$created->agent->refresh()->status);}}
        $this->assertSame(1,AgentLifecycleEvent::where('event','agent_retired')->count());$this->assertSame(1,AgentLifecycleEvent::where('event','contract_ended')->count());
    }

    public function test_resume_validates_complete_chain_and_hashes():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('resume-chain');$aggregate=app(AgentAggregateLifecycleService::class);$aggregate->pause($actor,$created->agent,'Manual pause');DB::table('ai_agent_versions')->where('id',$created->agentVersion->id)->update(['configuration_hash'=>str_repeat('0',64)]);$events=AgentLifecycleEvent::count();try{$aggregate->resume($actor,$created->agent,'Attempt resume');$this->fail('Stale hash must block resume.');}catch(StaleAiContentHashException){$this->assertSame(AgentStatus::Paused,$created->agent->refresh()->status);$this->assertSame($events,AgentLifecycleEvent::count());}
    }

    public function test_no_false_pause_event_and_audit_failure_rolls_back():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('audit-rollback');$aggregate=app(AgentAggregateLifecycleService::class);$aggregate->pause($actor,$created->agent,'Manual pause');$before=AgentLifecycleEvent::where('event','agent_paused')->count();$aggregate->suspendContract($actor,$created->contract,'Contract hold');$this->assertSame($before,AgentLifecycleEvent::where('event','agent_paused')->count());
        [$tenant2,$actor2,$draft]=$this->draftAggregate('audit-failure');$throw=true;AgentLifecycleEvent::creating(function()use(&$throw){if($throw){$throw=false;throw new \RuntimeException('audit unavailable');}});try{app(AgentContractVersionLifecycleService::class)->offer($actor2,$draft->contractVersion);$this->fail('Audit failure expected.');}catch(\RuntimeException){$this->assertSame(AgentContractVersionStatus::Draft,$draft->contractVersion->refresh()->status);$this->assertNull($draft->contractVersion->content_hash);$this->assertNull($draft->contractVersion->offered_at);$this->assertNull($draft->contract->refresh()->current_accepted_version_id);$this->assertNull($draft->agent->refresh()->current_published_version_id);$this->assertSame(0,AgentLifecycleEvent::count());}
    }

    public function test_evidence_ip_variants_and_reason_policy_are_rejected():void
    {
        $guard=app(\App\Domain\AI\Agents\Support\AiLifecycleInputGuard::class);foreach([['clientIp'=>'192.0.2.1'],['nested'=>['remoteAddress'=>'value']],['source'=>'2001:db8::1'],['x-forwarded-for'=>'value'],['network address'=>'value']]as$evidence){try{$guard->evidence($evidence);$this->fail('Network evidence must fail.');}catch(\InvalidArgumentException $exception){$this->assertSame('Network address evidence is not allowed.',$exception->getMessage());}}foreach(['',str_repeat('x',501),"bad\x01reason"]as$reason){try{$guard->reason($reason);$this->fail('Invalid reason must fail.');}catch(\InvalidArgumentException $exception){$this->assertSame('Lifecycle reason must contain 1 to 500 safe characters.',$exception->getMessage());}}
    }

    public function test_hash_numeric_signed_zero_sparse_and_invalid_unicode_semantics():void
    {
        $hasher=app(CanonicalJsonHasher::class);$this->assertNotSame($hasher->hash(['n'=>1]),$hasher->hash(['n'=>1.0]));$this->assertNotSame($hasher->hash(['n'=>-0.0]),$hasher->hash(['n'=>0.0]));$this->assertSame($hasher->hash([10=>'ten',2=>'two']),$hasher->hash([2=>'two',10=>'ten']));$this->expectException(\InvalidArgumentException::class);$hasher->hash(['bad'=>"\xB1\x31"]);
    }

    public function test_authorization_snapshot_is_not_serializable_and_is_revalidated():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('snapshot');$authorization=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class);$snapshot=$authorization->authorize($actor);
        try{serialize($snapshot);$this->fail('Snapshot serialization must fail.');}catch(\LogicException $exception){$this->assertSame('AI lifecycle authorization snapshots cannot be serialized.',$exception->getMessage());}
        $other=$this->tenant('snapshot-other');app(TenantContext::class)->set($other);$this->expectException(AuthorizationException::class);$authorization->revalidate($snapshot);
    }

    public function test_snapshot_revalidation_rejects_revoked_membership_role_entitlement_feature_tenant_and_user():void
    {
        $cases=['membership','role','entitlement','feature','tenant','user'];
        foreach($cases as$case){if($case==='user'){$tenant=$this->tenant('revalidate-user');$actor=$this->user('revalidate-user@life.local');$this->membership($tenant,$actor,'owner');$this->entitle($tenant);app(TenantContext::class)->set($tenant);}else{[$tenant,$actor,$created]=$this->draftAggregate('revalidate-'.$case);}$authorization=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class);$snapshot=$authorization->authorize($actor);if($case==='membership')TenantMembership::where('tenant_id',$tenant->id)->where('user_id',$actor->id)->update(['status'=>'suspended']);if($case==='role')TenantMembership::where('tenant_id',$tenant->id)->where('user_id',$actor->id)->update(['role'=>'viewer']);if($case==='entitlement')Entitlement::where('tenant_id',$tenant->id)->update(['is_enabled'=>false]);if($case==='feature')config(['ai.enabled'=>false]);if($case==='tenant')$tenant->update(['status'=>'inactive']);if($case==='user'){TenantMembership::where('tenant_id',$tenant->id)->where('user_id',$actor->id)->delete();$actor->delete();}try{$authorization->revalidate($snapshot);$this->fail("Revoked {$case} must fail.");}catch(AuthorizationException|AiEntitlementException|AiDisabledException|AiTenantContextException $exception){$this->assertNotEmpty($exception->getMessage());}finally{config(['ai.enabled'=>true]);}}
    }

    public function test_nominal_methods_require_transaction_current_authorization_and_valid_original_state():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('nominal');$snapshot=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class)->authorize($actor);
        try{$created->contractVersion->markOffered($snapshot,'hash');$this->fail('Transaction is required.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentContractVersionStatus::Draft,$created->contractVersion->refresh()->status);}
        DB::transaction(function()use($created,$snapshot){try{$created->contractVersion->markAccepted($snapshot,'hash',['method'=>'manual']);$this->fail('Draft cannot be accepted directly.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentContractVersionStatus::Draft,$created->contractVersion->refresh()->status);}try{$created->agentVersion->markPublished($snapshot);$this->fail('Draft cannot publish directly.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentVersionStatus::Draft,$created->agentVersion->refresh()->status);}try{$created->agent->resumeLifecycle($snapshot);$this->fail('Draft cannot resume directly.');}catch(InvalidAgentTransitionException){$this->assertSame(AgentStatus::Draft,$created->agent->refresh()->status);}try{$created->agent->activateWithPublishedVersion($snapshot,$created->agentVersion);$this->fail('Draft version cannot activate.');}catch(AiTenantMismatchException){$this->assertSame(AgentStatus::Draft,$created->agent->refresh()->status);}try{$created->contract->activateWithAcceptedVersion($snapshot,$created->contractVersion);$this->fail('Draft contract version cannot activate.');}catch(AiTenantMismatchException){$this->assertSame(AgentContractStatus::Draft,$created->contract->refresh()->status);}});
    }

    public function test_nominal_method_and_audit_reject_stale_context_and_audit_has_no_generic_api():void
    {
        [$tenant,$actor,$created]=$this->publishedAggregate('stale-direct');$snapshot=app(\App\Domain\AI\Agents\Services\AiLifecycleAuthorization::class)->authorize($actor);$other=$this->tenant('stale-direct-other');app(TenantContext::class)->set($other);
        try{DB::transaction(fn()=>$created->agent->pauseLifecycle($snapshot));$this->fail('Stale tenant snapshot must fail.');}catch(AuthorizationException){app(TenantContext::class)->set($tenant);$this->assertSame(AgentStatus::Active,$created->agent->refresh()->status);}
        $audit=app(\App\Domain\AI\Agents\Services\AgentLifecycleAudit::class);$this->assertFalse(method_exists($audit,'append'));try{$audit->agentPaused($snapshot,$created->agent,'reason');$this->fail('Audit outside transaction must fail.');}catch(\LogicException){$this->assertSame(0,AgentLifecycleEvent::where('event','agent_paused')->count());}DB::transaction(function()use($audit,$snapshot,$created){try{$audit->agentPaused($snapshot,$created->agent,'reason');$this->fail('Audit state mismatch must fail.');}catch(\LogicException $exception){$this->assertStringContainsString('does not match',$exception->getMessage());}});TenantMembership::where('tenant_id',$tenant->id)->where('user_id',$actor->id)->update(['status'=>'suspended']);try{DB::transaction(fn()=>$audit->agentPaused($snapshot,$created->agent,'reason'));$this->fail('Stale audit authorization must fail.');}catch(AuthorizationException){$this->assertSame(0,AgentLifecycleEvent::where('event','agent_paused')->count());}
    }

    public function test_append_only_blocks_quiet_create_destroy_and_all_insert_variants():void
    {
        [$tenant,$actor,$created]=$this->draftAggregate('append-paths');app(AgentContractVersionLifecycleService::class)->offer($actor,$created->contractVersion);$event=AgentLifecycleEvent::firstOrFail();$operations=[fn()=>AgentLifecycleEvent::createQuietly([]),fn()=>AgentLifecycleEvent::destroy($event->id),fn()=>AgentLifecycleEvent::query()->insert([]),fn()=>AgentLifecycleEvent::query()->insertOrIgnore([]),fn()=>AgentLifecycleEvent::query()->insertUsing([],AgentLifecycleEvent::query()),fn()=>AgentLifecycleEvent::query()->insertGetId([]),fn()=>AgentLifecycleEvent::query()->upsert([],['id'])];foreach($operations as$operation){try{$operation();$this->fail('Append-only operation must fail.');}catch(AiImmutableAttributeException|\BadMethodCallException){$this->assertSame(1,AgentLifecycleEvent::count());}}
    }

    public function test_resume_rejects_each_broken_persisted_chain_without_event():void
    {
        $mutations=[['ai_agent_contracts','status','suspended'],['ai_agents','current_published_version_id',null],['ai_agent_versions','status','retired'],['ai_agent_contract_versions','status','superseded'],['ai_agent_versions','configuration_hash','bad'],['ai_agent_contract_versions','content_hash','bad'],['ai_agent_contract_versions','accepted_content_hash','bad']];
        foreach($mutations as$i=>$mutation){[$tenant,$actor,$created]=$this->publishedAggregate('resume-condition-'.$i);$service=app(AgentAggregateLifecycleService::class);$service->pause($actor,$created->agent,'pause');[$table,$column,$value]=$mutation;$id=str_contains($table,'contract_versions')?$created->contractVersion->id:(str_contains($table,'agent_versions')?$created->agentVersion->id:(str_contains($table,'contracts')?$created->contract->id:$created->agent->id));DB::table($table)->where('id',$id)->update([$column=>$value]);$events=AgentLifecycleEvent::where('event','agent_resumed')->count();try{$service->resume($actor,$created->agent,'resume');$this->fail("Broken resume condition {$i} must fail.");}catch(AgentContractNotAcceptedException|StaleAiContentHashException $exception){$this->assertSame(AgentStatus::Paused,$created->agent->refresh()->status);$this->assertSame($events,AgentLifecycleEvent::where('event','agent_resumed')->count());}}
    }

    public function test_lifecycle_architecture_limits_nominal_calls_and_event_append_boundary():void
    {
        $allowed=['AgentAggregateLifecycleService.php','AgentContractVersionLifecycleService.php','AgentVersionLifecycleService.php','AgentLifecycleAudit.php'];$nominal='->(markOffered|markAccepted|markRejected|markWithdrawn|markCancelled|markSuperseded|markTesting|returnToDraftLifecycle|markApproved|markPublished|markRetired|markRetiredForTermination|activateWithPublishedVersion|activateWithAcceptedVersion|pauseLifecycle|pauseAndClearPublishedVersion|resumeLifecycle|retireLifecycle|suspendLifecycle|endLifecycle)\\(';$violations=[];$eventCallers=[];$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Domain/AI'),\FilesystemIterator::SKIP_DOTS));foreach($iterator as$file){if($file->getExtension()!=='php')continue;$content=file_get_contents($file->getPathname());if(preg_match('/'.$nominal.'/',$content)&&!in_array($file->getFilename(),$allowed,true)&&!str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR))$violations[]=$file->getPathname();if(str_contains($content,'->appendFromAudit(')&&$file->getFilename()!=='AgentLifecycleAudit.php')$eventCallers[]=$file->getPathname();}$this->assertSame([],$violations);$this->assertSame([],$eventCallers);
    }

    private function publishedAggregate(string$slug):array{[$tenant,$actor,$created]=$this->draftAggregate($slug);$contracts=app(AgentContractVersionLifecycleService::class);$versions=app(AgentVersionLifecycleService::class);$offered=$contracts->offer($actor,$created->contractVersion);$contracts->accept($actor,$offered,$offered->content_hash,['method'=>'manual']);$testing=$versions->startTesting($actor,$created->agentVersion);$versions->publish($actor,$versions->approve($actor,$testing,['decision'=>'approved']));return[$tenant,$actor,$created];}
    private function draftAggregate(string$slug,?Tenant$tenant=null,?User$actor=null):array{if(!$tenant){$tenant=$this->tenant($slug);$actor=$this->user($slug.'@life.local');$this->membership($tenant,$actor,'owner');$this->entitle($tenant);}app(TenantContext::class)->set($tenant);$created=app(CreateAgentDraftService::class)->create($actor,new CreateAgentDraftData($slug,'Agent '.$slug,null,AgentType::Sales,$this->contract(),$this->configuration()));return[$tenant,$actor,$created];}
    private function configuration():AgentConfigurationData{return new AgentConfigurationData('1.0',$this->configurationArray());}
    private function configurationArray(array$replace=[]):array{return array_replace(['identity'=>['name'=>'Advisor'],'goals'=>['qualify'],'behavior'=>['tone'=>'helpful'],'guardrails'=>[],'qualification'=>[],'handoff'=>[],'capabilities'=>[],'metadata'=>[]],$replace);}
    private function contract(string$job='Qualify opportunities'):AgentContractDraftData{return new AgentContractDraftData($job,['Qualify lead'],['lead_capture'],[],['webchat'],[],['capture_lead'],['mode'=>'human'],['success'=>'qualified'],['max'=>10],['seconds'=>30],['pii'=>'minimal'],['mode'=>'manual']);}
    private function tenant(string$slug):Tenant{return Tenant::create(['name'=>$slug,'slug'=>$slug,'status'=>'active']);}
    private function user(string$email):User{return User::create(['name'=>'User','email'=>$email,'password'=>'hashed','empresa_id'=>1]);}
    private function membership(Tenant$t,User$u,string$role):TenantMembership{return TenantMembership::create(['tenant_id'=>$t->id,'user_id'=>$u->id,'role'=>$role,'status'=>'active']);}
    private function entitle(Tenant$t):void{$module=Module::firstOrCreate(['code'=>'AI_CORE'],['name'=>'AI Core','type'=>'core','is_active'=>true,'sort_order'=>1]);$plan=Plan::create(['code'=>'PLAN_'.$t->id,'name'=>'AI','status'=>'active','currency'=>'MXN']);$subscription=Subscription::create(['tenant_id'=>$t->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now()->subDay(),'current_period_start'=>now()->subDay(),'current_period_end'=>now()->addMonth()]);Entitlement::create(['subscription_id'=>$subscription->id,'tenant_id'=>$t->id,'module_id'=>$module->id,'code'=>'AI_CORE','is_enabled'=>true,'source'=>'plan']);}
    private function schema():void{DB::statement('PRAGMA foreign_keys = ON');foreach(['ai_agent_lifecycle_events','ai_agent_versions','ai_agent_contract_versions','ai_agent_contracts','ai_agents','network_entitlements','network_subscriptions','network_tenant_memberships','network_plan_modules','network_modules','network_plans','network_tenants','users']as$table)Schema::dropIfExists($table);Schema::create('users',function(Blueprint$t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();});Schema::create('network_tenants',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->timestamps();});Schema::create('network_tenant_memberships',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps();$t->unique(['tenant_id','user_id']);});Schema::create('network_plans',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->char('currency',3);$t->timestamps();});Schema::create('network_modules',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});Schema::create('network_plan_modules',function(Blueprint$t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->timestamps();});Schema::create('network_subscriptions',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');foreach(['started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at']as$c)$t->timestamp($c)->nullable();$t->timestamps();});Schema::create('network_entitlements',function(Blueprint$t){$t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('module_id');$t->string('code');$t->boolean('is_enabled');$t->string('source');$t->timestamps();});$this->agentMigration()->up();$this->lifecycleMigration()->up();}
    private function agentMigration():object{return require base_path('database/migrations/2026_08_22_100000_create_ai_agent_domain_tables.php');}
    private function lifecycleMigration():object{return require base_path('database/migrations/2026_08_23_100000_add_ai_agent_lifecycle.php');}
}
