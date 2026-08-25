<?php
namespace Tests\Feature;
use App\Domain\AI\Agents\Data\{AgentConfigurationData,AgentContractDraftData,CreateAgentDraftData};
use App\Domain\AI\Agents\Enums\{AgentContractStatus,AgentContractVersionStatus,AgentStatus,AgentType,AgentVersionStatus};
use App\Domain\AI\Agents\Models\{Agent,AgentContract,AgentContractVersion,AgentVersion};
use App\Domain\AI\Agents\Services\{CreateAgentDraftService,UpdateAgentContractActionsService};
use App\Domain\AI\Support\Exceptions\{AiDisabledException,AiEntitlementException,AiTenantContextException,AiTenantMismatchException};
use App\Domain\AI\Support\Exceptions\AiImmutableAttributeException;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Billing\Models\{Entitlement,Subscription};
use App\Domain\Network\Catalog\Models\{Module,Plan};
use App\Domain\Network\Tenancy\Models\{Tenant,TenantMembership};
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use InvalidArgumentException;
use Tests\TestCase;

final class AiAgentDomainTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();config(['ai.enabled'=>true,'ai.entitlement.module_code'=>'AI_CORE']);$this->schema();app(TenantContext::class)->clear();
    }

    public function test_creates_complete_draft_aggregate_with_relations_and_casts():void
    {
        [$tenant,$actor]=$this->authorizedTenant('complete');$created=$this->service()->create($actor,$this->draft('sales-one'));
        $this->assertSame([$tenant->id,$tenant->id,$tenant->id,$tenant->id],[$created->agent->tenant_id,$created->contract->tenant_id,$created->contractVersion->tenant_id,$created->agentVersion->tenant_id]);$this->assertSame(AgentStatus::Draft,$created->agent->status);$this->assertSame(AgentType::Sales,$created->agent->type);
        $this->assertSame(AgentContractStatus::Draft,$created->contract->status);$this->assertSame(AgentContractVersionStatus::Draft,$created->contractVersion->status);$this->assertSame(1,$created->contractVersion->version_number);
        $this->assertSame(AgentVersionStatus::Draft,$created->agentVersion->status);$this->assertSame(1,$created->agentVersion->version_number);$this->assertSame($created->contractVersion->id,$created->agentVersion->agent_contract_version_id);
        $this->assertTrue($created->agent->contract->is($created->contract));$this->assertTrue($created->agent->versions->first()->is($created->agentVersion));$this->assertTrue($created->agentVersion->contractVersion->is($created->contractVersion));$this->assertTrue($created->contractVersion->agent->is($created->agent));
        $this->assertIsArray($created->agentVersion->configuration);$this->assertIsArray($created->contractVersion->objectives);$this->assertSame([$actor->id,$actor->id,$actor->id,$actor->id],[$created->agent->created_by_user_id,$created->contract->created_by_user_id,$created->contractVersion->created_by_user_id,$created->agentVersion->created_by_user_id]);
    }

    public function test_draft_allowed_actions_are_trusted_entitled_and_tamper_proof():void
    {
        [$tenant,$actor]=$this->authorizedTenant('actions-ui');$subscription=Subscription::where('tenant_id',$tenant->id)->firstOrFail();
        foreach(['SHIPPING','TRACKING']as$code){$module=Module::create(['code'=>$code,'name'=>$code,'type'=>'core','is_active'=>true,'sort_order'=>2]);Entitlement::create(['subscription_id'=>$subscription->id,'tenant_id'=>$tenant->id,'module_id'=>$module->id,'code'=>$code,'is_enabled'=>true,'source'=>'plan']);}
        $created=$this->service()->create($actor,$this->draft('actions-ui-agent'));$service=app(UpdateAgentContractActionsService::class);
        $updated=$service->update($actor,$created->agent,['zigo.track_shipment','zigo.quote_shipment','zigo.create_shipment_guide']);
        $this->assertSame(['zigo.create_shipment_guide','zigo.quote_shipment','zigo.track_shipment'],$updated->allowed_actions);
        Entitlement::where('tenant_id',$tenant->id)->where('code','TRACKING')->update(['is_enabled'=>false]);
        try{$service->update($actor,$created->agent,['zigo.track_shipment']);$this->fail('Unavailable Action tampering must fail.');}catch(\DomainException){$this->assertSame(['zigo.create_shipment_guide','zigo.quote_shipment','zigo.track_shipment'],$updated->fresh()->allowed_actions);}
        try{$service->update($actor,$created->agent,['unknown.action']);$this->fail('Unknown Action tampering must fail.');}catch(\DomainException){$this->addToAssertionCount(1);}
    }

    public function test_external_tenant_id_is_rejected_and_active_context_is_used():void
    {
        [$tenant,$actor]=$this->authorizedTenant('spoof-owner');$other=$this->tenant('spoof-other');
        try{$agent=new Agent();$agent->tenant_id=$other->id;$agent->code='SPOOF';$agent->name='Spoof';$agent->type=AgentType::Sales;$agent->status=AgentStatus::Draft;$agent->created_by_user_id=$actor->id;$agent->save();$this->fail('Spoofed tenant should fail.');}catch(AiTenantMismatchException){$this->assertSame(0,Agent::count());}
        $agent=$this->standaloneAgent($actor,'trusted');$this->assertSame($tenant->id,$agent->tenant_id);
    }

    public function test_other_tenant_actor_and_non_manager_are_rejected():void
    {
        [$tenant]=$this->authorizedTenant('authorization');$otherUser=$this->user('other@test.local');$viewer=$this->user('viewer@test.local');
        $otherTenant=$this->tenant('actor-other');$this->membership($otherTenant,$otherUser,'owner');$this->membership($tenant,$viewer,'viewer');
        foreach([$otherUser,$viewer]as$actor){try{$this->service()->create($actor,$this->draft('forbidden-'.$actor->id));$this->fail('Unauthorized actor should fail.');}catch(AuthorizationException){$this->assertSame(0,Agent::count());}}
    }

    public function test_feature_entitlement_and_context_fail_closed():void
    {
        $tenant=$this->tenant('no-entitlement');$actor=$this->user('owner@none.local');$this->membership($tenant,$actor,'owner');app(TenantContext::class)->set($tenant);
        try{$this->service()->create($actor,$this->draft('no-entitlement'));$this->fail('Entitlement should be required.');}catch(AiEntitlementException){$this->assertSame(0,DB::table('ai_agents')->count());}
        config(['ai.enabled'=>false]);try{$this->service()->create($actor,$this->draft('disabled'));$this->fail('AI should be disabled.');}catch(AiDisabledException){$this->assertTrue(true);}
        config(['ai.enabled'=>true]);app(TenantContext::class)->clear();$this->expectException(AiTenantContextException::class);$this->service()->create($actor,$this->draft('no-context'));
    }

    public function test_code_is_unique_per_tenant_but_reusable_across_tenants():void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 100]);
        [$first,$firstActor]=$this->authorizedTenant('code-a');$this->service()->create($firstActor,$this->draft('shared'));
        try{$this->service()->create($firstActor,$this->draft('shared'));$this->fail('Duplicate code should fail.');}catch(QueryException){$this->assertSame(1,Agent::count());}
        [$second,$secondActor]=$this->authorizedTenant('code-b');$this->service()->create($secondActor,$this->draft('shared'));$this->assertSame(1,Agent::count());
        app(TenantContext::class)->set($first);$this->assertSame(1,Agent::count());app(TenantContext::class)->set($second);$this->assertSame(1,Agent::count());
    }

    public function test_normal_queries_are_tenant_scoped_and_fail_without_context():void
    {
        [$first,$actorA]=$this->authorizedTenant('query-a');$this->service()->create($actorA,$this->draft('agent-a'));
        [$second,$actorB]=$this->authorizedTenant('query-b');$this->service()->create($actorB,$this->draft('agent-b'));
        $this->assertSame(['AGENT-B'],Agent::pluck('code')->all());app(TenantContext::class)->set($first);$this->assertSame(['AGENT-A'],Agent::pluck('code')->all());
        app(TenantContext::class)->clear();$this->expectException(AiTenantContextException::class);Agent::query()->get();
    }

    public function test_tenant_id_is_immutable():void
    {
        [$tenant,$actor]=$this->authorizedTenant('immutable');$agent=$this->service()->create($actor,$this->draft('immutable'))->agent;$other=$this->tenant('immutable-other');
        $agent->tenant_id=$other->id;$this->expectException(AiImmutableAttributeException::class);$agent->save();
    }

    public function test_cross_tenant_contract_and_cross_agent_contract_version_are_rejected():void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 100]);
        [$first,$actorA]=$this->authorizedTenant('cross-a');$createdA=$this->service()->create($actorA,$this->draft('cross-a'));
        [$second,$actorB]=$this->authorizedTenant('cross-b');$createdB=$this->service()->create($actorB,$this->draft('cross-b'));
        app(TenantContext::class)->set($first);
        try{$contract=new AgentContract();$contract->agent_id=$createdB->agent->id;$contract->status=AgentContractStatus::Draft;$contract->created_by_user_id=$actorA->id;$contract->save();$this->fail('Cross tenant contract should fail.');}catch(AiTenantMismatchException){$this->assertTrue(true);}
        $createdA2=$this->service()->create($actorA,$this->draft('cross-a-two'));$version=$createdA->agentVersion;$version->agent_contract_version_id=$createdA2->contractVersion->id;
        $this->expectException(AiImmutableAttributeException::class);$version->save();
    }

    public function test_relationships_remain_tenant_scoped():void
    {
        [$first,$actorA]=$this->authorizedTenant('relations-a');$a=$this->service()->create($actorA,$this->draft('relations-a'));
        [$second,$actorB]=$this->authorizedTenant('relations-b');$this->service()->create($actorB,$this->draft('relations-b'));
        app(TenantContext::class)->set($first);$agent=Agent::findOrFail($a->agent->id);$this->assertCount(1,$agent->versions);$this->assertSame($first->id,$agent->contract->tenant_id);$this->assertSame($first->id,$agent->versions->first()->tenant_id);
    }

    public function test_configuration_requires_structure_and_rejects_sensitive_or_non_json_data():void
    {
        $valid=$this->configuration();$this->assertSame('1.0',$valid->schemaVersion);
        foreach([
            fn()=>new AgentConfigurationData('1.0',[]),
            fn()=>new AgentConfigurationData('1.0',$this->configurationArray(['metadata'=>['api_key'=>'forbidden']])),
            fn()=>new AgentConfigurationData('1.0',$this->configurationArray(['metadata'=>['value'=>fopen('php://memory','rb')]])),
        ]as$factory){try{$factory();$this->fail('Invalid configuration should fail.');}catch(InvalidArgumentException){$this->assertTrue(true);}}
    }

    public function test_contract_requires_job_lists_structured_policies_and_no_secrets():void
    {
        foreach([
            fn()=>new AgentContractDraftData('',[],[],[],[],[],[],[],[],[],[],[],[]),
            fn()=>new AgentContractDraftData('Sell',['Qualify'],[],[],[],[],[],['invalid-list'],[],[],[],[],[]),
            fn()=>new AgentContractDraftData('Sell',['Qualify'],[],[],[],[],[],['mode'=>'human'],[],[],[],[],['password'=>'forbidden']),
        ]as$factory){try{$factory();$this->fail('Invalid contract should fail.');}catch(InvalidArgumentException){$this->assertTrue(true);}}
    }

    public function test_transaction_rolls_back_on_duplicate_failure():void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 100]);
        [$tenant,$actor]=$this->authorizedTenant('rollback');$this->service()->create($actor,$this->draft('rollback'));
        $before=['agents'=>DB::table('ai_agents')->count(),'contracts'=>DB::table('ai_agent_contracts')->count(),'contract_versions'=>DB::table('ai_agent_contract_versions')->count(),'agent_versions'=>DB::table('ai_agent_versions')->count()];
        try{$this->service()->create($actor,$this->draft('rollback'));$this->fail('Duplicate should fail.');}catch(QueryException){}
        $this->assertSame($before,['agents'=>DB::table('ai_agents')->count(),'contracts'=>DB::table('ai_agent_contracts')->count(),'contract_versions'=>DB::table('ai_agent_contract_versions')->count(),'agent_versions'=>DB::table('ai_agent_versions')->count()]);
    }

    public function test_real_migration_up_down_foreign_keys_indexes_and_defaults():void
    {
        foreach(['ai_agents','ai_agent_contracts','ai_agent_contract_versions','ai_agent_versions']as$table)$this->assertTrue(Schema::hasTable($table));
        $this->assertSame(1,(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        foreach(['ai_agents','ai_agent_contracts','ai_agent_contract_versions','ai_agent_versions']as$table)$this->assertNotEmpty(DB::select("PRAGMA foreign_key_list('{$table}')"));
        $this->assertNotEmpty(DB::select("PRAGMA index_list('ai_agents')"));
        [$tenant,$actor]=$this->authorizedTenant('defaults');$agent=new Agent();$agent->code='DEFAULT';$agent->name='Default';$agent->type=AgentType::Sales;$agent->created_by_user_id=$actor->id;$agent->save();$agent->refresh();$this->assertSame(AgentStatus::Draft,$agent->status);
        app(TenantContext::class)->clear();DB::table('ai_agents')->delete();$this->agentMigration()->down();
        foreach(['ai_agents','ai_agent_contracts','ai_agent_contract_versions','ai_agent_versions']as$table)$this->assertFalse(Schema::hasTable($table));
        $this->agentMigration()->up();
    }

    public function test_restrict_delete_preserves_complete_history():void
    {
        [$tenant,$actor]=$this->authorizedTenant('history');$created=$this->service()->create($actor,$this->draft('history'));
        foreach([
            fn()=>$tenant->delete(),fn()=>$created->agent->delete(),fn()=>$created->contract->delete(),fn()=>$created->contractVersion->delete(),
        ]as$delete){try{$delete();$this->fail('Historical delete should be restricted.');}catch(QueryException){$this->assertSame([1,1,1,1],[DB::table('ai_agents')->count(),DB::table('ai_agent_contracts')->count(),DB::table('ai_agent_contract_versions')->count(),DB::table('ai_agent_versions')->count()]);}}
    }

    public function test_scope_applies_to_find_first_count_update_and_delete():void
    {
        [$first,$actorA]=$this->authorizedTenant('scope-a');$a=$this->standaloneAgent($actorA,'scope-a');
        [$second,$actorB]=$this->authorizedTenant('scope-b');$b=$this->standaloneAgent($actorB,'scope-b');
        $this->assertNull(Agent::find($a->id));$this->assertSame($b->id,Agent::first()->id);$this->assertSame(1,Agent::count());
        $this->assertSame(1,Agent::query()->update(['name'=>'Updated B']));$this->assertSame('Scope A',DB::table('ai_agents')->where('id',$a->id)->value('name'));
        $this->assertSame(1,Agent::query()->delete());$this->assertTrue(DB::table('ai_agents')->where('id',$a->id)->exists());$this->assertFalse(DB::table('ai_agents')->where('id',$b->id)->exists());
        app(TenantContext::class)->set($first);$this->assertSame($a->id,Agent::findOrFail($a->id)->id);
    }

    public function test_save_and_bulk_writes_cannot_change_identity_or_relations():void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 100]);
        [$tenant,$actor]=$this->authorizedTenant('immutable-all');$one=$this->service()->create($actor,$this->draft('immutable-one'));$two=$this->service()->create($actor,$this->draft('immutable-two'));
        foreach([
            [$one->agent,'created_by_user_id',$actor->id+100],[$one->contract,'agent_id',$two->agent->id],[$one->contractVersion,'agent_contract_id',$two->contract->id],[$one->contractVersion,'version_number',2],[$one->agentVersion,'agent_id',$two->agent->id],[$one->agentVersion,'agent_contract_version_id',$two->contractVersion->id],[$one->agentVersion,'version_number',2],
        ]as[$model,$attribute,$value]){try{$model->{$attribute}=$value;$model->save();$this->fail("{$attribute} should be immutable.");}catch(AiImmutableAttributeException){$model->refresh();$this->assertNotSame($value,$model->{$attribute});}}
        foreach([
            fn()=>Agent::query()->update(['tenant_id'=>$tenant->id+1]),fn()=>AgentContract::query()->update(['agent_id'=>$two->agent->id]),fn()=>AgentContractVersion::query()->update(['agent_contract_id'=>$two->contract->id]),fn()=>AgentVersion::query()->update(['agent_contract_version_id'=>$two->contractVersion->id]),fn()=>Agent::query()->insert([['code'=>'RAW']]),fn()=>Agent::query()->upsert([['code'=>'RAW']],['code']),
        ]as$write){try{$write();$this->fail('Protected bulk write should fail.');}catch(AiImmutableAttributeException){$this->assertTrue(true);}}
        $this->assertSame(2,Agent::query()->update(['description'=>'editable']));
    }

    public function test_admin_is_allowed_while_suspended_viewer_and_other_tenant_are_rejected():void
    {
        $tenant=$this->tenant('roles');$admin=$this->user('admin@roles.local');$viewer=$this->user('viewer2@roles.local');$suspended=$this->user('suspended@roles.local');$other=$this->user('other2@roles.local');
        $this->membership($tenant,$admin,'admin');$this->membership($tenant,$viewer,'viewer');$membership=$this->membership($tenant,$suspended,'admin');$membership->status='suspended';$membership->save();$otherTenant=$this->tenant('roles-other');$this->membership($otherTenant,$other,'owner');$this->entitle($tenant);app(TenantContext::class)->set($tenant);
        $this->assertInstanceOf(Agent::class,$this->service()->create($admin,$this->draft('admin'))->agent);
        foreach([$viewer,$suspended,$other]as$actor){try{$this->service()->create($actor,$this->draft('denied-'.$actor->id));$this->fail('Actor should be rejected.');}catch(AuthorizationException){$this->assertTrue(true);}}
    }

    public function test_all_cross_tenant_and_cross_agent_links_are_rejected():void
    {
        config(['ai.capacity.defaults.MAX_AGENTS' => 100]);
        [$first,$actorA]=$this->authorizedTenant('links-a');$a=$this->service()->create($actorA,$this->draft('links-a'));
        [$second,$actorB]=$this->authorizedTenant('links-b');$b=$this->service()->create($actorB,$this->draft('links-b'));app(TenantContext::class)->set($first);
        foreach([
            function()use($b,$actorA){$v=new AgentContractVersion();$v->agent_contract_id=$b->contract->id;$v->version_number=2;$v->status=AgentContractVersionStatus::Draft;$v->job_to_be_done='x';foreach($this->contract()->toArray()as$k=>$value)$v->{$k}=$value;$v->created_by_user_id=$actorA->id;$v->save();},
            function()use($b,$actorA){$v=new AgentVersion();$v->agent_id=$b->agent->id;$v->version_number=2;$v->status=AgentVersionStatus::Draft;$v->schema_version='1';$v->configuration=$this->configuration()->configuration;$v->created_by_user_id=$actorA->id;$v->save();},
        ]as$create){try{$create();$this->fail('Cross tenant link should fail.');}catch(AiTenantMismatchException){$this->assertTrue(true);}}
        $v=new AgentVersion();$v->agent_id=$a->agent->id;$v->agent_contract_version_id=$this->service()->create($actorA,$this->draft('links-a-two'))->contractVersion->id;$v->version_number=2;$v->status=AgentVersionStatus::Draft;$v->schema_version='1';$v->configuration=$this->configuration()->configuration;$v->created_by_user_id=$actorA->id;$this->expectException(AiTenantMismatchException::class);$v->save();
    }

    public function test_structured_data_rejects_objects_secrets_non_finite_values_and_prompts():void
    {
        $jsonObject=new class implements \JsonSerializable{public function jsonSerialize():mixed{return['password'=>'hidden'];}};
        foreach([
            ['object'=>(object)['safe'=>'value']],['json'=>$jsonObject],['closure'=>fn()=>true],['resource'=>fopen('php://memory','rb')],['nan'=>NAN],['infinity'=>INF],['apiKey'=>'x'],['APIKey'=>'x'],['clientSecret'=>'x'],['access-token'=>'x'],['refreshToken'=>'x'],['privateKey'=>'x'],['metadata'=>['prompt'=>'x']],['metadata'=>['systemPrompt'=>'x']],
        ]as$value){try{new AgentConfigurationData('1.0',$this->configurationArray(['metadata'=>$value]));$this->fail('Unsafe structured data should fail.');}catch(InvalidArgumentException){$this->assertTrue(true);}}
        foreach(['','   ']as$schema){try{new AgentConfigurationData($schema,$this->configurationArray());$this->fail('Blank schema should fail.');}catch(InvalidArgumentException){$this->assertTrue(true);}}
    }

    public function test_transaction_rolls_back_after_agent_and_contract_were_inserted():void
    {
        [$tenant,$actor]=$this->authorizedTenant('late-rollback');$throw=true;
        AgentContractVersion::creating(function()use(&$throw){if($throw)throw new \RuntimeException('late failure');});
        try{$this->service()->create($actor,$this->draft('late-rollback'));$this->fail('Late failure expected.');}catch(\RuntimeException){$throw=false;}
        $this->assertSame([0,0,0,0],[DB::table('ai_agents')->count(),DB::table('ai_agent_contracts')->count(),DB::table('ai_agent_contract_versions')->count(),DB::table('ai_agent_versions')->count()]);
    }

    public function test_agent_limit_uses_real_creation_service():void{config(['ai.capacity.defaults.MAX_AGENTS'=>1]);[$tenant,$actor]=$this->authorizedTenant('capacity-create');$this->service()->create($actor,$this->draft('CAPACITY_A'));$this->assertSame(1,Agent::count());try{$this->service()->create($actor,$this->draft('CAPACITY_B'));$this->fail('Second Agent must exceed capacity.');}catch(\App\Domain\AI\Usage\Exceptions\AiCapacityExceededException){$this->addToAssertionCount(1);}$this->assertSame(1,Agent::count());}

    public function test_historical_ai_core_defaults_are_real_and_block_new_agent(): void
    {
        [$tenant, $actor] = $this->authorizedTenant('legacy-defaults');
        $historical = $this->standaloneAgent($actor, 'historical');
        $capacity = app(AiCapacityService::class);
        $this->assertSame([1, 1, 0, 1000, 100, 250], [
            $capacity->limit($tenant, AiCapacityService::MAX_AGENTS),
            $capacity->limit($tenant, AiCapacityService::MAX_WEBCHAT_CHANNELS),
            $capacity->limit($tenant, AiCapacityService::MAX_WHATSAPP_CHANNELS),
            $capacity->limit($tenant, AiCapacityService::MONTHLY_RUNTIME_UNITS),
            $capacity->limit($tenant, AiCapacityService::MONTHLY_ACTION_RUNS),
            $capacity->limit($tenant, AiCapacityService::MONTHLY_CONVERSATIONS),
        ]);
        $this->assertSame(1, $capacity->used($tenant, AiCapacityService::MAX_AGENTS));
        try { $this->service()->create($actor, $this->draft('legacy-second')); $this->fail('Legacy capacity must block a second Agent.'); }
        catch (\App\Domain\AI\Usage\Exceptions\AiCapacityExceededException) { $this->addToAssertionCount(1); }
        $this->assertDatabaseHas('ai_agents', ['id' => $historical->id]);
    }
    private function service():CreateAgentDraftService{return app(CreateAgentDraftService::class);}
    private function standaloneAgent(User$actor,string$code):Agent{$agent=new Agent();$agent->code=strtoupper($code);$agent->name=ucwords(str_replace('-',' ',$code));$agent->type=AgentType::Sales;$agent->status=AgentStatus::Draft;$agent->created_by_user_id=$actor->id;$agent->save();return$agent;}
    private function draft(string$code):CreateAgentDraftData{return new CreateAgentDraftData($code,'Sales Agent',null,AgentType::Sales,$this->contract(),$this->configuration());}
    private function configuration():AgentConfigurationData{return new AgentConfigurationData('1.0',$this->configurationArray());}
    private function configurationArray(array$replace=[]):array{return array_replace(['identity'=>['name'=>'Advisor'],'goals'=>['qualify'],'behavior'=>['tone'=>'helpful'],'guardrails'=>[],'qualification'=>[],'handoff'=>[],'capabilities'=>[],'metadata'=>[]],$replace);}
    private function contract():AgentContractDraftData{return new AgentContractDraftData('Qualify sales opportunities',['Qualify lead'],['lead_capture'],['payment_capture'],['webchat'],['sources'=>['faq']],['capture_lead'],['mode'=>'human'],['success'=>'qualified'],['max_conversations'=>10],['response_seconds'=>30],['pii'=>'minimal'],['mode'=>'manual']);}
    private function authorizedTenant(string$slug):array{$tenant=$this->tenant($slug);$actor=$this->user($slug.'@test.local');$this->membership($tenant,$actor,'owner');$this->entitle($tenant);app(TenantContext::class)->set($tenant);return[$tenant,$actor];}
    private function tenant(string$slug,string$status='active'):Tenant{return Tenant::create(['name'=>ucfirst($slug),'slug'=>$slug,'status'=>$status]);}
    private function user(string$email):User{return User::create(['name'=>'User','email'=>$email,'password'=>'hashed','empresa_id'=>1]);}
    private function membership(Tenant$t,User$u,string$role):TenantMembership{return TenantMembership::create(['tenant_id'=>$t->id,'user_id'=>$u->id,'role'=>$role,'status'=>'active']);}
    private function entitle(Tenant$t):void{$module=Module::firstOrCreate(['code'=>'AI_CORE'],['name'=>'AI Core','type'=>'core','is_active'=>true,'sort_order'=>1]);$plan=Plan::create(['code'=>'PLAN_'.$t->id,'name'=>'AI','status'=>'active','currency'=>'MXN']);$subscription=Subscription::create(['tenant_id'=>$t->id,'plan_id'=>$plan->id,'status'=>'active','started_at'=>now()->subDay(),'current_period_start'=>now()->subDay(),'current_period_end'=>now()->addMonth()]);Entitlement::create(['subscription_id'=>$subscription->id,'tenant_id'=>$t->id,'module_id'=>$module->id,'code'=>'AI_CORE','is_enabled'=>true,'source'=>'plan']);}

    private function schema():void
    {
        DB::statement('PRAGMA foreign_keys = ON');
        foreach(['ai_agent_versions','ai_agent_contract_versions','ai_agent_contracts','ai_agents','network_entitlements','network_subscriptions','network_tenant_memberships','network_plan_modules','network_modules','network_plans','network_tenants','users']as$table)Schema::dropIfExists($table);
        Schema::create('users',function(Blueprint$t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();});
        Schema::create('network_tenants',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps();});
        Schema::create('network_tenant_memberships',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps();$t->unique(['tenant_id','user_id']);});
        Schema::create('network_plans',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
        Schema::create('network_modules',function(Blueprint$t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
        Schema::create('network_plan_modules',function(Blueprint$t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();});
        Schema::create('network_subscriptions',function(Blueprint$t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');$t->unsignedInteger('operations_limit')->nullable();foreach(['started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at']as$c)$t->timestamp($c)->nullable();$t->timestamps();});
        Schema::create('network_entitlements',function(Blueprint$t){$t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('module_id');$t->string('code');$t->boolean('is_enabled');$t->unsignedInteger('limit_value')->nullable();$t->string('source');$t->timestamps();});
        $this->agentMigration()->up();
    }

    private function agentMigration():object{return require base_path('database/migrations/2026_08_22_100000_create_ai_agent_domain_tables.php');}
}
