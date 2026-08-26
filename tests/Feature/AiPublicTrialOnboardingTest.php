<?php
namespace Tests\Feature;
use App\Domain\AI\Usage\AiCapacityService;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Catalog\Models\{Module,Plan};
use App\Domain\Network\Commerce\Models\{NetworkCommercialProduct,PlatformPaymentAttempt,TenantSaasOrder};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Http,Schema};
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AiPublicTrialOnboardingTest extends TestCase
{
 private NetworkCommercialProduct $trial;
 protected function setUp():void
 {
  parent::setUp();
  if(DB::connection()->getDriverName()!=='sqlite'||DB::connection()->getDatabaseName()!==':memory:')$this->markTestSkipped('Requires isolated SQLite :memory:.');
  Http::preventStrayRequests(); $this->schema();
  config(['ai.enabled'=>true,'zigo_surfaces.corporate.host'=>'zigo.local','zigo_onboarding.subdomain_base'=>'zigo.local','zigo_onboarding.tenant_subdomain_suffix'=>'','zigo_onboarding.tenant_domain_environment'=>'sandbox','zigo_onboarding.managed_subdomains_are_verified'=>true,'zigo_onboarding.tax_rate'=>'0.00']);
  $m=Module::create(['code'=>'AI_CORE','name'=>'AI Core','description'=>'AI','type'=>'ai','is_active'=>true,'sort_order'=>1]);
  $p=Plan::create(['code'=>'AI_TRIAL','name'=>'Agentes IA Trial','status'=>'active','monthly_price'=>0,'annual_price'=>0,'currency'=>'MXN']); $p->modules()->attach($m->id,['is_included'=>true,'limit_value'=>null]);
  $this->trial=NetworkCommercialProduct::create(['uuid'=>'11111111-1111-4111-8111-111111111111','code'=>'AI_TRIAL','name'=>'Agentes IA Trial','description'=>'Prueba','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>0,'currency'=>'MXN','module_id'=>$m->id,'plan_id'=>$p->id,'is_active'=>true,'is_public'=>true,'metadata'=>['ai_product'=>true,'ai_kind'=>'base','trial'=>true,'ai_plan_code'=>'AI_TRIAL']]);
 }
 public function test_landing_http_exposes_trial_cta():void{$this->withHeader('Host','zigo.local')->get('/agentes-ia')->assertOk()->assertSee('Probar gratis 7 días')->assertSee('/agentes-ia/comenzar?offer='.$this->trial->uuid);}
 public function test_start_http_selects_trial_offer():void{$this->withHeader('Host','zigo.local')->get('/agentes-ia/comenzar?offer='.$this->trial->uuid)->assertOk()->assertSee('Agentes IA Trial')->assertSee($this->trial->uuid);}
 public function test_public_trial_post_provisions_complete_ai_only_tenant_without_payment():void
 {
  $this->withHeader('Host','zigo.local')->get('/agentes-ia/comenzar?offer='.$this->trial->uuid)->assertOk();
  $r=$this->withHeader('Host','zigo.local')->post('/agentes-ia/comenzar',['offer'=>$this->trial->uuid,'contact_name'=>'Trial','contact_last_name'=>'Owner','contact_email'=>'trial-owner@example.test','contact_phone'=>'5551234567','company_name'=>'Trial Company','company_legal_name'=>'Trial Company SA de CV','tax_id'=>'TRIAL010101AA1','requested_subdomain'=>'trialcompany']); $r->assertRedirect();
  $a=SaasOnboardingApplication::where('contact_email','trial-owner@example.test')->firstOrFail(); $this->assertSame('ACTIVE',$a->status); $this->assertNull($a->paid_at); $this->assertNotNull($a->tenant_id); $this->assertNotNull($a->owner_user_id); $this->assertNotNull($a->legacy_empresa_id); $this->assertSame(0,PlatformPaymentAttempt::where('onboarding_application_id',$a->id)->count()); $this->assertSame(0,TenantSaasOrder::where('payment_status','APPROVED')->count());
  $t=Tenant::findOrFail($a->tenant_id); $this->assertSame('active',$t->status); $this->assertSame('trialcompany',$t->slug); $this->assertDatabaseHas('network_tenant_memberships',['tenant_id'=>$t->id,'user_id'=>$a->owner_user_id,'role'=>'owner','status'=>'active']); $this->assertDatabaseHas('network_tenant_domains',['tenant_id'=>$t->id,'is_primary'=>1,'status'=>'verified']);
  $s=Subscription::where('tenant_id',$t->id)->firstOrFail(); $this->assertSame('trialing',$s->status); $this->assertSame('TRIAL',$s->billing_frequency); $this->assertSame(Plan::where('code','AI_TRIAL')->value('id'),$s->plan_id); $this->assertDatabaseHas('network_entitlements',['subscription_id'=>$s->id,'tenant_id'=>$t->id,'code'=>'AI_CORE','is_enabled'=>1]); $c=app(AiCapacityService::class); $this->assertSame(1,$c->limit($t,'MAX_AGENTS')); $this->assertSame(1,$c->limit($t,'MAX_WEBCHAT_CHANNELS')); $this->assertSame(0,$c->limit($t,'MAX_WHATSAPP_CHANNELS')); $this->assertSame(50,$c->limit($t,'MONTHLY_CONVERSATIONS')); $events=$a->events()->pluck('event')->all(); foreach(['AI_PRODUCT_SELECTED','AI_TRIAL_READY','PROVISIONING_STARTED','PROVISIONING_COMPLETED'] as $event) $this->assertContains($event,$events); $this->assertDatabaseMissing('saas_onboarding_events',['onboarding_application_id'=>$a->id,'event'=>'AI_READY_FOR_CHECKOUT']);
 }
 public function test_trial_post_replay_does_not_duplicate_provisioned_resources(): void
 {
  $this->postTrial('replay@example.test','replay-company');
  $before=array_map(fn($t)=>(int)DB::table($t)->count(),['saas_onboarding_applications','network_tenants','users','empresas','network_tenant_memberships','network_tenant_domains','network_subscriptions','network_entitlements']);
  $this->postTrial('replay@example.test','replay-company');
  $after=array_map(fn($t)=>(int)DB::table($t)->count(),['saas_onboarding_applications','network_tenants','users','empresas','network_tenant_memberships','network_tenant_domains','network_subscriptions','network_entitlements']);
  $this->assertSame($before,$after); $this->assertSame(0,PlatformPaymentAttempt::count());
 }
 public function test_owner_existing_without_trial_history_is_reused(): void
 {
  $empresa=DB::table('empresas')->insertGetId(['estatus'=>1,'contacto'=>'Existing','nombre'=>'Existing','email'=>'existing@example.test','telefono'=>'5551234567','created_at'=>now(),'updated_at'=>now()]);
  $user=User::create(['name'=>'Existing','apellido_paterno'=>'Owner','email'=>'existing@example.test','password'=>password_hash('secret',PASSWORD_BCRYPT),'empresa_id'=>$empresa]);
  $this->postTrial('existing@example.test','existing-company');
  $this->assertSame($user->id,SaasOnboardingApplication::where('contact_email','existing@example.test')->value('owner_user_id')); $this->assertSame(1,User::where('email','existing@example.test')->count());
 }
 public function test_same_email_cannot_receive_second_ai_trial_on_another_tenant(): void
 {
  $this->postTrial('trial-repeat@example.test','trial-first');
  $first=SaasOnboardingApplication::where('contact_email','trial-repeat@example.test')->firstOrFail();
  Subscription::where('tenant_id',$first->tenant_id)->update(['status'=>'suspended','trial_ends_at'=>now()->subMinute()]);
  $this->withSession(['ai_onboarding_purchase_key'=>(string)\Illuminate\Support\Str::uuid()])->withHeader('Host','zigo.local')->post('/agentes-ia/comenzar',['offer'=>$this->trial->uuid,'contact_name'=>'Repeat','contact_last_name'=>'Owner','contact_email'=>'trial-repeat@example.test','contact_phone'=>'5551234567','company_name'=>'Second','company_legal_name'=>'Second SA','tax_id'=>'SECOND010101AA1','requested_subdomain'=>'trial-second'])->assertSessionHasErrors();
  $this->assertSame(1,Subscription::where('billing_frequency','TRIAL')->count());
 }
 public function test_expired_trial_is_suspended_without_deleting_tenant_data(): void
 {
  $this->postTrial('expiry@example.test','expiry-company'); $a=SaasOnboardingApplication::where('contact_email','expiry@example.test')->firstOrFail(); $s=Subscription::where('tenant_id',$a->tenant_id)->firstOrFail();
  Carbon::setTestNow($s->trial_ends_at->copy()->addMinute()); $this->artisan('ai:expire-trials')->assertSuccessful(); Carbon::setTestNow();
  $this->assertSame('suspended',$s->fresh()->status); $this->assertDatabaseHas('network_tenants',['id'=>$a->tenant_id]); $this->assertDatabaseHas('users',['id'=>$a->owner_user_id]); $this->assertDatabaseHas('network_tenant_domains',['tenant_id'=>$a->tenant_id]); $this->assertDatabaseHas('network_entitlements',['tenant_id'=>$a->tenant_id]);
 }
 public function test_paid_ai_onboarding_remains_pending_payment_and_is_not_provisioned_as_trial(): void
 {
  $paid=NetworkCommercialProduct::create(['uuid'=>'22222222-2222-4222-8222-222222222222','code'=>'AI_INITIAL','name'=>'Inicial','description'=>'Paid','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>599,'currency'=>'MXN','is_active'=>true,'is_public'=>true,'metadata'=>['ai_product'=>true,'ai_kind'=>'base','trial'=>false,'ai_plan_code'=>'AI_INITIAL']]);
  $this->withHeader('Host','zigo.local')->post('/agentes-ia/comenzar',['offer'=>$paid->uuid,'contact_name'=>'Paid','contact_last_name'=>'Owner','contact_email'=>'paid@example.test','contact_phone'=>'5551234567','company_name'=>'Paid Co','company_legal_name'=>'Paid Co SA','tax_id'=>'PAID010101AA1','requested_subdomain'=>'paid-company'])->assertRedirect();
  $a=SaasOnboardingApplication::where('contact_email','paid@example.test')->firstOrFail(); $this->assertSame('PENDING_PAYMENT',$a->status); $this->assertNull($a->paid_at); $this->assertNull($a->tenant_id); $this->assertSame(0,Subscription::count()); $this->assertSame(0,PlatformPaymentAttempt::count()); $this->assertDatabaseHas('saas_onboarding_events',['onboarding_application_id'=>$a->id,'event'=>'AI_READY_FOR_CHECKOUT']); $this->assertDatabaseMissing('saas_onboarding_events',['onboarding_application_id'=>$a->id,'event'=>'AI_TRIAL_READY']);
 }
 private function postTrial(string $email,string $subdomain): void
 {
  $this->withHeader('Host','zigo.local')->post('/agentes-ia/comenzar',['offer'=>$this->trial->uuid,'contact_name'=>'Trial','contact_last_name'=>'Owner','contact_email'=>$email,'contact_phone'=>'5551234567','company_name'=>'Trial Company','company_legal_name'=>'Trial Company SA de CV','tax_id'=>'TRIAL010101AA1','requested_subdomain'=>$subdomain])->assertRedirect();
 }
 private function schema():void
 {
  foreach(['platform_payment_events','platform_payment_attempts','tenant_saas_orders','saas_onboarding_events','saas_onboarding_applications','network_entitlement_capacities','network_subscription_events','network_entitlements','network_subscriptions','network_tenant_memberships','network_tenant_brandings','network_tenant_domains','network_commercial_products','network_plan_modules','network_tenants','network_plans','network_modules','users','empresas'] as $x)Schema::dropIfExists($x);
  Schema::create('empresas',function(Blueprint $t){$t->id();$t->timestamps();$t->boolean('estatus')->default(1);$t->string('contacto',50);$t->string('nombre',50);$t->string('email')->nullable()->unique();$t->string('telefono',10);});
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('apellido_paterno')->nullable();$t->string('apellido_materno')->nullable();$t->string('rfc')->nullable();$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps();});
  Schema::create('network_modules',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
  Schema::create('network_plans',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
  Schema::create('network_tenants',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps();});
  Schema::create('network_plan_modules',function(Blueprint $t){$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();$t->unique(['plan_id','module_id']);});
  Schema::create('network_tenant_domains',function(Blueprint $t){$t->id();$t->unsignedBigInteger('tenant_id');$t->string('domain')->unique();$t->string('type');$t->string('environment');$t->boolean('is_primary');$t->string('status');$t->timestamp('verified_at')->nullable();$t->timestamps();});
  Schema::create('network_tenant_brandings',function(Blueprint $t){$t->id();$t->unsignedBigInteger('tenant_id')->unique();$t->string('brand_name')->nullable();$t->string('logo_path')->nullable();$t->string('primary_color',7)->nullable();$t->string('secondary_color',7)->nullable();$t->string('accent_color',7)->nullable();$t->string('favicon_path')->nullable();$t->string('support_email')->nullable();$t->string('support_phone')->nullable();$t->timestamps();});
  Schema::create('network_tenant_memberships',function(Blueprint $t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps();$t->unique(['tenant_id','user_id']);});
  Schema::create('network_subscriptions',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');$t->unsignedInteger('operations_limit')->nullable();foreach(['started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at']as$c)$t->timestamp($c)->nullable();$t->string('billing_frequency',12)->nullable();$t->timestamps();});
  Schema::create('network_entitlements',function(Blueprint $t){$t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('module_id');$t->string('code');$t->boolean('is_enabled');$t->unsignedInteger('limit_value')->nullable();$t->string('source');$t->timestamps();$t->unique(['subscription_id','module_id']);$t->unique(['subscription_id','code']);});
  Schema::create('network_entitlement_capacities',function(Blueprint $t){$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('entitlement_id');$t->string('capability_code');$t->unsignedInteger('quantity');$t->string('source');$t->string('source_key');$t->boolean('is_enabled')->default(true);$t->timestamps();$t->unique(['entitlement_id','capability_code','source','source_key']);});
  Schema::create('network_subscription_events',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('actor_user_id')->nullable(),$t->string('event'),$t->string('from_status')->nullable(),$t->string('to_status')->nullable(),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]);
  Schema::create('network_commercial_products',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->string('billing_type');$t->decimal('price',12,2);$t->char('currency',3);$t->unsignedBigInteger('module_id')->nullable();$t->unsignedBigInteger('plan_id')->nullable();$t->unsignedInteger('included_operations')->nullable();$t->boolean('is_active');$t->boolean('is_public')->default(true);$t->unsignedInteger('sort_order')->default(0);$t->json('metadata')->nullable();$t->timestamps();});
  (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up(); (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up(); Schema::table('saas_onboarding_applications',fn(Blueprint $t)=>[$t->unsignedBigInteger('legacy_empresa_id')->nullable(),$t->boolean('owner_is_new')->nullable()]);
  Schema::create('tenant_saas_orders',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('onboarding_application_id')->nullable()->unique();$t->unsignedBigInteger('commercial_product_id');$t->unsignedBigInteger('created_by_user_id');$t->string('purchase_key');$t->string('status');$t->string('payment_status');$t->unsignedInteger('quantity');$t->decimal('unit_amount',12,2);$t->decimal('subtotal',12,2);$t->decimal('tax_amount',12,2);$t->decimal('total_amount',12,2);$t->char('currency',3);$t->json('purchase_snapshot');$t->string('payment_provider')->nullable();$t->string('payment_reference')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('paid_at')->nullable();$t->timestamp('activated_at')->nullable();$t->timestamps();});
  Schema::create('platform_payment_attempts',fn(Blueprint $t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id')->nullable(),$t->unsignedBigInteger('saas_order_id')->nullable(),$t->unsignedBigInteger('onboarding_application_id')->nullable(),$t->string('provider'),$t->string('status'),$t->string('provider_preference_id')->nullable(),$t->string('provider_payment_id')->nullable(),$t->string('external_reference')->unique(),$t->decimal('amount',12,2),$t->char('currency',3),$t->text('init_point')->nullable(),$t->timestamp('approved_at')->nullable(),$t->timestamp('rejected_at')->nullable(),$t->timestamps(),$t->unique(['provider','provider_payment_id'])]);
  Schema::create('platform_payment_events',fn(Blueprint $t)=>[$t->id(),$t->string('provider'),$t->string('event_key'),$t->unsignedBigInteger('platform_payment_attempt_id')->nullable(),$t->string('provider_payment_id')->nullable(),$t->string('status'),$t->timestamp('received_at'),$t->timestamp('processed_at')->nullable(),$t->timestamps(),$t->unique(['provider','event_key'])]);
 }
}
