<?php
namespace Tests\Feature;

use App\Domain\Network\Map\NetworkMapRegistry;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NetworkControlCenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') $this->markTestSkipped('Requires SQLite :memory:.');
        foreach (['network_plan_modules','network_plans','network_modules','network_tenants','users_roles','roles','users'] as $table) Schema::dropIfExists($table);
        Schema::create('users',fn(Blueprint $t)=>$this->users($t));
        Schema::create('roles',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug');$t->timestamps();});
        Schema::create('users_roles',function(Blueprint $t){$t->unsignedBigInteger('user_id');$t->unsignedBigInteger('roles_id');});
        Schema::create('network_tenants',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps();});
        Schema::create('network_modules',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
        Schema::create('network_plans',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
        Schema::create('network_plan_modules',function(Blueprint $t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();});
    }
    public function test_three_views_are_protected_and_available_to_sysadmin(): void
    {
        $this->get('/network')->assertRedirect('/network/login');
        $this->actingAs($this->user('admin'))->get('/network/topology')->assertForbidden();
        $this->actingAs($this->user('sysadmin'));
        $this->get('/network')->assertOk()->assertSee('Control Center')->assertSee('ZIGO AI');
        $this->get('/network/topology')->assertOk()->assertSee('Network Topology')->assertSee('Dimension AI');
        $this->get('/network/dashboard')->assertOk()->assertSee('ESTADO DEL ECOSISTEMA')->assertSee('N/A');
    }
    public function test_registry_integrity_routes_counts_and_ai(): void
    {
        $registry=app(NetworkMapRegistry::class); $nodes=$registry->nodes(); $codes=array_column($nodes,'code');
        $this->assertCount(count(array_unique($codes)),$codes);
        foreach($nodes as $node){$this->assertArrayHasKey($node['implementation_status'],$registry->statuses());if($node['route_name']){$this->assertTrue(Route::has($node['route_name']));$this->assertNotNull($registry->url($node));}}
        foreach($registry->connections() as $edge){$this->assertContains($edge['from'],$codes);$this->assertContains($edge['to'],$codes);$this->assertContains($edge['status'],['current','future','experimental']);}
        foreach(['ZIGO_AI','AI_CONVERSATIONAL','AI_VISION','WHATSAPP','WEBCHAT','BOT_BUILDER','HUMAN_HANDOFF','ADDRESS_OCR','DOCUMENT_AI'] as $code)$this->assertSame('planned',$registry->node($code)['implementation_status']);
        $this->assertSame('experimental',$registry->node('DIMENSION_AI')['implementation_status']);
        $this->assertSame(count($nodes),array_sum($registry->counts()));
        $planned=$registry->node('ZIGO_LOCAL');$this->assertNull($registry->url($planned));
    }
    public function test_existing_network_catalog_routes_remain_available(): void
    {
        $this->actingAs($this->user('sysadmin'));
        foreach(['/network/tenants','/network/plans','/network/modules'] as $uri)$this->get($uri)->assertOk();
    }
    public function test_support_navigation_uses_configured_portal_host_when_host_routing_is_required(): void
    {
        $oldRouting=config('zigo_domains.routing_enabled');$oldPortal=config('zigo_domains.portals.support');
        config(['zigo_domains.routing_enabled'=>true,'zigo_domains.portals.support'=>['url'=>'https://support.test','host'=>'support.test']]);
        $url=app(NetworkMapRegistry::class)->url(app(NetworkMapRegistry::class)->node('SUPPORT'));
        $this->assertSame('https://support.test/soporte/dashboard',$url);
        config(['zigo_domains.portals.support'=>['url'=>null,'host'=>null]]);
        $this->assertNull(app(NetworkMapRegistry::class)->url(app(NetworkMapRegistry::class)->node('SUPPORT')));
        config(['zigo_domains.routing_enabled'=>$oldRouting,'zigo_domains.portals.support'=>$oldPortal]);
    }
    private function user(string $slug): User {$u=User::forceCreate(['name'=>'Test','email'=>uniqid()."@test.local",'password'=>'secret','empresa_id'=>1]);$r=Roles::create(['name'=>$slug,'slug'=>$slug]);$u->roles()->attach($r->id);return $u;}
    private function users(Blueprint $t): void {$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();}
}
