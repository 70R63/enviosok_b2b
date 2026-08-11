<?php
namespace Tests\Feature;
use Illuminate\Http\Request; use Tests\TestCase;
final class ZigoStageHostContractTest extends TestCase
{
 protected function setUp():void{parent::setUp();$this->app['env']='stage';config()->set('zigo_domains.routing_enabled',true);config()->set('zigo_payments.providers.mercado_pago.webhook_secret','stage-secret-placeholder');}
 protected function tearDown():void{$this->app['env']='testing';parent::tearDown();}
 public function test_payment_edge_is_host_bound_post_only_and_secure():void{
  $payments=config('zigo_surfaces.payments.host');$this->get('https://'.$payments.'/payments/health')->assertOk()->assertJson(['status'=>'ok'])->assertHeader('Cache-Control','no-store, private')->assertHeader('X-Frame-Options','DENY');
  $this->get('https://rapidgo-stage.zigo-envios.com/payments/health')->assertNotFound();
  $this->get('https://'.$payments.'/api/payments/mercado-pago/webhook')->assertStatus(405);
  $this->postJson('https://rapidgo-stage.zigo-envios.com/api/payments/mercado-pago/webhook')->assertNotFound();
  $this->postJson('https://'.$payments.'/api/payments/mercado-pago/webhook',['data'=>['id'=>'1']])->assertUnauthorized();
 }
 public function test_callback_is_central_and_does_not_require_tenant_cookie():void{
  $route=collect(app('router')->getRoutes()->getRoutes())->first(fn($r)=>$r->getName()==='payments.mercado-pago.oauth.callback');
  $this->assertSame(config('zigo_surfaces.payments.host'),$route->getDomain());$this->assertNotContains('auth',$route->gatherMiddleware());
  $this->get('https://'.config('zigo_surfaces.payments.host').'/payments/mercado-pago/oauth/callback?state=invalid&code=x')->assertForbidden();
  $this->get('https://rapidgo-stage.zigo-envios.com/payments/mercado-pago/oauth/callback?state=invalid&code=x')->assertNotFound();
 }
 public function test_driver_and_network_surfaces_do_not_leak_across_hosts():void{
  $this->get('https://'.config('zigo_driver.host').'/driver/manifest.webmanifest')->assertOk()->assertJsonPath('scope','/driver/')->assertJsonPath('start_url','/driver/');
  $this->get('https://network-stage.zigo-envios.com/driver/manifest.webmanifest')->assertNotFound();
  $this->get('https://'.config('zigo_surfaces.network.host').'/network/login')->assertOk();
  $this->get('https://stage.zigo-envios.com/network/login')->assertNotFound();
  $this->get('https://rapidgo-stage.zigo-envios.com/network/login')->assertNotFound();
  $this->get('https://driver-stage.zigo-envios.com/network/login')->assertNotFound();
 }
 public function test_isolated_surface_cookie_names_remain_host_only():void{
  foreach([config('zigo_driver.host')=>'_driver',config('zigo_surfaces.network.host')=>'_network',config('zigo_surfaces.payments.host')=>'_payments'] as $host=>$suffix){config(['session.cookie'=>'zigo_stage_session','session.domain'=>'.zigo-envios.com']);$request=Request::create('https://'.$host.'/');app(\App\Http\Middleware\UseZigoPortalSessionCookie::class)->handle($request,fn()=>response('ok'));$this->assertSame('zigo_stage_session'.$suffix,config('session.cookie'));$this->assertNull(config('session.domain'));}
 }
}
