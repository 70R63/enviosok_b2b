<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderConsumerResolver;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\TenantDomain;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Network\Tenancy\TenantDomainResolver;
use App\Models\B2cSaldo;
use App\Models\User;
use App\Services\Billing\B2cCheckoutDebtService;
use App\Services\Identity\IdentityGuideAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

final class HistoricalB2cProviderNetworkTest extends TestCase
{
    private array $f;
    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Mail::fake();
        ProviderFulfillmentFixture::schema();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->f = ProviderContractFixture::seed();
        $this->f['consumer']->update(['name' => 'ZIGO', 'slug' => 'zigo']);
        $this->f['provider']->update(['name' => 'ENFA', 'slug' => 'entregasenfa']);
        ProviderFulfillmentFixture::operational($this->f);
        $this->host = config('zigo_domains.portals.b2c.host');
        config(['provider_network.corporate_consumer_slug' => 'zigo', 'provider_network.corporate_consumer_uuid' => null]);
        TenantDomain::create(['tenant_id' => $this->f['consumer']->id, 'domain' => $this->host, 'status' => 'verified']);
        app(TenantContext::class)->clear();
        request()->headers->set('host', $this->host);
    }

    public function test_reserved_host_clears_context_without_resolving_verified_domain(): void
    {
        app(TenantContext::class)->set($this->f['consumer']);
        $this->assertNull(app(TenantDomainResolver::class)->resolve($this->host));
        $this->assertNull(app(TenantContext::class)->current());
        $this->assertDatabaseCount('network_tenant_domains', 1);
    }

    public function test_login_and_ingresar_render_historical_login(): void
    {
        foreach (['/login', '/ingresar'] as $path) {
            $this->get('http://'.$this->host.$path)->assertOk()->assertViewIs('auth.login');
        }
        $this->assertDatabaseMissing('network_subscriptions', ['tenant_id' => $this->f['consumer']->id]);
        $this->assertDatabaseMissing('network_entitlements', ['tenant_id' => $this->f['consumer']->id]);
    }

    public function test_ingresar_authenticates_existing_user_without_tenant_profile(): void
    {
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => Hash::make('secret-pass')]);
        $this->post('http://'.$this->host.'/ingresar', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect('/b2c/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_white_label_ingresar_keeps_tenant_action_and_entitlement_guard(): void
    {
        $host = 'entregasenfa-stage.zigo-envios.com';
        TenantDomain::create(['tenant_id' => $this->f['provider']->id, 'domain' => $host, 'status' => 'verified']);
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('http://'.$host.'/ingresar'));
        $this->assertSame('tenant.customer.login', $route->getName());
        $this->assertSame('App\Http\Controllers\Tenant\CustomerAuthController@create', $route->getActionName());
        $this->assertContains('tenant.subscription', $route->gatherMiddleware());
        $this->assertContains('tenant.entitlement:B2C', $route->gatherMiddleware());
        $this->get('http://'.$host.'/ingresar')->assertForbidden();
        DB::table('network_entitlements')->insert(['tenant_id' => $this->f['provider']->id,
            'subscription_id' => DB::table('network_subscriptions')->value('id'), 'code' => 'B2C', 'is_enabled' => true]);
        $this->get('http://'.$host.'/ingresar')->assertOk()->assertViewIs('tenant.customer.auth.login');
    }

    public function test_explicit_consumer_resolution_is_isolated_and_fails_closed(): void
    {
        $resolver = app(ProviderConsumerResolver::class);
        $this->assertTrue($resolver->current()->is($this->f['consumer']));
        $this->assertNull(app(TenantContext::class)->current());
        config(['provider_network.corporate_consumer_uuid' => $this->f['provider']->uuid]);
        $this->assertTrue($resolver->current()->is($this->f['provider']));
        config(['provider_network.corporate_consumer_uuid' => 'missing']);
        $this->assertTrue($resolver->current()->is($this->f['consumer']));
        $this->f['consumer']->update(['status' => 'inactive']);
        $this->assertNull($resolver->current());
        $this->f['consumer']->update(['status' => 'active']);
        config(['provider_network.corporate_consumer_uuid' => null, 'provider_network.corporate_consumer_slug' => null]);
        $this->assertNull($resolver->current());
        config(['provider_network.corporate_consumer_slug' => 'zigo']);
        request()->headers->set('host', 'unknown.example.test');
        $this->assertNull($resolver->current());
        app(TenantContext::class)->set($this->f['provider']);
        $this->assertTrue($resolver->current()->is($this->f['provider']));
    }

    public function test_b2c_host_quotes_enfa_without_tenant_context(): void
    {
        $options = app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous');
        $this->assertCount(1, $options);
        $this->assertSame($this->f['provider']->id, $options[0]['_tenant_provider']['provider_tenant_id']);
        $this->assertNull(app(TenantContext::class)->current());
    }

    public function test_balance_purchase_automatically_fulfills_once_without_mercado_pago(): void
    {
        Schema::table('b2c_movimientos_saldo', function (Blueprint $t): void {
            $t->decimal('saldo_anterior', 12, 2)->nullable();
            $t->decimal('saldo_nuevo', 12, 2)->nullable();
        });
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'balance@example.test', 'password' => 'test']);
        $this->actingAs($user);
        B2cSaldo::create(['user_id' => $user->id, 'saldo' => 500]);
        $quote = $this->f['quote'];
        $quote->update(['user_id' => $user->id]);
        session()->put('cotizacion_publica_id', $quote->id);
        $option = app(ProviderQuotePublisher::class)->publish($quote,
            app(TenantProviderQuoteSource::class)->options($quote, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($quote, $option['provider_snapshot_uuid']);
        $quote->refresh()->update(['estatus' => 'CHECKOUT_COMPLETO', 'guia_request_snapshot' => ['pickup_request' => ['mode' => 'ASAP', 'requested_date' => null, 'window_start' => null, 'window_end' => null, 'timezone' => config('app.timezone'), 'requested_at' => now()->toIso8601String()]]]);
        $this->mock(IdentityGuideAccessService::class)->shouldReceive('assertCanProceed')->andReturn([]);
        $this->mock(B2cCheckoutDebtService::class, function ($mock): void {
            $mock->shouldReceive('reserveForCotizacion')->once()->andReturn(['payment_total' => 145]);
            $mock->shouldReceive('expectedPaymentTotal')->andReturn(145.0);
            $mock->shouldReceive('applyReserved')->andReturn([]);
        });
        $url = 'http://'.$this->host.'/b2c/pago/'.$quote->id.'/saldo';
        $this->post($url)->assertRedirect(route('b2c.pago.success', $quote));
        $quote->refresh();
        $this->assertSame('saldo_prepago', $quote->payment_status);
        $this->assertSame('VERIFIED', $quote->payment_verification_status);
        $this->assertSame('GUIA_GENERADA', $quote->estatus);
        $this->assertSame('GENERADA', $quote->guia_estatus);
        $this->assertNotEmpty($quote->guia_id);
        $this->assertNotEmpty($quote->tracking_number);
        $bridge = ProviderFulfillment::firstOrFail();
        $this->assertSame('READY_FOR_PICKUP', $bridge->shipment->status);
        $this->assertNull($bridge->operation->customer_profile_id);
        // A browser retry must not downgrade an already generated guide or purchase again.
        $this->post($url)->assertRedirect();
        $this->assertEquals(355, B2cSaldo::where('user_id', $user->id)->value('saldo'));
        $this->assertDatabaseCount('b2c_movimientos_saldo', 1);
        $this->assertDatabaseHas('b2c_movimientos_saldo', ['user_id' => $user->id, 'tipo' => 'COMPRA_GUIA', 'monto' => 145, 'estatus' => 'APLICADO']);
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertSame('GUIA_GENERADA', $quote->fresh()->estatus);
        Http::assertNothingSent();
    }
}
