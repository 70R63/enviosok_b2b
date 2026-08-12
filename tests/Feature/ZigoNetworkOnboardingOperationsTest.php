<?php

namespace Tests\Feature;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Tests\TestCase;

class ZigoNetworkOnboardingOperationsTest extends TestCase
{
    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
        $this->host = 'https://'.config('zigo_surfaces.network.host');
    }

    public function test_network_sysadmin_with_two_factor_lists_filters_and_views_safe_detail(): void
    {
        $admin = $this->user('sysadmin');
        $failed = $this->application(SaasOnboardingApplication::FAILED, 'FAILED_CODE');
        DB::table('platform_payment_attempts')->insert([
            'uuid'=>(string)\Illuminate\Support\Str::uuid(),'onboarding_application_id'=>$failed->id,
            'provider'=>'MERCADO_PAGO','status'=>'APPROVED','provider_payment_id'=>'payment-1',
            'external_reference'=>'opaque-reference','amount'=>'100.00','currency'=>'MXN','approved_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->networkAs($admin)->get($this->host.'/network/onboarding?status=FAILED')
            ->assertOk()->assertSee('Company')->assertSee('APPROVED')->assertSee('FAILED_CODE');
        $this->networkAs($admin)->get($this->host.'/network/onboarding/'.$failed->uuid)
            ->assertOk()->assertSee('PAGO CONFIRMADO — PROVISIONING FALLIDO')
            ->assertDontSee('APP_KEY')->assertDontSee('webhook-secret')->assertDontSee('password');
    }

    public function test_host_auth_and_two_factor_contract_are_preserved(): void
    {
        $admin = $this->user('sysadmin');
        config(['zigo_domains.routing_enabled' => true]);
        app()->detectEnvironment(fn () => 'staging');
        $this->networkAs($admin)->get('https://wrong.example/network/onboarding')->assertNotFound();
        app()->detectEnvironment(fn () => 'testing');
        $this->actingAs($admin)->withSession(['network.2fa_user_id'=>null,'network.2fa_verified_at'=>null])
            ->get($this->host.'/network/onboarding')->assertRedirect($this->host.'/network/login');
        $nonNetwork = $this->user('admin');
        $this->actingAs($nonNetwork)->withSession(['network.2fa_user_id'=>$nonNetwork->id])
            ->get($this->host.'/network/onboarding')->assertForbidden();
    }

    public function test_failed_retry_uses_shared_provisioner_and_records_network_actor(): void
    {
        $admin = $this->user('sysadmin');
        $failed = $this->application(SaasOnboardingApplication::FAILED, 'RETRYABLE');
        $this->networkAs($admin)->post($this->host.'/network/onboarding/'.$failed->uuid.'/retry')
            ->assertRedirect($this->host.'/network/onboarding/'.$failed->uuid);
        $this->assertDatabaseHas('saas_onboarding_events', [
            'onboarding_application_id'=>$failed->id,'event'=>'PROVISIONING_RETRY_REQUESTED',
            'actor_type'=>'network_user','actor_id'=>$admin->id,
        ]);
        $this->assertDatabaseHas('saas_onboarding_events', [
            'onboarding_application_id'=>$failed->id,'event'=>'PROVISIONING_RETRIED',
        ]);
        $this->assertSame(SaasOnboardingApplication::FAILED, $failed->fresh()->status);
    }

    public function test_active_cancelled_and_unpaid_are_not_retryable(): void
    {
        $admin = $this->user('sysadmin');
        foreach ([SaasOnboardingApplication::ACTIVE, SaasOnboardingApplication::CANCELLED, SaasOnboardingApplication::PENDING_PAYMENT] as $status) {
            $application = $this->application($status);
            $this->networkAs($admin)->post($this->host.'/network/onboarding/'.$application->uuid.'/retry')->assertStatus(409);
        }
        $this->assertDatabaseMissing('saas_onboarding_events', ['event'=>'PROVISIONING_RETRY_REQUESTED']);
    }

    private function networkAs(User $user): self
    {
        return $this->actingAs($user)->withSession(['network.2fa_user_id'=>$user->id,'network.2fa_verified_at'=>now()->timestamp]);
    }

    private function user(string $role): User
    {
        $user = User::create(['name'=>'Network','email'=>$role.uniqid().'@example.test','password'=>Hash::make('secret'),'empresa_id'=>1]);
        $model = Roles::firstOrCreate(['slug'=>$role],['name'=>$role]);
        $user->roles()->attach($model->id);
        return $user;
    }

    private function application(string $status, ?string $failure = null): SaasOnboardingApplication
    {
        return SaasOnboardingApplication::create([
            'status'=>$status,'contact_name'=>'Contact','contact_email'=>uniqid().'@example.test',
            'company_name'=>'Company','billing_period'=>'monthly','purchase_key'=>'purchase-'.uniqid(),
            'selected_plan_id'=>1,'requested_subdomain'=>'company-'.uniqid(),
            'commercial_snapshot_json'=>['plan'=>['id'=>1,'name'=>'Start','code'=>'START'],'modules'=>[],'billing_period'=>'monthly'],
            'subtotal'=>'100.00','tax_amount'=>'0.00','total'=>'100.00','currency'=>'MXN',
            'paid_at'=>in_array($status,[SaasOnboardingApplication::PAID,SaasOnboardingApplication::FAILED,SaasOnboardingApplication::ACTIVE],true)?now():null,
            'failure_code'=>$failure,
        ]);
    }

    private function schema(): void
    {
        Schema::create('users',fn(Blueprint$t)=>[$t->id(),$t->string('name'),$t->string('email')->unique(),$t->string('password'),$t->unsignedBigInteger('empresa_id'),$t->rememberToken(),$t->timestamps()]);
        Schema::create('roles',fn(Blueprint$t)=>[$t->id(),$t->string('name'),$t->string('slug')->unique(),$t->timestamps()]);
        Schema::create('users_roles',fn(Blueprint$t)=>[$t->unsignedBigInteger('user_id'),$t->unsignedBigInteger('roles_id')]);
        Schema::create('network_modules',fn(Blueprint$t)=>[$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('type'),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]);
        Schema::create('network_plans',fn(Blueprint$t)=>[$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('status'),$t->decimal('monthly_price',12,2)->nullable(),$t->decimal('annual_price',12,2)->nullable(),$t->char('currency',3),$t->unsignedInteger('included_operations')->nullable(),$t->timestamps()]);
        DB::table('network_plans')->insert(['id'=>1,'code'=>'START','name'=>'Start','status'=>'active','currency'=>'MXN','created_at'=>now(),'updated_at'=>now()]);
        Schema::create('network_tenants',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->string('name'),$t->string('slug')->unique(),$t->string('status'),$t->unsignedBigInteger('current_plan_id')->nullable(),$t->timestamps()]);
        Schema::create('network_tenant_brandings',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('brand_name')->nullable(),$t->timestamps()]);
        Schema::create('network_tenant_domains',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('domain'),$t->string('type'),$t->string('environment'),$t->boolean('is_primary'),$t->string('status'),$t->timestamp('verified_at')->nullable(),$t->timestamps()]);
        Schema::create('network_subscriptions',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('plan_id'),$t->string('status'),$t->timestamps()]);
        Schema::create('network_entitlements',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('module_id'),$t->string('code'),$t->boolean('is_enabled'),$t->string('source'),$t->timestamps()]);
        (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up();
        (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up();
        Schema::create('platform_payment_attempts',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid'),$t->unsignedBigInteger('tenant_id')->nullable(),$t->unsignedBigInteger('saas_order_id')->nullable(),$t->unsignedBigInteger('onboarding_application_id')->nullable(),$t->string('provider'),$t->string('status'),$t->string('provider_preference_id')->nullable(),$t->string('provider_payment_id')->nullable(),$t->string('external_reference'),$t->decimal('amount',12,2),$t->char('currency',3),$t->text('init_point')->nullable(),$t->timestamp('approved_at')->nullable(),$t->timestamp('rejected_at')->nullable(),$t->timestamps()]);
    }
}
