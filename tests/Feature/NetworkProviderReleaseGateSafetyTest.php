<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\User;
use App\Services\B2cQuoteOptionsService;
use App\Services\ZigoCommercialQuoteService;
use App\Services\ZigoProviderQuoteObservationService;
use App\Services\ZigoProviderRateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProviderApiFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

/** Release verification only. No production behavior or existing assertion changes. */
final class NetworkProviderReleaseGateSafetyTest extends TestCase
{
    public static function inactiveModes(): array
    {
        return ['no connection' => ['absent'], 'inactive connection' => ['inactive']];
    }

    #[DataProvider('inactiveModes')]
    public function test_no_active_connection_preserves_national_checkout_and_has_no_provider_side_effects(string $mode): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite fixture required');
        }
        Http::preventStrayRequests();
        Mail::fake();
        ProviderApiFixture::schema();
        $f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($f);
        if ($mode === 'absent') {
            DB::table('network_provider_connection_services')->delete();
            $f['connection']->delete();
        } else {
            $f['connection']->forceFill(['status' => 'inactive'])->save();
        }
        app(TenantContext::class)->set($f['consumer']);
        session()->put('cotizacion_publica_id', $f['quote']->id);
        $option = ['logistico' => 'Estafeta', 'servicio' => 'Terrestre', 'service_code' => 'terrestre',
            'provider_source' => 'xperta', 'carrier' => 'estafeta', 'base_price' => 116, 'provider_quote_id' => 'national-offer'];
        $this->mock(ZigoProviderRateService::class)->shouldReceive('getOptionsForCotizacion')->twice()->andReturn([$option]);
        $this->assertSame([], app(TenantProviderQuoteSource::class)->options($f['quote'], 'anonymous'));
        $this->assertSame([$option], app(B2cQuoteOptionsService::class)->options($f['quote'], fn ($o) => $o, 'anonymous'));
        $pricing = ['base_price' => 116, 'final_price' => 200, 'profit_amount' => 84, 'margin_percentage' => 0,
            'fixed_fee' => 84, 'margin_amount' => 0, 'adjustment_type' => null, 'adjustment_value' => null,
            'adjustment_amount' => 0, 'discount_type' => null, 'discount_value' => null, 'discount_amount' => 0,
            'customer_segment' => 'anonymous', 'pricing_rule_id' => 77, 'adjustment_id' => null, 'client_pricing_rule_id' => null];
        $this->mock(ZigoCommercialQuoteService::class)->shouldReceive('calculate')->once()->andReturn(['pricing' => $pricing]);
        $this->mock(ZigoProviderQuoteObservationService::class)->shouldReceive('attachSelectionMetadata')->once();
        app(ZigoProviderQuoteObservationService::class)->shouldReceive('selectionMetadata')->andReturn([]);
        $this->post(route('b2c.seleccionar', $f['quote']), ['logistico' => 'Estafeta', 'servicio' => 'Terrestre'])
            ->assertRedirect(route('login'));
        $this->assertSame('xperta', $f['quote']->fresh()->provider);
        $this->assertSame('national-offer', $f['quote']->fresh()->provider_quote_reference);
        $this->assertSame('200.00', $f['quote']->fresh()->precio);
        $owner = User::create(['name' => 'National Customer', 'email' => 'national@example.test', 'password' => 'unused']);
        $f['quote']->refresh()->update(['user_id' => $owner->id]);
        $this->actingAs($owner)->get(route('b2c.checkout', $f['quote']))->assertOk()->assertSee('Estafeta')->assertSee('Terrestre')
            ->assertDontSee('Operado por Provider Delivery')->assertDontSee('provider_snapshot_uuid');
        // A real model save exercises the payment observer; no external payment is requested.
        $f['quote']->refresh()->update(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
            'payment_verified_amount' => 200, 'payment_verified_currency' => 'MXN']);
        foreach (['network:provider-fulfillment:retry', 'network:provider-payables:reconcile', 'network:provider-crm:deliver'] as $command) {
            $this->assertSame(0, Artisan::call($command));
        }
        foreach (['network_provider_quote_snapshots', 'network_provider_contracts', 'network_provider_fulfillments',
            'network_provider_payables', 'network_provider_payable_events', 'network_provider_settlement_entries',
            'tenant_api_webhook_deliveries', 'local_shipments', 'network_tenant_operations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($f['consumer']->id, app(TenantContext::class)->id());
        Http::assertNothingSent();
    }
}
