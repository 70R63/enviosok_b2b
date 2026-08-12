<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Billing\SaasSubscriptionLifecycleService;
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use App\Notifications\SaasBillingLifecycleNotification;

final class ZigoNetworkBillingLifecycleTest extends ZigoNetworkOperationsConsoleTest
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_08_20_110000_add_tenant_retention_fields.php'))->up();
        Schema::table('tenant_saas_orders', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('activated_at')->nullable();
        });
        Schema::create('zigo_notification_deliveries', function (Blueprint $table): void {
            $table->id(); $table->string('event_key')->unique(); $table->string('event_type'); $table->string('notifiable_type'); $table->unsignedBigInteger('notifiable_id');
            $table->json('recipients')->nullable(); $table->string('status')->default('PENDING'); $table->unsignedSmallInteger('attempts')->default(0); $table->timestamp('sent_at')->nullable(); $table->timestamp('failed_at')->nullable(); $table->string('last_error')->nullable(); $table->timestamps();
        });
    }

    public function test_reminders_are_configurable_and_idempotent(): void
    {
        Notification::fake(); config(['zigo_billing.reminder_days' => [7]]);
        [$tenant, $subscription] = $this->subscription(now()->addDays(7));
        $service = app(SaasSubscriptionLifecycleService::class);
        $this->assertTrue($service->notifyDue($subscription));
        $this->assertFalse($service->notifyDue($subscription->fresh()));
        $this->assertDatabaseCount('zigo_notification_deliveries', 1);
        Notification::assertCount(1);
    }

    public function test_renewal_is_an_idempotent_pending_order_and_expiration_suspends_without_deleting_data(): void
    {
        [$tenant, $subscription, $owner] = $this->subscription(now()->subDay());
        NetworkCommercialProduct::create(['code'=>'RENEW-M','name'=>'Renovación mensual','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>'345.67','currency'=>'MXN','plan_id'=>$subscription->plan_id,'included_operations'=>100,'is_active'=>true,'is_public'=>true]);
        $service = app(SaasSubscriptionLifecycleService::class);
        $first = $service->generateRenewal($subscription, $owner);
        $second = $service->generateRenewal($subscription, $owner);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('345.67', $first->total_amount);
        $this->assertSame('PENDING_PAYMENT', $first->status);
        $service->suspendExpired($subscription, $owner->id);
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertSame('suspended', $subscription->fresh()->status);
        $this->assertDatabaseHas('network_tenant_memberships', ['tenant_id'=>$tenant->id,'user_id'=>$owner->id]);
    }

    public function test_network_filters_show_expiring_tenant_and_browser_return_cannot_reactivate(): void
    {
        [$tenant, $subscription] = $this->subscription(now()->addDays(7));
        $admin = $this->networkAdmin();
        $this->asNetworkAdmin($admin)->get('https://network.zigo.local/network/subscriptions?lifecycle=upcoming')->assertOk()->assertSee($tenant->name);
        $this->get('http://zigo.local/zigo-platform/solicitud/not-a-token/resultado?status=approved')->assertNotFound();
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_notice_uses_server_side_order_amount_semantics_and_tenant_payment_cta(): void
    {
        Notification::fake(); config(['zigo_billing.reminder_days'=>[7]]);
        [$tenant,$subscription,$owner]=$this->subscription(now()->addDays(7));
        NetworkCommercialProduct::create(['code'=>'NOTICE-M','name'=>'Renovación aviso','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>'456.78','currency'=>'MXN','plan_id'=>$subscription->plan_id,'included_operations'=>100,'is_active'=>true,'is_public'=>true]);
        $service=app(SaasSubscriptionLifecycleService::class);
        $this->assertTrue($service->notifyDue($subscription));
        $order=\App\Domain\Network\Commerce\Models\TenantSaasOrder::where('tenant_id',$tenant->id)->firstOrFail();
        $this->assertSame('456.78',$order->total_amount);
        Notification::assertSentTo($owner,SaasBillingLifecycleNotification::class,function($notification)use($owner,$order,$tenant){$html=$notification->toMail($owner)->render()->toHtml();return str_contains($html,'Importe de renovación')&&!str_contains(strtolower($html),'adeudo')&&str_contains($html,'456.78')&&str_contains($html,'MXN')&&str_contains($html,$order->uuid)&&str_contains($html,$tenant->slug.'.zigo.local');});
        $this->assertFalse($service->notifyDue($subscription));
        $this->assertDatabaseCount('tenant_saas_orders',1);

        Notification::fake();$subscription->update(['current_period_end'=>now()->subDay()]);
        $expiredOrder=$service->generateRenewal($subscription->fresh(),$owner);
        $service->sendReminder($subscription->fresh());
        Notification::assertSentTo($owner,SaasBillingLifecycleNotification::class,fn($notification)=>str_contains($notification->toMail($owner)->render()->toHtml(),'Saldo pendiente'));
        $this->assertSame('PENDING',$expiredOrder->payment_status);
    }

    private function subscription($ends): array
    {
        $tenant=Tenant::create(['name'=>'Billing '.uniqid(),'slug'=>'billing-'.uniqid(),'status'=>'active','current_plan_id'=>1]);
        $owner=User::create(['name'=>'Owner','email'=>uniqid().'@billing.test','password'=>Hash::make('x'),'empresa_id'=>30]);
        TenantMembership::create(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']);
        $tenant->domains()->create(['domain'=>$tenant->slug.'.zigo.local','type'=>'subdomain','environment'=>'testing','is_primary'=>true,'status'=>'verified','verified_at'=>now()]);
        $subscription=Subscription::create(['tenant_id'=>$tenant->id,'plan_id'=>1,'status'=>'active','operations_limit'=>100,'started_at'=>now()->subMonth(),'current_period_start'=>now()->subMonth(),'current_period_end'=>$ends]);
        return [$tenant,$subscription,$owner];
    }

    private function networkAdmin(): User { $user=User::create(['name'=>'Admin','email'=>uniqid().'@network.test','password'=>Hash::make('x'),'empresa_id'=>31]); $role=\App\Models\Roles\Roles::firstOrCreate(['slug'=>'sysadmin'],['name'=>'sysadmin']); $user->roles()->attach($role); return $user; }
    private function asNetworkAdmin(User $user): self { return $this->actingAs($user)->withSession(['network.2fa_user_id'=>$user->id,'network.2fa_verified_at'=>now()->timestamp]); }
}
