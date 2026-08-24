<?php

namespace Tests\Feature;

use App\Domain\AI\Runtime\Enums\AiExecutionMode;
use App\Domain\AI\Simulator\Support\SimulationScenarioDefinition;
use App\Domain\AI\Simulator\Support\ScenarioDefinitionFromForm;
use App\Domain\AI\Simulator\Support\SimulationPayloadLimits;
use App\Domain\AI\Actions\{ActionRegistry};
use App\Domain\AI\Actions\Contracts\ActionHandler;
use App\Domain\AI\Actions\Data\{ActionDefinition,ActionExecutionContext,ActionResultData};
use App\Domain\AI\Actions\Enums\{ActionConfirmationPolicy,ActionEffect};
use App\Domain\AI\Agents\Models\AgentVersion;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class AiSimulationFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']); DB::statement('PRAGMA foreign_keys=ON');
    }
    public function test_execution_mode_is_closed_server_vocabulary(): void
    {$this->assertSame(['live','simulation'],array_map(fn($case)=>$case->value,AiExecutionMode::cases()));}
    public function test_definition_is_deterministic_bounded_and_rejects_scripting_fields(): void
    {$definition=app(SimulationScenarioDefinition::class)->normalize(['turns'=>['Uno','Dos'],'action_results'=>[],'assertions'=>[['type'=>'response_completed']]]);$this->assertSame(['Uno','Dos'],$definition['turns']);$this->assertFalse($definition['simulate_confirmation']);$this->expectException(\InvalidArgumentException::class);app(SimulationScenarioDefinition::class)->normalize(['turns'=>['x'],'script'=>'system("x")']);}
    public function test_non_technical_builder_rebuilds_canonical_definition_and_rejects_tampering():void
    {
        $handler=new class implements ActionHandler{public function execute(ActionExecutionContext$context,array$arguments):ActionResultData{return new ActionResultData(['value'=>'never']);}};$registry=new ActionRegistry;$registry->register(new ActionDefinition('example.lookup_record','Consultar','Consulta segura',['type'=>'object','additionalProperties'=>false,'required'=>['reference'],'properties'=>['reference'=>['type'=>'string']]],['type'=>'object','additionalProperties'=>false,'required'=>['value'],'properties'=>['value'=>['type'=>'string']]],ActionEffect::Read,ActionConfirmationPolicy::None,$handler));$builder=new ScenarioDefinitionFromForm($registry,app(\App\Domain\AI\Actions\Support\ActionSchemaValidator::class),app(SimulationScenarioDefinition::class),app(SimulationPayloadLimits::class));$version=new AgentVersion;$version->setRelation('contractVersion',(object)['allowed_actions'=>['example.lookup_record']]);$input=['turns'=>[['message'=>'Busca el registro','action_key'=>'example.lookup_record','fixture_fields'=>[['property'=>'value','value'=>'Encontrado']],'simulate_confirmation'=>false]],'expected_handoff'=>'no','expected_outcome_type'=>'','response_contains'=>'Encontrado','response_not_contains'=>'error','response_completed'=>true];$definition=$builder->build($input,new Tenant,$version);$this->assertSame(['Busca el registro'],$definition['turns']);$this->assertSame(['value'=>'Encontrado'],$definition['action_results']['example.lookup_record']);$this->assertContains(['type'=>'expected_action_key','value'=>'example.lookup_record'],$definition['assertions']);
        foreach([array_merge($input,['ready'=>true]),array_replace_recursive($input,['turns'=>[['action_key'=>'unknown.action']]]),array_replace_recursive($input,['turns'=>[['fixture_fields'=>[['property'=>'secret','value'=>'x']]]]])]as$bad)try{$builder->build($bad,new Tenant,$version);$this->fail('Tampering must fail.');}catch(\InvalidArgumentException){$this->addToAssertionCount(1);}
    }
    public function test_payload_limits_measure_real_utf8_and_canonical_json_bytes():void
    {
        $limits=app(SimulationPayloadLimits::class);config(['ai.simulator.max_user_message_bytes'=>4,'ai.simulator.max_fixture_bytes'=>20,'ai.simulator.max_transcript_bytes'=>60]);$limits->assertMessage('aaaa');$this->addToAssertionCount(1);try{$limits->assertMessage('aaaaa');$this->fail('ASCII overflow must fail.');}catch(\DomainException$e){$this->assertSame('simulation_message_too_large',$e->getMessage());}try{$limits->assertMessage('ááá');$this->fail('UTF-8 byte overflow must fail.');}catch(\DomainException$e){$this->assertSame(3,mb_strlen('ááá'));$this->assertSame(6,strlen('ááá'));}$this->assertSame(strlen('{"value":"ok"}'),$limits->jsonBytes(['value'=>'ok']));
    }
    public function test_scenario_mutations_and_publish_share_the_agent_lock_barrier():void
    {
        $mutations=file_get_contents(app_path('Domain/AI/Simulator/Services/SimulationScenarioMutationService.php'));$publish=file_get_contents(app_path('Domain/AI/Simulator/Services/PublishReadyAgentVersionService.php'));$this->assertStringContainsString("Agent::query()->lockForUpdate()",$mutations);$this->assertStringContainsString("Agent::query()->lockForUpdate()",$publish);$this->assertStringContainsString('evaluateLocked',$publish);$this->assertStringContainsString('lockCurrentAuthority',$publish);
    }
    public function test_migration_up_down_up_with_foreign_keys(): void
    {
        $this->baseSchema();$migration=require base_path('database/migrations/2026_08_30_100000_create_ai_simulator_readiness.php');$migration->up();
        foreach(['ai_simulation_scenarios','ai_simulation_runs','ai_simulation_case_runs']as$table)$this->assertTrue(Schema::hasTable($table));
        $this->assertSame(1,(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys);$this->assertNotEmpty(DB::select("PRAGMA foreign_key_list('ai_simulation_case_runs')"));
        $migration->down();$this->assertFalse(Schema::hasTable('ai_simulation_runs'));$migration->up();$this->assertTrue(Schema::hasColumn('ai_runtime_runs','execution_mode'));
    }
    private function baseSchema(): void
    {
        Schema::create('users',fn(Blueprint$t)=>$t->id());Schema::create('network_tenants',function(Blueprint$t){$t->id();$t->unique(['id']);});
        Schema::create('ai_agents',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unique(['tenant_id','id']);});
        Schema::create('ai_agent_contract_versions',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unique(['tenant_id','id']);});
        Schema::create('ai_agent_versions',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('agent_id');$t->unsignedBigInteger('agent_contract_version_id');$t->unique(['tenant_id','agent_id','id']);});
        Schema::create('ai_runtime_runs',function(Blueprint$t){$t->id();$t->unsignedBigInteger('tenant_id');$t->string('purpose');$t->timestamps();});
    }
}
