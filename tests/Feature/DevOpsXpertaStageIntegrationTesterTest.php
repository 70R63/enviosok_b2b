<?php

namespace Tests\Feature;

use App\Http\Controllers\DevOps\XpertaIntegrationController;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Services\DevOps\XpertaStageIntegrationTester;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DevOpsXpertaStageIntegrationTesterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>false],
            'services.xperta.environment'=>'production','services.xperta.base_url'=>'https://prd-xperta.test','services.xperta.corporativo'=>'prd-corp','services.xperta.email'=>'prd@example.test','services.xperta.password'=>'prd-password','services.xperta.api_key'=>'prd-key',
            'zigo_devops_integrations.xperta_stage'=>['enabled'=>true,'base_url'=>'https://stage-xperta.test','corporativo'=>'corp-stage','ltd'=>'estafeta','email'=>'stage-secret@example.test','password'=>'stage-secret-password','api_key'=>'stage-secret-key','token_minutes'=>60,'connect_timeout'=>1,'timeout'=>2,'token_path'=>'/api/v1/{corporativo}/login','frequency_path'=>'/api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origin}/{destination}','quote_path'=>'/api/v1/empresas/{corporativo}/ltds/{ltd}/servicios/{service}/cotizaciones','services'=>['terrestre','diasig']],
            'services.shipping.unified_quote_enabled'=>false,'services.shipping.prd_quote_probe_enabled'=>false,
        ]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('zigo_provider_api_events',function(Blueprint $t){$t->id();$t->string('provider');$t->string('operation');$t->string('environment');$t->string('correlation_id');$t->string('status');$t->integer('http_status')->nullable();$t->string('provider_code')->nullable();$t->integer('duration_ms');$t->integer('retry_count');$t->integer('requested_by_user_id')->nullable();$t->json('metadata')->nullable();$t->timestamps();});
        Schema::create('b2c_cotizaciones',fn(Blueprint $t)=>$t->id()); Schema::create('pagos',fn(Blueprint $t)=>$t->id()); Schema::create('guias',fn(Blueprint $t)=>$t->id()); Cache::flush();
    }

    public function test_stage_token_executes_once_and_sanitizes_event():void
    {
        $prdCache='xperta:token:'.hash('sha256',implode('|',[config('services.xperta.base_url'),config('services.xperta.corporativo'),config('services.xperta.email')]));
        Cache::put($prdCache,'prd-cached-token',60);
        Http::fake(['*'=>Http::response(['success'=>true,'message'=>['token'=>'raw-secret-token','expires_at'=>now()->addHour()->toIso8601String()]],200)]);
        $result=app(XpertaStageIntegrationTester::class)->token();
        $this->assertTrue($result['token_received']); $this->assertSame(strlen('raw-secret-token'),$result['token_length']); Http::assertSentCount(1);
        $serialized=json_encode([$result,DB::table('zigo_provider_api_events')->get()]); foreach(['raw-secret-token','stage-secret@example.test','stage-secret-password','stage-secret-key','prd@example.test','prd-password','prd-key'] as $secret)$this->assertStringNotContainsString($secret,$serialized);
        Http::assertSent(fn($r)=>str_contains($r->url(),'stage-xperta.test')&&!str_contains($r->url(),'prd-xperta.test')&&$r->hasHeader('x-api-key','stage-secret-key'));
        $this->assertSame('production',config('services.xperta.environment')); $this->assertSame('https://prd-xperta.test',config('services.xperta.base_url')); $this->assertSame('prd-cached-token',Cache::get($prdCache));
    }

    public function test_stage_frequency_uses_valid_contract_once():void
    {
        $this->cacheToken(); Http::fake(['*'=>Http::response(['success'=>true,'data'=>['coverage'=>true,'services'=>['terrestre'],'zone'=>'A','periodicity'=>'daily','restrictions'=>[]]],200)]);
        $result=app(XpertaStageIntegrationTester::class)->frequency('64000','64000');
        $this->assertTrue($result['coverage']); Http::assertSentCount(1); Http::assertSent(fn($r)=>$r->method()==='POST'&&str_ends_with($r->url(),'/frecuencia/64000/64000')&&$r->data()===['token'=>'cached-token']&&$r->hasHeader('x-api-key','stage-secret-key'));
    }

    public function test_stage_quote_is_non_commercial_and_exact():void
    {
        $this->cacheToken(); Http::fake(['*'=>Http::response(['success'=>true,'data'=>[['costo'=>100,'costo_ae'=>10,'sub_total'=>110,'total'=>127.6,'moneda'=>'MXN']]],200,['X-Correlation-ID'=>'safe-correlation'])]);
        $before=[DB::table('b2c_cotizaciones')->count(),DB::table('pagos')->count(),DB::table('guias')->count()];
        $result=app(XpertaStageIntegrationTester::class)->quote(['origin'=>'64000','destination'=>'64000','weight'=>1,'length'=>20,'width'=>20,'height'=>20,'service'=>'terrestre','declared_value'=>0]);
        $this->assertSame(127.6,$result['total']); $this->assertSame('safe-correlation',$result['correlation_id']); Http::assertSentCount(1);
        $this->assertSame($before,[DB::table('b2c_cotizaciones')->count(),DB::table('pagos')->count(),DB::table('guias')->count()]);
        $this->assertFalse(config('services.shipping.unified_quote_enabled')); $this->assertFalse(config('services.shipping.prd_quote_probe_enabled'));
    }

    public function test_401_and_403_are_sanitized():void
    {
        Http::fakeSequence()->push(['message'=>'Unauthorized','password'=>'leak'],401)->push(['message'=>'Corporativo no Autorizado','password'=>'leak'],403);
        foreach(['XPERTA_HTTP_401','XPERTA_CORPORATE_UNAUTHORIZED'] as $code){$result=app(XpertaStageIntegrationTester::class)->token();$this->assertSame($code,$result['code']);$this->assertStringNotContainsString('leak',json_encode($result));}
    }

    public function test_production_is_blocked_without_http():void
    {
        config(['zigo_devops_integrations.xperta_stage.base_url'=>'https://prd-xperta.test']); Http::fake();
        try { app(XpertaStageIntegrationTester::class)->token(); $this->fail('PRD debió bloquearse.'); }
        catch (RuntimeException $e) { $this->assertSame('XPERTA_PRODUCTION_BLOCKED',$e->getMessage()); }
        Http::assertNothingSent();
    }

    /** @dataProvider readOnlyRoles */
    public function test_admin_and_support_cannot_execute(string $role):void
    {
        $this->authenticateAs($role); $this->expectException(HttpException::class);
        app(XpertaIntegrationController::class)->token(app(XpertaStageIntegrationTester::class));
    }

    public static function readOnlyRoles():array { return [['admin'],['soporte']]; }

    public function test_sysadmin_frequency_rejects_invalid_postal_code():void
    {
        $this->authenticateAs('sysadmin'); $request=Request::create('/integrations/xperta-estafeta/frequency','POST',['origin'=>'6400','destination'=>'ABC']);
        $this->expectException(ValidationException::class);
        app(XpertaIntegrationController::class)->frequency($request,app(XpertaStageIntegrationTester::class));
    }

    public function test_routes_have_independent_throttles_and_web_csrf_layer():void
    {
        foreach(['token','frequency','quote'] as $operation){$route=app('router')->getRoutes()->getByName('devops.integrations.xperta.'.$operation);$this->assertNotNull($route);$this->assertContains('throttle:devops-xperta-'.$operation,$route->gatherMiddleware());$this->assertContains('web',$route->gatherMiddleware());}
    }

    private function cacheToken():void
    {
        Cache::put(\App\Services\DevOps\XpertaStageClient::TOKEN_CACHE_KEY,'cached-token',60);
    }

    private function authenticateAs(string $slug):void
    {
        $role=new Roles(); $role->slug=$slug; $user=new User(); $user->id=10; $user->setRelation('roles',collect([$role])); auth()->setUser($user);
    }
}
