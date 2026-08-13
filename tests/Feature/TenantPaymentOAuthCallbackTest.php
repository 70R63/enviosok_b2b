<?php

namespace Tests\Feature;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Exceptions\MercadoPagoOAuthException;
use App\Domain\Payments\MercadoPagoPaymentProvider;
use App\Domain\Payments\Models\TenantPaymentConnection;
use App\Http\Controllers\Tenant\TenantPaymentConnectionController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Http, Schema};
use Mockery;
use Tests\TestCase;

final class TenantPaymentOAuthCallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('zigo_payments.providers.mercado_pago.oauth_cache_store', 'array');
        config()->set('zigo_payments.providers.mercado_pago.environment', 'sandbox');
        config()->set('zigo_payments.providers.mercado_pago.client_id', 'public-client-id');
        config()->set('zigo_payments.providers.mercado_pago.client_secret', 'server-secret');
        config()->set('zigo_payments.providers.mercado_pago.redirect_uri', 'https://payments.example.test/payments/mercado-pago/oauth/callback');
        config()->set('zigo_payments.providers.mercado_pago.api_url', 'https://api.mercadopago.com');
        Schema::create('network_tenant_memberships', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('user_id');
            $table->string('role'); $table->string('status'); $table->timestamps();
        });
        Schema::create('tenant_payment_connections', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->unsignedBigInteger('tenant_id');
            $table->string('provider'); $table->string('status')->default('PENDING');
            $table->string('provider_account_id')->nullable(); $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable(); $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable(); $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->unique(['tenant_id', 'provider']);
        });
        DB::table('network_tenant_memberships')->insert([
            ['tenant_id'=>10,'user_id'=>100,'role'=>'owner','status'=>'active','created_at'=>now(),'updated_at'=>now()],
            ['tenant_id'=>20,'user_id'=>200,'role'=>'owner','status'=>'active','created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_payment_connections');
        Schema::dropIfExists('network_tenant_memberships');
        parent::tearDown();
    }

    public function test_rejected_exchange_redirects_with_error_without_creating_or_altering_connection(): void
    {
        TenantPaymentConnection::create(['tenant_id'=>20,'provider'=>'MERCADO_PAGO','status'=>'CONNECTED','provider_account_id'=>'other-seller','access_token'=>'other-token']);
        $provider=Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('exchangeAuthorizationCode')->once()->with('bad-code','verifier')->andThrow(new MercadoPagoOAuthException('safe'));
        $response=$this->invokeOAuthCallback($provider,10,100,'bad-code');
        $this->assertSame('https://bruniverse.example.test/admin/configuracion/pagos',$response->getTargetUrl());
        $this->assertSame('No fue posible conectar Mercado Pago. Intenta autorizar la cuenta nuevamente.',session('error'));
        $this->assertDatabaseMissing('tenant_payment_connections',['tenant_id'=>10,'provider'=>'MERCADO_PAGO']);
        $this->assertDatabaseHas('tenant_payment_connections',['tenant_id'=>20,'status'=>'CONNECTED','provider_account_id'=>'other-seller']);
    }

    public function test_successful_exchange_connects_only_correlated_tenant_with_returned_seller(): void
    {
        $tokens=['access_token'=>'seller-token','refresh_token'=>'seller-refresh','expires_in'=>21600,'user_id_test'=>987654];
        Http::fake(['api.mercadopago.com/oauth/token'=>Http::response($tokens,200)]);
        $provider=new MercadoPagoPaymentProvider;
        $response=$this->invokeOAuthCallback($provider,10,100,'good-code');
        $this->assertSame('https://bruniverse.example.test/admin/configuracion/pagos',$response->getTargetUrl());
        $this->assertSame('Mercado Pago quedó conectado.',session('success'));
        $this->assertDatabaseHas('tenant_payment_connections',['tenant_id'=>10,'provider'=>'MERCADO_PAGO','status'=>'CONNECTED','provider_account_id'=>'987654']);
        $this->assertDatabaseMissing('tenant_payment_connections',['tenant_id'=>20,'provider'=>'MERCADO_PAGO']);
    }

    private function invokeOAuthCallback(PaymentProvider $provider,int $tenantId,int $userId,string $code)
    {
        $state='opaque-state';
        Cache::store('array')->put('zigo_mp_oauth:'.hash('sha256',$state),['verifier'=>'verifier','tenant_id'=>$tenantId,'user_id'=>$userId,'return_url'=>'https://bruniverse.example.test/admin/configuracion/pagos'],now()->addMinutes(10));
        $request=Request::create('/payments/mercado-pago/oauth/callback','GET',['state'=>$state,'code'=>$code]);
        $request->setLaravelSession(app('session')->driver());
        return (new TenantPaymentConnectionController)->callback($request,$provider);
    }
}
