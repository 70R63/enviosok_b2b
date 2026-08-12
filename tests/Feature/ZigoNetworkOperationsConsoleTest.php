<?php
namespace Tests\Feature;

use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\Models\{Tenant,TenantMembership};
use App\Models\{Roles\Roles,User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Hash,Schema};

class ZigoNetworkOperationsConsoleTest extends ZigoNetworkOnboardingOperationsTest
{
 private string $networkHost='https://network.zigo.local';
 protected function setUp():void
 {
  parent::setUp();config(['zigo_surfaces.network.host'=>'network.zigo.local','zigo_surfaces.corporate.host'=>'zigo.local']);
  Schema::table('network_plans',fn(Blueprint$t)=>$t->text('description')->nullable());
  Schema::create('network_plan_modules',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('plan_id'),$t->unsignedBigInteger('module_id'),$t->boolean('is_included'),$t->unsignedInteger('limit_value')->nullable(),$t->timestamps(),$t->unique(['plan_id','module_id'])]);
  Schema::create('network_tenant_memberships',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('role'),$t->string('status'),$t->timestamps(),$t->unique(['tenant_id','user_id'])]);
  Schema::table('network_subscriptions',fn(Blueprint$t)=>[$t->unsignedInteger('operations_limit')->nullable(),$t->timestamp('started_at')->nullable(),$t->timestamp('current_period_start')->nullable(),$t->timestamp('current_period_end')->nullable(),$t->timestamp('trial_ends_at')->nullable(),$t->timestamp('grace_ends_at')->nullable(),$t->timestamp('canceled_at')->nullable(),$t->timestamp('ended_at')->nullable()]);
  Schema::create('network_usage_events',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->nullable(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id'),$t->string('metric'),$t->unsignedInteger('quantity'),$t->string('idempotency_key')->nullable(),$t->timestamp('occurred_at'),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]);
  Schema::create('network_subscription_events',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('actor_user_id')->nullable(),$t->string('event'),$t->string('from_status')->nullable(),$t->string('to_status')->nullable(),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]);
  Schema::create('network_commercial_products',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->string('code')->unique(),$t->string('name'),$t->text('description')->nullable(),$t->string('type'),$t->string('billing_type'),$t->decimal('price',12,2),$t->char('currency',3),$t->unsignedBigInteger('module_id')->nullable(),$t->unsignedBigInteger('plan_id')->nullable(),$t->unsignedInteger('included_operations')->nullable(),$t->boolean('is_active'),$t->unsignedInteger('sort_order')->default(0),$t->json('metadata')->nullable(),$t->timestamps()]);
  (require database_path('migrations/2026_08_20_100000_harden_network_commercial_catalog_and_audit.php'))->up();
  Schema::create('platform_payment_events',fn(Blueprint$t)=>[$t->id(),$t->string('provider'),$t->string('event_key'),$t->unsignedBigInteger('platform_payment_attempt_id')->nullable(),$t->string('provider_payment_id')->nullable(),$t->string('status'),$t->string('error_code')->nullable(),$t->timestamp('received_at'),$t->timestamp('processed_at')->nullable(),$t->timestamps()]);
  Schema::create('tenant_saas_orders',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('commercial_product_id'),$t->unsignedBigInteger('created_by_user_id'),$t->string('purchase_key'),$t->string('status'),$t->string('payment_status'),$t->unsignedInteger('quantity'),$t->decimal('unit_amount',12,2),$t->decimal('subtotal',12,2),$t->decimal('tax_amount',12,2),$t->decimal('total_amount',12,2),$t->char('currency',3),$t->json('purchase_snapshot'),$t->timestamps()]);
  Schema::create('tenant_operation_allowances',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('saas_order_id'),$t->unsignedInteger('operations'),$t->timestamp('starts_at'),$t->timestamp('expires_at'),$t->timestamps()]);
  Schema::create('support_tickets',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id')->nullable(),$t->unsignedBigInteger('requester_user_id'),$t->string('requester_type'),$t->string('scope'),$t->string('channel'),$t->string('category'),$t->string('priority'),$t->string('status'),$t->string('subject'),$t->text('description'),$t->unsignedBigInteger('assigned_user_id')->nullable(),$t->string('assigned_team')->nullable(),$t->unsignedBigInteger('related_operation_id')->nullable(),$t->unsignedBigInteger('related_shipment_id')->nullable(),$t->unsignedBigInteger('related_checkout_id')->nullable(),$t->unsignedBigInteger('related_driver_profile_id')->nullable(),$t->string('public_reference'),$t->timestamp('first_response_at')->nullable(),$t->timestamp('resolved_at')->nullable(),$t->timestamp('closed_at')->nullable(),$t->timestamps()]);
  (require database_path('migrations/2026_08_17_100000_create_tenant_api_hub_v1.php'))->up();
 }
 public function test_catalog_publication_drives_public_pricing_and_used_plan_archives():void
 {
  $admin=$this->admin();$plan=Plan::findOrFail(1);$this->asNetwork($admin)->post($this->networkHost.'/network/catalog',['code'=>'OPS-M','name'=>'Operación','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>'100.00','currency'=>'MXN','plan_id'=>$plan->id,'is_active'=>1,'is_public'=>1])->assertRedirect();
  $product=NetworkCommercialProduct::firstOrFail();$this->get('http://zigo.local/zigo-platform/precios')->assertOk()->assertSee('Operación');
  $tenant=Tenant::create(['name'=>'ACME','slug'=>'acme','status'=>'active','current_plan_id'=>$plan->id]);
  $this->asNetwork($admin)->delete($this->networkHost.'/network/plans/'.$plan->id)->assertRedirect();
  $this->assertSame('inactive',$plan->fresh()->status);$this->assertFalse($product->fresh()->is_public);
  $this->assertDatabaseHas('network_admin_audit_events',['action'=>'commercial_product.created']);$this->assertDatabaseHas('network_admin_audit_events',['action'=>'plan.archived']);
 }
 public function test_tenant_users_search_and_last_owner_protection():void
 {
  $admin=$this->admin();$tenant=Tenant::create(['name'=>'Search Company','slug'=>'search-company','status'=>'active','current_plan_id'=>1]);$owner=User::create(['name'=>'Owner','email'=>'owner@search.test','password'=>Hash::make('x'),'empresa_id'=>8]);$membership=TenantMembership::create(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']);
  $this->asNetwork($admin)->get($this->networkHost.'/network/tenants?q=owner%40search.test')->assertOk()->assertSee('Search Company');
  $this->asNetwork($admin)->get($this->networkHost.'/network/users?tenant_id='.$tenant->id)->assertOk()->assertSee('owner@search.test')->assertDontSee($owner->password);
  $this->asNetwork($admin)->patch($this->networkHost.'/network/tenants/'.$tenant->id.'/users/'.$membership->id,['role'=>'admin','status'=>'active'])->assertSessionHasErrors('role');
  $this->assertSame('owner',$membership->fresh()->role);
 }
 public function test_payment_approved_failed_is_visible_and_security_contract_is_preserved():void
 {
  $admin=$this->admin();$app=SaasOnboardingApplication::create(['status'=>'FAILED','contact_name'=>'A','contact_email'=>'a@b.test','company_name'=>'Broken Co','purchase_key'=>'broken-key','paid_at'=>now(),'failure_code'=>'FAIL']);DB::table('platform_payment_attempts')->insert(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'onboarding_application_id'=>$app->id,'provider'=>'MERCADO_PAGO','status'=>'APPROVED','provider_payment_id'=>'pay-12','external_reference'=>'opaque-very-secret-ref','amount'=>'100.00','currency'=>'MXN','approved_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);$attempt=DB::table('platform_payment_attempts')->first();
  $this->asNetwork($admin)->get($this->networkHost.'/network/payments/'.$attempt->id)->assertOk()->assertSee('PAGO CONFIRMADO — ACTIVACIÓN FALLIDA')->assertDontSee('opaque-very-secret-ref');
  app()->detectEnvironment(fn()=>'staging');config(['zigo_domains.routing_enabled'=>true]);$this->asNetwork($admin)->get('https://wrong.test/network/payments')->assertNotFound();app()->detectEnvironment(fn()=>'testing');
  $owner=User::create(['name'=>'Tenant','email'=>'tenant@test.local','password'=>Hash::make('x'),'empresa_id'=>9]);$this->actingAs($owner)->get($this->networkHost.'/network/payments')->assertForbidden();
 }
 public function test_api_hub_renders_zero_one_and_multiple_error_counts_without_shadowing_error_bag():void
 {
  $admin=$this->admin();$url=$this->networkHost.'/network/api-hub';
  $this->asNetwork($admin)->get($url)->assertOk()->assertSee('Errores')->assertSee('0');
  $tenant=Tenant::create(['name'=>'API Tenant','slug'=>'api-tenant','status'=>'active','current_plan_id'=>1]);$client=DB::table('tenant_api_clients')->insertGetId(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$tenant->id,'name'=>'Test','environment'=>'STAGE','status'=>'ACTIVE','created_by_user_id'=>$admin->id,'created_at'=>now(),'updated_at'=>now()]);$key=DB::table('tenant_api_keys')->insertGetId(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$tenant->id,'api_client_id'=>$client,'key_prefix'=>'zg_test','key_hash'=>hash('sha256','test-key'),'last_four'=>'test','name'=>'Test','scopes'=>'[]','created_at'=>now(),'updated_at'=>now()]);
  foreach([500,422] as$index=>$status)DB::table('tenant_api_usage_events')->insert(['tenant_id'=>$tenant->id,'api_client_id'=>$client,'api_key_id'=>$key,'request_id'=>(string)\Illuminate\Support\Str::uuid(),'endpoint_code'=>'test-'.$index,'http_status'=>$status,'units'=>1,'duration_ms'=>1,'occurred_at'=>now(),'created_at'=>now()]);
  $this->asNetwork($admin)->get($url)->assertOk()->assertSee('2');
 }
 public function test_pre_stage_network_navigation_smoke_has_no_dead_or_duplicate_links():void
 {
  $admin=$this->admin();foreach(['/network','/network/dashboard','/network/tenants','/network/users','/network/plans','/network/modules','/network/commercial-products','/network/subscriptions','/network/payments','/network/onboarding','/network/api-hub','/network/support','/network/audit']as$path)$this->asNetwork($admin)->get($this->networkHost.$path)->assertOk()->assertDontSee('href="#"',false);
  $tenant=Tenant::create(['name'=>'Tenant 360','slug'=>'tenant-360','status'=>'inactive','current_plan_id'=>1]);$this->asNetwork($admin)->get($this->networkHost.'/network/tenants/'.$tenant->id)->assertOk()->assertSee('Resumen')->assertSee('Facturación')->assertSee('Zona de peligro')->assertDontSee('href="#"',false);
  $layout=file_get_contents(resource_path('views/network/layout.blade.php'));$this->assertSame(0,substr_count($layout,'>Launchpad</a>'));$this->assertSame(0,substr_count($layout,'>Mapa Network</a>'));$this->assertSame(1,substr_count($layout,'>Dashboard</a>'));$this->assertSame(1,substr_count($layout,'>Onboarding</a>'));$this->assertStringNotContainsString('>Consumo</a>',$layout);$dashboard=file_get_contents(resource_path('views/network/dashboard.blade.php'));$this->assertSame(1,substr_count($dashboard,'>Launchpad</a>'));$this->assertSame(1,substr_count($dashboard,'>Mapa Network</a>'));
  app()->detectEnvironment(fn()=>'staging');config(['zigo_domains.routing_enabled'=>true]);$this->asNetwork($admin)->get('https://wrong.test/network/api-hub')->assertNotFound();app()->detectEnvironment(fn()=>'testing');
 }
 private function admin():User{$u=User::create(['name'=>'Network','email'=>uniqid().'@network.test','password'=>Hash::make('x'),'empresa_id'=>1]);$r=Roles::firstOrCreate(['slug'=>'sysadmin'],['name'=>'sysadmin']);$u->roles()->attach($r);return$u;}
 private function asNetwork(User$u):self{return$this->actingAs($u)->withSession(['network.2fa_user_id'=>$u->id,'network.2fa_verified_at'=>now()->timestamp]);}
}
