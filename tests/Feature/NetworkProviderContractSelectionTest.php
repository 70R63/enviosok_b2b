<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\ZigoCommercialQuoteService;
use App\Services\ZigoProviderQuoteObservationService;
use App\Services\ZigoProviderRateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderContractFixture;
use Tests\TestCase;

final class NetworkProviderContractSelectionTest extends TestCase
{
    private array $fixture;

    private B2cCotizacion $quote;

    private array $option;

    private ProviderQuoteSnapshot $offer;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        ProviderContractFixture::schema();
        $this->fixture = ProviderContractFixture::seed();
        $this->quote = $this->fixture['quote'];
        app(TenantContext::class)->set($this->fixture['consumer']);
        session()->put('cotizacion_publica_id', $this->quote->id);
        $this->option = $this->issue();
        $this->offer = ProviderQuoteSnapshot::where('uuid', $this->option['provider_snapshot_uuid'])->firstOrFail();
    }

    public function test_valid_local_offer_can_be_selected_and_attached_to_checkout(): void
    {
        $contract = $this->select();
        $this->assertSame($this->offer->id, $contract->quote_snapshot_id);
        $this->assertSame($this->quote->id, $contract->b2c_cotizacion_id);
        $this->assertSame($contract->id, $this->quote->fresh()->providerContract->id);
        $this->assertSame('SELECCIONADA', $this->quote->fresh()->estatus);
    }

    public function test_expired_offer_is_rejected(): void
    {
        $this->travel(31)->minutes();
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_another_consumer_cannot_select_offer(): void
    {
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']));
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_browser_provider_is_ignored(): void
    {
        $this->postSelection(['provider_tenant_id' => $this->fixture['consumer']->id])->assertRedirect(route('login'));
        $this->assertSame($this->fixture['provider']->id, $this->quote->fresh()->providerContract->snapshot->provider_tenant_id);
    }

    public function test_browser_price_is_ignored(): void
    {
        $this->postSelection(['precio' => 1, 'price' => 1, 'provider_cost' => 1])->assertRedirect(route('login'));
        $this->assertSame('145.00', $this->quote->fresh()->precio);
    }

    public function test_browser_service_and_visible_names_are_ignored(): void
    {
        $this->postSelection(['service_id' => 999, 'servicio' => 'Forged', 'logistico' => 'Estafeta'])->assertRedirect(route('login'));
        $this->assertSame($this->fixture['service']->id, $this->quote->fresh()->providerContract->snapshot->local_shipping_service_id);
        $this->assertSame('Provider Express', $this->quote->fresh()->logistico);
    }

    public function test_deactivated_connection_rejects_selection(): void
    {
        $this->fixture['connection']->forceFill(['status' => 'inactive'])->save();
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_suspended_provider_rejects_selection(): void
    {
        $this->fixture['provider']->update(['status' => 'suspended']);
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_revoked_service_rejects_selection(): void
    {
        DB::table('network_provider_connection_services')->delete();
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_double_submit_creates_one_effective_contract(): void
    {
        $first = $this->select();
        $this->assertSame($first->id, $this->select()->id);
        $this->assertDatabaseCount('network_provider_contracts', 1);
    }

    public function test_competing_offers_for_same_quote_cannot_create_two_contracts(): void
    {
        $second = $this->issue();
        $this->select();
        try {
            app(ProviderContractSelectionService::class)->select($this->quote, $second['provider_snapshot_uuid']);
            $this->fail('A competing offer must not replace the selected contract.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('network_provider_contracts', 1);
        }
    }

    public function test_unique_constraint_blocks_a_competing_contract_write(): void
    {
        $second = ProviderQuoteSnapshot::where('uuid', $this->issue()['provider_snapshot_uuid'])->firstOrFail();
        $this->select();
        $this->expectException(QueryException::class);
        ProviderContract::forceCreate(['quote_snapshot_id' => $second->id, 'b2c_cotizacion_id' => $this->quote->id, 'selected_at' => now()]);
    }

    public function test_snapshot_preserves_provider_net_cost(): void
    {
        $this->select();
        $this->assertSame('100.00', $this->offer->fresh()->provider_cost);
        $this->assertSame('100.00', $this->quote->fresh()->provider_base_price);
        $this->assertNull($this->quote->fresh()->provider_total); // No provider invoice/VAT was supplied.
    }

    public function test_snapshot_preserves_customer_net_and_gross_prices(): void
    {
        $this->select();
        $this->assertSame('125.00', $this->offer->subtotal_amount);
        $this->assertSame('145.00', $this->offer->total_amount);
        $this->assertSame('145.00', $this->quote->fresh()->zigo_final_price);
        $this->assertSame('25.00', $this->quote->fresh()->zigo_profit_amount);
    }

    public function test_snapshot_preserves_vat_and_public_commercial_breakdown(): void
    {
        $this->select();
        config(['zigo_commercial_pricing.vat_rate' => 0]);
        $metadata = app(ZigoProviderQuoteObservationService::class)->selectionMetadata($this->quote->fresh());
        $this->assertSame('0.1600', $this->offer->tax_rate);
        $this->assertSame('20.00', $this->offer->tax_amount);
        $this->assertSame(20.0, $metadata['vat']);
        $this->assertSame(145.0, $metadata['customer_total']);
    }

    public function test_snapshot_preserves_commercial_rule_reference_and_values(): void
    {
        $this->select();
        $this->assertSame($this->fixture['commercial']->id, $this->offer->commercial_rule_snapshot['id']);
        $this->assertSame('20.00', $this->offer->commercial_rule_snapshot['margin_percentage']);
    }

    public function test_later_tariff_change_does_not_reprice_the_selected_offer(): void
    {
        $this->fixture['tariff']->update(['amount' => 900]);
        $this->select();
        $this->assertSame('100.00', $this->offer->fresh()->provider_cost);
        $this->assertSame('100.00', $this->offer->tariff_snapshot['amount']);
    }

    public function test_later_margin_change_does_not_reprice_the_selected_offer(): void
    {
        $this->fixture['commercial']->update(['margin_percentage' => 90]);
        $this->select();
        $this->assertSame('145.00', $this->quote->fresh()->precio);
        $this->assertSame('20.00', $this->offer->commercial_rule_snapshot['margin_percentage']);
    }

    public function test_html_exposes_only_uuid_and_public_price_not_internal_identifiers_or_cost(): void
    {
        $html = view('index', ['cotizacion_publica' => $this->quote, 'cotizacion_id' => $this->quote->id, 'opciones' => [$this->option]])->render();
        $this->assertStringContainsString($this->offer->uuid, $html);
        $this->assertStringContainsString('$145.00', $html);
        foreach (['$100.00', '$25.00', 'provider_tenant_id', 'provider_connection_id', 'provider_cost', 'owner_binding'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
        $this->assertStringNotContainsString('Sólo cotización', $html);
    }

    public function test_selection_creates_no_physical_logistics_or_operations(): void
    {
        $this->select();
        foreach (['local_shipments', 'local_driver_assignments', 'local_tracking_events', 'network_tenant_operations', 'local_shipping_quote_snapshots'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($this->quote->fresh()->tracking_number);
        $this->assertNull($this->quote->fresh()->guia_id);
    }

    public function test_national_selection_still_uses_existing_names_and_pricing_pipeline(): void
    {
        $option = ['logistico' => 'Estafeta', 'servicio' => 'Terrestre', 'service_code' => 'terrestre',
            'provider_source' => 'xperta', 'carrier' => 'estafeta', 'base_price' => 116, 'provider_quote_id' => 'national-offer'];
        $this->mock(ZigoProviderRateService::class)->shouldReceive('getOptionsForCotizacion')->once()->andReturn([$option]);
        $pricing = ['base_price' => 116, 'final_price' => 200, 'profit_amount' => 84, 'margin_percentage' => 0,
            'fixed_fee' => 84, 'margin_amount' => 0, 'adjustment_type' => null, 'adjustment_value' => null,
            'adjustment_amount' => 0, 'discount_type' => null, 'discount_value' => null, 'discount_amount' => 0,
            'customer_segment' => 'anonymous', 'pricing_rule_id' => 77, 'adjustment_id' => null, 'client_pricing_rule_id' => null];
        $this->mock(ZigoCommercialQuoteService::class)->shouldReceive('calculate')->once()
            ->andReturn(['pricing' => $pricing]);
        $this->mock(ZigoProviderQuoteObservationService::class)->shouldReceive('attachSelectionMetadata')->once();
        $this->post(route('b2c.seleccionar', $this->quote), ['logistico' => 'Estafeta', 'servicio' => 'Terrestre'])
            ->assertRedirect(route('login'));
        $this->assertSame('xperta', $this->quote->fresh()->provider);
        $this->assertSame('200.00', $this->quote->fresh()->precio);
        $this->assertSame('national-offer', $this->quote->fresh()->provider_quote_reference);
        $this->assertDatabaseCount('network_provider_contracts', 0);
    }

    public function test_other_authenticated_user_cannot_select_quote_even_in_same_consumer(): void
    {
        $owner = User::forceCreate(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret']);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret']);
        $this->quote->update(['user_id' => $owner->id]);
        $this->actingAs($other);
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_nonexistent_uuid_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ProviderContractSelectionService::class)->select($this->quote, (string) Str::uuid());
    }

    public function test_contract_and_checkout_update_are_atomic(): void
    {
        B2cCotizacion::updating(fn () => throw new \RuntimeException('Simulated checkout persistence failure'));
        try {
            $this->select();
            $this->fail('The simulated failure must roll back the contract.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('network_provider_contracts', 0);
            $this->assertNull($this->quote->fresh()->provider);
            $this->assertDatabaseCount('network_provider_quote_snapshots', 1);
        }
    }

    public function test_guest_selection_continues_through_login_and_commercial_checkout_with_frozen_total(): void
    {
        $this->postSelection()->assertRedirect(route('login'));
        $user = User::forceCreate(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'secret']);
        $this->actingAs($user)->get(route('b2c.checkout', $this->quote))->assertOk()
            ->assertSee('$125.00')->assertSee('$20.00')->assertSee('$145.00')->assertDontSee('provider_cost');
        $this->assertSame($user->id, $this->quote->fresh()->user_id);
        $this->post(route('b2c.checkout.procesar', $this->quote), [
            'remitente_nombre' => 'Sender', 'remitente_telefono' => '5555555555', 'remitente_email' => 'sender@example.test',
            'remitente_direccion' => 'Origin Street', 'remitente_num_ext' => '1', 'ciudad_origen' => 'City', 'estado_origen' => 'State',
            'destinatario_nombre' => 'Receiver', 'destinatario_telefono' => '5555555556', 'destinatario_email' => 'receiver@example.test',
            'destinatario_direccion' => 'Destination Street', 'destinatario_num_ext' => '2', 'ciudad_destino' => 'City', 'estado_destino' => 'State',
            'contenido' => 'Goods', 'valor_declarado' => 0, 'requiere_seguro_envio' => false,
        ])->assertRedirect(route('b2c.confirmar', $this->quote));
        $this->assertSame('145.00', $this->quote->fresh()->precio);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_checkout_from_another_consumer_is_forbidden(): void
    {
        $this->select();
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']));
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->get(route('b2c.checkout', $this->quote))->assertForbidden();
    }

    public function test_offer_expiration_is_bounded_by_connection_validity(): void
    {
        $end = now()->addMinutes(5)->startOfSecond();
        $this->fixture['connection']->forceFill(['valid_to' => $end])->save();
        $offer = ProviderQuoteSnapshot::where('uuid', $this->issue()['provider_snapshot_uuid'])->firstOrFail();
        $this->assertTrue($offer->expires_at->equalTo($end));
    }

    public function test_unpublished_service_rejects_selection_without_changing_offer(): void
    {
        $this->fixture['service']->update(['published' => false]);
        try {
            $this->select();
            $this->fail('Unpublished service must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('145.00', $this->offer->fresh()->total_amount);
        }
    }

    public function test_sql_cannot_bind_contract_to_a_different_quote_than_its_offer(): void
    {
        $other = $this->quote->replicate();
        $other->save();
        $this->expectException(QueryException::class);
        ProviderContract::forceCreate(['quote_snapshot_id' => $this->offer->id, 'b2c_cotizacion_id' => $other->id, 'selected_at' => now()]);
    }

    public function test_selection_does_not_change_tenant_context(): void
    {
        $before = app(TenantContext::class)->current();
        $this->select();
        $this->assertSame($before, app(TenantContext::class)->current());
    }

    public function test_migration_rolls_back_with_selected_contract_and_reapplies(): void
    {
        $this->select();
        $migration = require database_path('migrations/2026_10_13_110000_create_network_provider_contract_snapshots.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('network_provider_contracts'));
        $this->assertFalse(Schema::hasTable('network_provider_quote_snapshots'));
        $this->assertDatabaseCount('network_tenants', 2);
        $this->assertDatabaseCount('local_shipping_services', 1);
        $this->assertDatabaseCount('b2c_cotizaciones', 1);
        $migration->up();
        $this->assertTrue(Schema::hasTable('network_provider_contracts'));
    }

    public function test_eloquent_cannot_rewrite_an_economic_snapshot(): void
    {
        $this->expectException(\LogicException::class);
        ProviderQuoteSnapshot::whereKey($this->offer->id)->update(['provider_cost' => 1]);
    }

    public function test_eloquent_cannot_delete_a_selected_contract(): void
    {
        $this->select();
        $this->expectException(\LogicException::class);
        ProviderContract::query()->delete();
    }

    public function test_quiet_save_cannot_change_even_an_expired_snapshot(): void
    {
        $this->travel(31)->minutes();
        $this->offer->forceFill(['provider_cost' => 1]);
        $this->expectException(\LogicException::class);
        $this->offer->saveQuietly();
    }

    public function test_quiet_delete_cannot_remove_a_selected_snapshot(): void
    {
        $this->select();
        $this->expectException(\LogicException::class);
        $this->offer->deleteQuietly();
    }

    public function test_quiet_delete_cannot_remove_a_contract(): void
    {
        $this->select();
        $this->expectException(\LogicException::class);
        ProviderContract::firstOrFail()->deleteQuietly();
    }

    public function test_expired_unselected_snapshot_can_be_cleaned_up(): void
    {
        $this->travel(31)->minutes();
        $this->offer->delete();
        $this->assertDatabaseCount('network_provider_quote_snapshots', 0);
    }

    public function test_migration_does_not_install_triggers(): void
    {
        $this->assertSame([], DB::select("SELECT name FROM sqlite_master WHERE type = 'trigger' AND name LIKE 'net_provider_%'"));
    }

    public function test_unselected_local_offer_does_not_block_existing_quote_deletion(): void
    {
        $this->quote->delete();
        $this->assertDatabaseCount('b2c_cotizaciones', 0);
        $this->assertDatabaseCount('network_provider_quote_snapshots', 0);
    }

    public function test_selected_quote_cannot_erase_durable_contract_history(): void
    {
        $this->select();
        $this->expectException(QueryException::class);
        $this->quote->delete();
    }

    public function test_another_session_cannot_replay_offer_for_same_consumer(): void
    {
        session()->forget('tenant_provider_quote_nonce');
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_offer_cannot_be_used_with_another_b2c_quote(): void
    {
        $other = $this->quote->replicate();
        $other->save();
        session()->put('cotizacion_publica_id', $other->id);
        $this->expectException(ValidationException::class);
        app(ProviderContractSelectionService::class)->select($other, $this->offer->uuid);
    }

    public function test_modified_package_requires_requote(): void
    {
        $this->quote->update(['peso' => 3]);
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_coverage_removed_after_quote_rejects_selection(): void
    {
        DB::table('local_shipping_zone_postal_codes')->delete();
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_quote_prices_are_immutable_after_local_selection(): void
    {
        $this->select();
        $this->expectException(ValidationException::class);
        $this->quote->fresh()->update(['precio' => 1]);
    }

    public function test_changed_service_pricing_strategy_requires_requote(): void
    {
        $this->fixture['service']->update(['pricing_strategy' => 'PACKAGE_FLAT']);
        $this->expectException(ValidationException::class);
        $this->select();
    }

    public function test_selected_option_display_never_requotes_national_or_local_sources(): void
    {
        $this->select();
        $this->fixture['tariff']->update(['amount' => 999]);
        $this->mock(ZigoProviderRateService::class)->shouldNotReceive('getOptionsForCotizacion');
        $options = app(CotizacionPublicaController::class)->getPublicOptionsForLanding($this->quote->fresh());
        $this->assertSame(145.0, $options[0]['commercial_price']);
        $this->assertDatabaseCount('network_provider_quote_snapshots', 1);
    }

    public function test_already_selected_contract_can_resume_checkout_after_offer_expiry_without_reselecting(): void
    {
        $this->select();
        $this->travel(31)->minutes();
        $option = app(ProviderQuotePublisher::class)->selectedOptions($this->quote->fresh())[0];
        $html = view('index', ['cotizacion_publica' => $this->quote->fresh(), 'cotizacion_id' => $this->quote->id, 'opciones' => [$option]])->render();
        $this->assertStringContainsString('landing-quote-continue', $html);
        $this->assertStringContainsString(route('b2c.checkout', $this->quote), $html);
        $this->assertSame(145.0, $option['commercial_price']);
        $this->assertDatabaseCount('network_provider_contracts', 1);
    }

    public function test_pending_checkout_id_alone_cannot_issue_an_unselected_offer(): void
    {
        session()->forget('cotizacion_publica_id');
        session()->put('b2c_pending_checkout_id', $this->quote->id);
        $options = app(TenantProviderQuoteSource::class)->options($this->quote, 'anonymous');
        $published = app(ProviderQuotePublisher::class)->publish($this->quote, $options);
        $this->assertArrayNotHasKey('provider_snapshot_uuid', $published[0]);
        $this->assertFalse($published[0]['selection_enabled']);
        $this->assertDatabaseCount('network_provider_quote_snapshots', 1);
    }

    public function test_local_quote_cannot_use_national_guide_endpoint(): void
    {
        $this->select();
        $controller = app(CotizacionPublicaController::class);
        $this->expectException(HttpException::class);
        app()->call([$controller, 'generarGuia'], ['cotizacion' => $this->quote->fresh()]);
    }

    private function issue(): array
    {
        $options = app(TenantProviderQuoteSource::class)->options($this->quote, 'anonymous');

        return app(ProviderQuotePublisher::class)->publish($this->quote, $options)[0];
    }

    private function select(): ProviderContract
    {
        return app(ProviderContractSelectionService::class)->select($this->quote, $this->offer->uuid);
    }

    private function postSelection(array $extra = []): TestResponse
    {
        // The request sets only the opaque identity. Price/provider/service are ignored.
        $this->app->instance(TenantContext::class, app(TenantContext::class));

        return $this->withSession(['cotizacion_publica_id' => $this->quote->id])->post(route('b2c.seleccionar', $this->quote),
            array_merge(['provider_snapshot_uuid' => $this->offer->uuid], $extra));
    }
}
