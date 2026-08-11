<?php

namespace Tests\Feature;

use App\Domain\Network\Onboarding\Services\SaasTenantProvisioningService;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Notifications\SaasOwnerWelcomeNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Hash, Notification, Queue, Schema};

final class ZigoSaasOnboardingEndToEndTest extends ZigoSaasOnboardingPaymentProvisioningTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('saas_onboarding_applications', function (Blueprint $table): void {
            $table->boolean('owner_is_new')->nullable();
            $table->timestamp('owner_activation_sent_at')->nullable();
            $table->timestamp('owner_activation_completed_at')->nullable();
            $table->timestamp('setup_started_at')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
        });
        Schema::create('password_resets',fn(Blueprint$t)=>[$t->string('email')->index(),$t->string('token'),$t->timestamp('created_at')->nullable()]);
        Schema::create('zigo_notification_deliveries',fn(Blueprint$t)=>[$t->id(),$t->string('event_key')->unique(),$t->string('event_type'),$t->string('notifiable_type'),$t->unsignedBigInteger('notifiable_id'),$t->json('recipients')->nullable(),$t->string('status')->default('PENDING'),$t->unsignedSmallInteger('attempts')->default(0),$t->timestamp('sent_at')->nullable(),$t->timestamp('failed_at')->nullable(),$t->string('last_error')->nullable(),$t->timestamps()]);
        Schema::create('roles',fn(Blueprint$t)=>[$t->id(),$t->string('name'),$t->string('slug')->unique(),$t->timestamps()]);
        Schema::create('users_roles',fn(Blueprint$t)=>[$t->unsignedBigInteger('user_id'),$t->unsignedBigInteger('roles_id')]);
        Schema::create('tenant_customer_profiles',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('user_id'),$t->string('status'),$t->string('display_name')->nullable(),$t->string('phone')->nullable(),$t->timestamps(),$t->unique(['tenant_id','user_id'])]);
        Schema::create('network_tenant_operations',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('channel'),$t->string('status'),$t->string('source_type')->nullable(),$t->unsignedBigInteger('source_id')->nullable(),$t->string('provider')->nullable(),$t->string('service_code')->nullable(),$t->string('external_reference')->nullable(),$t->unsignedBigInteger('created_by_user_id')->nullable(),$t->unsignedBigInteger('customer_profile_id')->nullable(),$t->json('metadata')->nullable(),$t->timestamps()]);
        Schema::create('network_usage_events',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->nullable(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('subscription_id')->nullable(),$t->string('metric'),$t->unsignedInteger('quantity'),$t->string('idempotency_key')->nullable(),$t->timestamp('occurred_at'),$t->json('metadata')->nullable(),$t->timestamp('created_at')->nullable()]);
        Schema::create('local_shipments',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('tenant_operation_id'),$t->string('tracking_number')->unique(),$t->string('service_code'),$t->string('status'),$t->json('sender_snapshot'),$t->json('recipient_snapshot'),$t->json('package_snapshot'),$t->json('pricing_snapshot'),$t->json('guide_snapshot'),$t->timestamps()]);
        Schema::create('tenant_customer_checkouts',fn(Blueprint$t)=>[$t->id(),$t->uuid('uuid')->unique(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('customer_profile_id'),$t->unsignedBigInteger('tenant_operation_id'),$t->string('status'),$t->string('payment_status'),$t->char('currency',3),$t->decimal('shipping_amount',12,2),$t->decimal('evidence_amount',12,2),$t->decimal('total_amount',12,2),$t->json('quote_snapshot'),$t->json('shipping_data_snapshot'),$t->json('proof_option_snapshot'),$t->timestamp('expires_at')->nullable(),$t->timestamps()]);
        Schema::create('tenant_payment_connections',fn(Blueprint$t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->string('provider'),$t->string('status'),$t->timestamps()]);
        config(['zigo_onboarding.tenant_admin_scheme'=>'http']);
    }

    public function test_unknown_visitor_reaches_operational_tenant_owner_customer_and_network(): void
    {
        Notification::fake(); Queue::fake();
        $corporate = 'http://zigo.local:8000';
        $this->get($corporate.'/')->assertOk()
            ->assertSee('href="/zigo-platform"', false)
            ->assertSee('ZIGO Platform');
        $this->get($corporate.'/zigo-platform')->assertOk()
            ->assertSee('href="'.$corporate.'/zigo-platform/precios"', false)
            ->assertSee('href="'.$corporate.'/zigo-platform/comenzar"', false);
        $this->get($corporate.'/zigo-platform/precios')->assertOk();
        $this->get($corporate.'/zigo-platform/comenzar')->assertOk();

        [$application, $attempt] = $this->pending('e2e-certified', true, 'owner-e2e@example.test', true);
        $this->get($corporate.'/zigo-platform/precios')->assertOk()
            ->assertSee('/zigo-platform/comenzar?offer=', false);
        $this->assertSame('PENDING_PAYMENT', $application->status);
        foreach (['network_tenants','empresas','users','network_tenant_memberships','network_subscriptions','network_entitlements','network_tenant_brandings','network_tenant_domains'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table.' must be empty before APPROVED');
        }
        $this->get($corporate.'/zigo-platform/solicitud/'.$application->public_token.'/retorno/success')->assertOk();
        $this->assertSame('PENDING_PAYMENT', $application->fresh()->status);

        $this->sendWebhook($attempt, 'e2e-approved', 'approved')->assertOk();
        $this->assertSame('PAID', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->paid_at);
        $active = app(SaasTenantProvisioningService::class)->provision($application->fresh());
        $this->assertSame('ACTIVE', $active->status);
        foreach (['network_tenants','empresas','users','network_tenant_memberships','network_subscriptions','network_tenant_brandings','network_tenant_domains'] as $table) {
            $this->assertSame(1, DB::table($table)->count(), $table);
        }
        $this->assertSame(3, DB::table('network_entitlements')->count());
        $domain = DB::table('network_tenant_domains')->where('is_primary',1)->where('status','verified')->value('domain');
        $this->assertSame($active->requested_subdomain.'.zigo-envios.com', $domain);

        $owner = User::findOrFail($active->owner_user_id);
        $activationUrl = null;
        Notification::assertSentTo($owner, SaasOwnerWelcomeNotification::class, function ($notification) use ($owner, &$activationUrl) {
            $activationUrl = $notification->toMail($owner)->actionUrl; return true;
        });
        $this->assertNotNull($activationUrl);
        $parts = parse_url($activationUrl);
        preg_match('#/admin/activate/[^/]+/([^/]+)$#', $parts['path'], $matches);
        $token = $matches[1];
        $tenantBase = 'http://'.$domain;
        $this->post($tenantBase.'/admin/activate/'.$active->public_token, [
            'token'=>$token,'password'=>'Owner-safe-123','password_confirmation'=>'Owner-safe-123',
        ])->assertRedirect($tenantBase.'/admin/setup');
        $this->assertTrue(Hash::check('Owner-safe-123', $owner->fresh()->password));
        $this->get($tenantBase.'/admin/setup')->assertOk();
        $this->post($tenantBase.'/admin/setup', ['brand_name'=>'E2E Company','finish'=>'1'])
            ->assertRedirect($tenantBase.'/admin');
        $this->get($tenantBase.'/admin')->assertOk();
        $this->post($tenantBase.'/admin/logout');

        $this->get($tenantBase.'/')->assertOk()->assertDontSee('RapidGo');
        $this->get($tenantBase.'/registro')->assertOk();
        $this->post($tenantBase.'/registro', [
            'name'=>'Final Customer','email'=>'customer-e2e@example.test','password'=>'Customer-safe-123',
            'password_confirmation'=>'Customer-safe-123','terms'=>'1',
        ])->assertRedirect('/app');
        $customer = User::where('email','customer-e2e@example.test')->firstOrFail();
        $this->assertDatabaseMissing('network_tenant_memberships',['user_id'=>$customer->id]);
        $this->get($tenantBase.'/app')->assertOk();
        $this->get($tenantBase.'/app/cotizar')->assertOk();
        $profileId = DB::table('tenant_customer_profiles')->where('user_id',$customer->id)->value('id');
        $operation = TenantOperation::create([
            'tenant_id'=>$active->tenant_id,'subscription_id'=>DB::table('network_subscriptions')->value('id'),
            'customer_profile_id'=>$profileId,'channel'=>'b2c','status'=>'quoted',
            'metadata'=>['origin_postal_code'=>'64000','destination_postal_code'=>'64000','quoted_package'=>['type'=>'sobre','weight'=>1],'selected_quote'=>['service'=>'Local','price'=>100]],
        ]);
        $this->withSession(['tenant_customer.active_operation'=>$operation->uuid])
            ->get($tenantBase.'/app/envio/nuevo')->assertOk();

        $sysadmin = User::create(['name'=>'Network','email'=>'network-e2e@example.test','password'=>Hash::make('secret'),'empresa_id'=>$active->legacy_empresa_id]);
        $role = Roles::create(['name'=>'Sysadmin','slug'=>'sysadmin']); $sysadmin->roles()->attach($role->id);
        $network = 'http://network.zigo.local:8000';
        $this->actingAs($sysadmin)->get($network.'/network/onboarding')->assertOk()->assertSee('ACTIVE')->assertSee('APPROVED');
        $this->get($network.'/network/onboarding/'.$active->uuid)->assertOk()->assertSee($domain)->assertSee('PROVISIONING_COMPLETED');
    }
}
