<?php

namespace Tests\Feature;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Onboarding\Services\OwnerActivationService;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\User;
use App\Notifications\SaasOwnerWelcomeNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Notification, Password, Schema};
use Tests\TestCase;

final class ZigoSaasOnboardingOwnerActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
        config(['zigo_onboarding.tenant_admin_scheme' => 'http']);
    }

    public function test_only_active_new_owner_receives_one_activation_without_password_or_secret(): void
    {
        Notification::fake();
        [$application, $owner] = $this->activeApplication('acme');
        app(OwnerActivationService::class)->sendIfNeeded($application);
        app(OwnerActivationService::class)->sendIfNeeded($application->fresh());

        Notification::assertSentToTimes($owner, SaasOwnerWelcomeNotification::class, 1);
        $this->assertNotNull($application->fresh()->owner_activation_sent_at);
        $this->assertDatabaseCount('password_resets', 1);
        $this->assertDatabaseCount('zigo_notification_deliveries', 1);
        $this->assertSame(1, $application->events()->where('event', 'OWNER_ACTIVATION_SENT')->count());
        $serialized = $application->events()->pluck('metadata_json')->implode(' ');
        $this->assertStringNotContainsString('password', strtolower($serialized));
        $this->assertStringNotContainsString('token', strtolower($serialized));

        foreach ([SaasOnboardingApplication::PENDING_PAYMENT, SaasOnboardingApplication::PAID, SaasOnboardingApplication::FAILED] as $index => $status) {
            [$inactive, $inactiveOwner] = $this->application('inactive'.$index, $status);
            app(OwnerActivationService::class)->sendIfNeeded($inactive);
            Notification::assertNothingSentTo($inactiveOwner);
        }
        Notification::assertSentToTimes($owner, SaasOwnerWelcomeNotification::class, 1);
    }

    public function test_activation_token_is_single_use_host_bound_and_redirects_to_setup(): void
    {
        [$application, $owner] = $this->activeApplication('secure');
        $token = Password::broker()->createToken($owner);
        $url = 'http://secure.zigo-envios.com/admin/activate/'.$application->public_token.'/'.$token;
        $this->get($url)->assertOk()->assertSee('Activa tu cuenta');
        $this->get('http://otro.zigo-envios.com/admin/activate/'.$application->public_token.'/'.$token)->assertNotFound();
        $this->post('http://secure.zigo-envios.com/admin/activate/'.$application->public_token, [
            'token' => $token, 'password' => 'Nueva-clave-123', 'password_confirmation' => 'Nueva-clave-123',
        ])->assertRedirect('http://secure.zigo-envios.com/admin/setup');
        $this->assertAuthenticatedAs($owner);
        $this->assertNotNull($application->fresh()->owner_activation_completed_at);
        $this->assertTrue(Hash::check('Nueva-clave-123', $owner->fresh()->password));
        $this->assertDatabaseCount('network_tenants', 1);
        $this->assertDatabaseCount('network_entitlements', 1);
        $this->post('http://secure.zigo-envios.com/admin/activate/'.$application->public_token, [
            'token' => $token, 'password' => 'Otra-clave-123', 'password_confirmation' => 'Otra-clave-123',
        ])->assertStatus(410);
    }

    public function test_token_for_one_owner_cannot_activate_another_and_existing_owner_gets_login_link(): void
    {
        Notification::fake();
        [$first, $owner] = $this->activeApplication('first');
        [$second, $other] = $this->activeApplication('second', false);
        $token = Password::broker()->createToken($owner);
        $this->post('http://second.zigo-envios.com/admin/activate/'.$second->public_token, [
            'token' => $token, 'password' => 'Nueva-clave-123', 'password_confirmation' => 'Nueva-clave-123',
        ])->assertStatus(410);
        $this->assertFalse(Hash::check('Nueva-clave-123', $other->fresh()->password));

        app(OwnerActivationService::class)->sendIfNeeded($second);
        Notification::assertSentTo($other, SaasOwnerWelcomeNotification::class, fn ($notification) =>
            str_contains($notification->toMail($other)->actionUrl, 'second.zigo-envios.com/admin/login'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_welcome_urls_use_the_persisted_stage_and_production_domains(): void
    {
        Notification::fake();
        [$stage, $stageOwner] = $this->activeApplication('bruniverse-stage', false);
        [$production, $productionOwner] = $this->activeApplication('bruniverse', false);

        app(OwnerActivationService::class)->sendIfNeeded($stage);
        app(OwnerActivationService::class)->sendIfNeeded($production);

        Notification::assertSentTo($stageOwner, SaasOwnerWelcomeNotification::class, fn ($notification) =>
            str_contains($notification->toMail($stageOwner)->actionUrl, 'bruniverse-stage.zigo-envios.com/admin/login'));
        Notification::assertSentTo($productionOwner, SaasOwnerWelcomeNotification::class, fn ($notification) =>
            str_contains($notification->toMail($productionOwner)->actionUrl, 'bruniverse.zigo-envios.com/admin/login'));
    }

    public function test_owner_setup_preserves_entitlements_and_finishes_to_dashboard(): void
    {
        [$application, $owner] = $this->activeApplication('setup');
        $before = DB::table('network_entitlements')->get()->toJson();
        $this->actingAs($owner)->get('http://setup.zigo-envios.com/admin/setup')->assertOk()
            ->assertSee('Configuración inicial')->assertSee('SHIPPING');
        $this->actingAs($owner)->post('http://setup.zigo-envios.com/admin/setup', [
            'brand_name' => 'Mi Marca', 'primary_color' => '#112233',
            'secondary_color' => '#223344', 'accent_color' => '#334455', 'finish' => '1',
        ])->assertRedirect('http://setup.zigo-envios.com/admin');
        $this->assertNotNull($application->fresh()->setup_started_at);
        $this->assertNotNull($application->fresh()->setup_completed_at);
        $this->assertSame($before, DB::table('network_entitlements')->get()->toJson());
        $this->assertSame('Mi Marca', DB::table('network_tenant_brandings')->value('brand_name'));
    }

    public function test_expired_token_and_user_without_membership_cannot_enter_tenant(): void
    {
        [$application, $owner] = $this->activeApplication('expired');
        $token = Password::broker()->createToken($owner);
        DB::table('password_resets')->where('email', $owner->email)->update(['created_at'=>now()->subHours(2)]);
        $this->get('http://expired.zigo-envios.com/admin/activate/'.$application->public_token.'/'.$token)->assertStatus(410);

        $outsider = User::create(['name'=>'Network','email'=>'outsider@example.test','password'=>Hash::make('secret'),'empresa_id'=>1]);
        $before = DB::table('network_tenant_memberships')->count();
        $this->actingAs($outsider)->get('http://expired.zigo-envios.com/admin')->assertRedirect('http://expired.zigo-envios.com/admin/login');
        $this->assertSame($before, DB::table('network_tenant_memberships')->count());
    }

    private function activeApplication(string $slug, bool $isNew = true): array
    {
        [$application, $owner] = $this->application($slug, SaasOnboardingApplication::ACTIVE, $isNew);
        return [$application, $owner];
    }

    private function application(string $slug, string $status, bool $isNew = true): array
    {
        $owner = User::create(['name'=>'Owner','email'=>$slug.'@example.test','password'=>Hash::make('random-secret'),'empresa_id'=>1]);
        $tenant = Tenant::create(['name'=>'Company '.$slug,'slug'=>$slug,'status'=>'active','current_plan_id'=>1]);
        DB::table('network_tenant_domains')->insert(['tenant_id'=>$tenant->id,'domain'=>$slug.'.zigo-envios.com','type'=>'subdomain','environment'=>'production','is_primary'=>1,'status'=>'verified','verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('network_tenant_memberships')->insert(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        DB::table('network_tenant_brandings')->insert(['tenant_id'=>$tenant->id,'brand_name'=>'Company '.$slug,'created_at'=>now(),'updated_at'=>now()]);
        $subscriptionId = DB::table('network_subscriptions')->insertGetId(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'tenant_id'=>$tenant->id,'plan_id'=>1,'status'=>'active','started_at'=>now(),'current_period_start'=>now(),'current_period_end'=>now()->addMonth(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('network_entitlements')->insert(['subscription_id'=>$subscriptionId,'tenant_id'=>$tenant->id,'module_id'=>1,'code'=>'SHIPPING','is_enabled'=>1,'source'=>'plan','created_at'=>now(),'updated_at'=>now()]);
        $application = SaasOnboardingApplication::create(['status'=>$status,'contact_name'=>'Owner','contact_email'=>$owner->email,'company_name'=>'Company '.$slug,'billing_period'=>'monthly','purchase_key'=>'purchase-'.$slug,'tenant_id'=>$tenant->id,'owner_user_id'=>$owner->id,'owner_is_new'=>$isNew,'selected_plan_id'=>1]);
        return [$application,$owner];
    }

    private function schema(): void
    {
        Schema::create('users',fn(Blueprint$t)=>[$t->id(),$t->string('name'),$t->string('email')->unique(),$t->string('password'),$t->unsignedBigInteger('empresa_id'),$t->rememberToken(),$t->timestamps()]);
        Schema::create('password_resets',fn(Blueprint$t)=>[$t->string('email')->index(),$t->string('token'),$t->timestamp('created_at')->nullable()]);
        Schema::create('network_plans',fn(Blueprint$t)=>[$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('status'),$t->decimal('monthly_price',12,2)->nullable(),$t->decimal('annual_price',12,2)->nullable(),$t->char('currency',3),$t->unsignedInteger('included_operations')->nullable(),$t->timestamps()]);
        DB::table('network_plans')->insert(['id'=>1,'code'=>'START','name'=>'Start','status'=>'active','currency'=>'MXN','created_at'=>now(),'updated_at'=>now()]);
        Schema::create('network_modules',fn(Blueprint$t)=>[$t->id(),$t->string('code')->unique(),$t->string('name'),$t->string('type'),$t->boolean('is_active'),$t->unsignedSmallInteger('sort_order'),$t->timestamps()]);
        DB::table('network_modules')->insert(['id'=>1,'code'=>'SHIPPING','name'=>'SHIPPING','type'=>'core','is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('network_tenants',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->string('name'),$t->string('slug')->unique(),$t->string('status'),$t->unsignedBigInteger('current_plan_id')->nullable(),$t->timestamps()]);
        Schema::create('network_tenant_domains',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('domain')->unique(),$t->string('type'),$t->string('environment'),$t->boolean('is_primary'),$t->string('status'),$t->timestamp('verified_at')->nullable(),$t->timestamps()]);
        Schema::create('network_tenant_memberships',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('role'),$t->string('status'),$t->timestamps()]);
        Schema::create('network_tenant_brandings',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id')->unique(),$t->string('brand_name')->nullable(),$t->string('logo_path')->nullable(),$t->string('primary_color')->nullable(),$t->string('secondary_color')->nullable(),$t->string('accent_color')->nullable(),$t->string('favicon_path')->nullable(),$t->string('support_email')->nullable(),$t->string('support_phone')->nullable(),$t->timestamps()]);
        Schema::create('network_subscriptions',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('plan_id'),$t->string('status'),$t->unsignedInteger('operations_limit')->nullable(),$t->timestamp('started_at')->nullable(),$t->timestamp('current_period_start')->nullable(),$t->timestamp('current_period_end')->nullable(),$t->timestamp('trial_ends_at')->nullable(),$t->timestamp('grace_ends_at')->nullable(),$t->timestamp('canceled_at')->nullable(),$t->timestamp('ended_at')->nullable(),$t->timestamps()]);
        Schema::create('network_entitlements',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('subscription_id'),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('module_id'),$t->string('code'),$t->boolean('is_enabled'),$t->unsignedInteger('limit_value')->nullable(),$t->string('source'),$t->timestamps()]);
        (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up();
        (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up();
        Schema::table('saas_onboarding_applications',function(Blueprint$t){$t->boolean('owner_is_new')->nullable();$t->timestamp('owner_activation_sent_at')->nullable();$t->timestamp('owner_activation_completed_at')->nullable();$t->timestamp('setup_started_at')->nullable();$t->timestamp('setup_completed_at')->nullable();});
        Schema::create('zigo_notification_deliveries',fn(Blueprint$t)=>[$t->id(),$t->string('event_key')->unique(),$t->string('event_type'),$t->string('notifiable_type'),$t->unsignedBigInteger('notifiable_id'),$t->json('recipients')->nullable(),$t->string('status')->default('PENDING'),$t->unsignedSmallInteger('attempts')->default(0),$t->timestamp('sent_at')->nullable(),$t->timestamp('failed_at')->nullable(),$t->string('last_error')->nullable(),$t->timestamps()]);
        Schema::create('tenant_payment_connections',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('provider'),$t->string('status'),$t->timestamps()]);
    }
}
