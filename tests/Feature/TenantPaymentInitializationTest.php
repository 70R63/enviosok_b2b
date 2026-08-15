<?php

namespace Tests\Feature;

use App\Domain\Network\Channels\B2C\Models\TenantCustomerCheckout;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Models\TenantPaymentAttempt;
use App\Domain\Payments\TenantPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Mockery;
use Tests\TestCase;

final class TenantPaymentInitializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        Schema::create('tenant_customer_checkouts', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->string('status'); $table->char('currency', 3); $table->decimal('total_amount', 12, 2); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('tenant_payment_connections', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->string('status'); $table->timestamps();
        });
        Schema::create('tenant_payment_attempts', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid'); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('customer_profile_id'); $table->unsignedBigInteger('checkout_id'); $table->unsignedBigInteger('payment_connection_id'); $table->string('provider'); $table->string('status'); $table->string('provider_preference_id')->nullable(); $table->string('provider_payment_id')->nullable(); $table->string('external_reference'); $table->decimal('amount', 12, 2); $table->char('currency', 3); $table->decimal('marketplace_fee_amount', 12, 2); $table->text('init_point')->nullable(); $table->timestamp('approved_at')->nullable(); $table->timestamp('rejected_at')->nullable(); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_payment_attempts');
        Schema::dropIfExists('tenant_payment_connections');
        Schema::dropIfExists('tenant_customer_checkouts');
        parent::tearDown();
    }

    public function test_initialize_uses_environment_specific_checkout_url(): void
    {
        DB::table('tenant_customer_checkouts')->insert(['id'=>1,'uuid'=>'10000000-0000-0000-0000-000000000001','status'=>'PENDING_PAYMENT','currency'=>'MXN','total_amount'=>207.64,'expires_at'=>now()->addHour(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('tenant_payment_connections')->insert(['id'=>1,'uuid'=>'20000000-0000-0000-0000-000000000001','status'=>'CONNECTED','created_at'=>now(),'updated_at'=>now()]);
        foreach (['sandbox','production'] as $index=>$environment) {
            $id=$index+1;DB::table('tenant_payment_attempts')->insert(['id'=>$id,'uuid'=>'30000000-0000-0000-0000-00000000000'.$id,'tenant_id'=>1,'customer_profile_id'=>1,'checkout_id'=>1,'payment_connection_id'=>1,'provider'=>'MERCADO_PAGO','status'=>'CREATED','external_reference'=>'zg_'.$environment,'amount'=>207.64,'currency'=>'MXN','marketplace_fee_amount'=>5,'created_at'=>now(),'updated_at'=>now()]);
            config()->set('zigo_payments.providers.mercado_pago.environment',$environment);
            $provider=Mockery::mock(PaymentProvider::class);$provider->shouldReceive('createCheckout')->once()->andReturn(['id'=>'pref-'.$environment,'sandbox_init_point'=>'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=sandbox','init_point'=>'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=production']);
            $service=new TenantPaymentService($provider,app(\App\Domain\Payments\VerifiedTenantPaymentService::class));$initialized=$service->initialize(TenantPaymentAttempt::findOrFail($id));
            $this->assertStringContainsString('pref_id='.$environment,$initialized->init_point);
        }
        $this->assertSame('PENDING',TenantPaymentAttempt::findOrFail(1)->status);
        $this->assertSame('PENDING',TenantPaymentAttempt::findOrFail(2)->status);
        $this->assertSame('207.64',(string)TenantCustomerCheckout::findOrFail(1)->total_amount);
    }
}
