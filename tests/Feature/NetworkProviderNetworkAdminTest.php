<?php

namespace Tests\Feature;

use App\Domain\ApiHub\Models\TenantApiClient;
use App\Domain\ApiHub\Models\TenantApiKey;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderNetworkReadinessService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Models\B2cCotizacion;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeProviderCrmAdapter;
use Tests\Support\ProviderApiFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\Support\ProviderTrackingFixture;
use Tests\TestCase;

final class NetworkProviderNetworkAdminTest extends TestCase
{
    private array $f;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated test database required');
        }
        DB::statement('PRAGMA foreign_keys=ON');
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('local');
        ProviderApiFixture::schema();
        Schema::create('network_admin_audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_user_id');
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->json('before_json')->nullable();
            $t->json('after_json')->nullable();
            $t->timestamp('created_at');
        });
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        ProviderTrackingFixture::grantDriver($this->f);
        $this->admin = User::create(['name' => 'Network Admin', 'email' => 'network@example.test', 'password' => 'unused']);
        $role = Roles::create(['slug' => 'sysadmin']);
        $this->admin->roles()->attach($role->id);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $this->admin->id, 'role' => 'operator', 'status' => 'active']);
        app(TenantContext::class)->set($this->f['consumer']);
    }

    private function input(string $status = 'active'): array
    {
        return ['consumer_uuid' => $this->f['consumer']->uuid, 'provider_uuid' => $this->f['provider']->uuid,
            'capability' => 'LOCAL_SHIPPING', 'status' => $status, 'services' => [$this->f['service']->id]];
    }

    private function report(bool $activation = false): array
    {
        return app(ProviderNetworkReadinessService::class)->evaluate($this->f['consumer']->fresh(), $this->f['provider']->fresh(), $this->f['connection']->fresh(), $activation);
    }

    private function check(string $code): string
    {
        return collect($this->report()['checks'])->firstWhere('code', $code)['status'];
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin)->withSession(['network.2fa_user_id' => $this->admin->id]);
    }

    public function test_network_admin_lists_and_configures_connections(): void
    {
        $this->asAdmin()->get('/network/provider-connections')->assertOk()->assertSee('Provider Express');
        $this->get('/network/provider-connections/'.$this->f['connection']->id.'/edit')->assertOk()->assertSee('Readiness')->assertSee('INTERNAL / TENANT_UI');
    }

    public function test_provider_admin_cannot_administer_network_connections(): void
    {
        $user = User::create(['name' => 'Tenant Admin', 'email' => 'tenant@example.test', 'password' => 'unused']);
        $role = Roles::create(['slug' => 'admin']);
        $user->roles()->attach($role->id);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->withSession(['network.2fa_user_id' => $user->id])->get('/network/provider-connections')->assertForbidden();
        $this->post('/network/provider-connections/'.$this->f['connection']->id, $this->input())->assertForbidden();
    }

    public function test_guest_and_missing_two_factor_cannot_manage(): void
    {
        $this->get('/network/provider-connections')->assertRedirect('/network/login');
        $this->actingAs($this->admin)->withSession(['network.2fa_user_id' => 0])->get('/network/provider-connections')->assertRedirect('/network/login');
    }

    public function test_same_consumer_provider_is_rejected(): void
    {
        $input = $this->input();
        $input['provider_uuid'] = $input['consumer_uuid'];
        $this->asAdmin()->post('/network/provider-connections', $input)->assertSessionHasErrors('provider_uuid');
        $this->assertDatabaseCount('network_provider_connections', 1);
    }

    public function test_provider_catalog_excludes_other_tenants(): void
    {
        $other = $this->f['service']->replicate();
        $other->tenant_id = $this->f['consumer']->id;
        $other->code = 'FOREIGN';
        $other->name = 'Foreign service';
        $other->save();
        $this->asAdmin()->get('/network/provider-connections/create?consumer_uuid='.$this->f['consumer']->uuid.'&provider_uuid='.$this->f['provider']->uuid)
            ->assertOk()->assertSee('Envío local')->assertDontSee('Foreign service');
        $input = $this->input();
        $input['services'] = [$other->id];
        $this->post('/network/provider-connections/'.$this->f['connection']->id, $input)->assertSessionHasErrors('services');
        $this->assertDatabaseHas('network_provider_connection_services', ['local_shipping_service_id' => $this->f['service']->id]);
    }

    public function test_inactive_connection_can_save_without_services_and_audits_actor(): void
    {
        $input = $this->input('inactive');
        $input['services'] = [];
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $input)->assertRedirect();
        $this->assertDatabaseHas('network_provider_connections', ['status' => 'inactive']);
        $this->assertDatabaseCount('network_provider_connection_services', 0);
        $this->assertDatabaseHas('network_admin_audit_events', ['actor_user_id' => $this->admin->id, 'action' => 'provider_connection.configured']);
        $this->assertNotNull(DB::table('network_admin_audit_events')->value('created_at'));
    }

    public function test_activation_with_critical_fail_rolls_back_configuration(): void
    {
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->f['commercial']->update(['active' => false]);
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $this->input())->assertSessionHasErrors('status');
        $this->assertSame('inactive', $this->f['connection']->fresh()->status);
        $this->assertDatabaseCount('network_admin_audit_events', 0);
    }

    public function test_valid_internal_configuration_can_activate_without_integration(): void
    {
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->assertTrue($this->report(true)['ready']);
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $this->input())->assertRedirect();
        $this->assertSame('active', $this->f['connection']->fresh()->status);
        $this->assertDatabaseCount('network_provider_integrations', 0);
        $this->assertSame($this->f['consumer']->id, app(TenantContext::class)->id());
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public static function criticalFailures(): array
    {
        return [['consumer', 'consumer.active'], ['provider', 'provider.active'], ['expired', 'connection.validity'],
            ['future', 'connection.validity'], ['grant', 'services.authorized'], ['published', 'services.published'],
            ['service', 'services.active'], ['service-expired', 'services.validity'], ['coverage', 'coverage.services'],
            ['rate', 'rate.1.caja'], ['commercial', 'commercial.1.caja'], ['package', 'package.1'], ['member', 'operation.members'],
            ['subscription', 'operation.authority'], ['capability', 'connection.capability']];
    }

    #[DataProvider('criticalFailures')]
    public function test_readiness_detects_critical_configuration_failures(string $change, string $code): void
    {
        match ($change) {
            'consumer' => $this->f['consumer']->update(['status' => 'inactive']),
            'provider' => $this->f['provider']->update(['status' => 'suspended']),
            'expired' => $this->f['connection']->forceFill(['valid_to' => now()->subMinute()])->save(),
            'future' => $this->f['connection']->forceFill(['valid_from' => now()->addDay()])->save(),
            'grant' => DB::table('network_provider_connection_services')->delete(),
            'published' => $this->f['service']->update(['published' => false]),
            'service' => $this->f['service']->update(['status' => 'inactive']),
            'service-expired' => $this->f['service']->update(['valid_to' => now()->subMinute()]),
            'coverage' => LocalShippingZonePostalCode::query()->update(['active' => false]),
            'rate' => $this->f['tariff']->update(['active' => false]),
            'commercial' => $this->f['commercial']->update(['active' => false]),
            'package' => DB::table('local_shipping_package_rules')->update(['active' => false]),
            'member' => TenantMembership::query()->update(['status' => 'inactive']),
            'subscription' => DB::table('network_subscriptions')->update(['status' => 'suspended']),
            'capability' => $this->wrongCapability(),
        };
        $this->assertFalse($this->report()['ready']);
        $this->assertSame('FAIL', $this->check($code));
    }

    public function test_valid_readiness_reports_separate_provider_and_customer_pricing(): void
    {
        $this->assertTrue($this->report()['ready']);
        foreach (['consumer.active', 'provider.active', 'coverage.zones', 'coverage.postal_codes', 'coverage.services', 'rate.1.caja', 'commercial.1.caja', 'operation.members', 'fulfillment.schema', 'scheduler.application', 'crm.mode'] as $code) {
            $this->assertSame('PASS', $this->check($code));
        }
        $this->assertSame('WARN', $this->check('scheduler.host'));
        $this->assertSame('WARN', $this->check('dispatch.drivers'));
    }

    public function test_foreign_postal_codes_do_not_count(): void
    {
        LocalShippingZonePostalCode::query()->update(['tenant_id' => $this->f['consumer']->id]);
        $this->assertSame('FAIL', $this->check('coverage.postal_codes'));
        $this->assertSame('FAIL', $this->check('coverage.services'));
    }

    public function test_foreign_provider_tariff_does_not_count(): void
    {
        $this->f['tariff']->update(['tenant_id' => $this->f['consumer']->id]);
        $this->assertSame('FAIL', $this->check('rate.1.caja'));
    }

    public function test_estafeta_commercial_rule_does_not_count(): void
    {
        $this->f['commercial']->update(['carrier' => 'ESTAFETA']);
        $this->assertSame('PASS', $this->check('rate.1.caja'));
        $this->assertSame('FAIL', $this->check('commercial.1.caja'));
    }

    public function test_expired_cp_and_wrong_service_zones_do_not_count(): void
    {
        LocalShippingZonePostalCode::query()->update(['valid_to' => now()->subMinute()]);
        $this->assertSame('FAIL', $this->check('coverage.services'));
    }

    public function test_service_zone_of_another_provider_is_not_coverage(): void
    {
        DB::table('local_shipping_zones')->where('id', $this->f['service']->origin_zone_id)->update(['tenant_id' => $this->f['consumer']->id]);
        $this->assertSame('FAIL', $this->check('coverage.services'));
    }

    public function test_incomplete_fulfillment_columns_are_a_critical_fail(): void
    {
        Schema::table('b2c_cotizaciones', fn (Blueprint $t) => $t->dropColumn('provider_fulfillment_attempted_at'));
        $this->assertSame('FAIL', $this->check('fulfillment.schema'));
        $this->assertFalse($this->report()['ready']);
    }

    public function test_command_is_read_only_succeeds_without_secrets(): void
    {
        $before = $this->f['connection']->fresh()->getAttributes();
        $this->artisan('network:provider-network:check', ['--consumer' => 'slug:consumer', '--provider' => 'uuid:'.$this->f['provider']->uuid])->assertSuccessful();
        $this->assertSame($before, $this->f['connection']->fresh()->getAttributes());
        $this->assertDatabaseCount('network_admin_audit_events', 0);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 0);
    }

    public function test_preflight_never_prints_integration_credentials_or_economy(): void
    {
        $integration = $this->integration();
        DB::table('tenant_api_keys')->insert(['uuid' => (string) Str::uuid(), 'tenant_id' => $this->f['provider']->id,
            'api_client_id' => $integration->api_client_id, 'name' => 'Secret credential label', 'key_prefix' => 'not-output', 'last_four' => '1234',
            'key_hash' => str_repeat('f', 64), 'scopes' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $result = Artisan::call('network:provider-network:check', ['--consumer' => 'slug:consumer', '--provider' => 'slug:provider']);
        $this->assertSame(0, $result);
        $output = Artisan::output();
        foreach (['Secret credential label', str_repeat('f', 64), 'key_hash', 'provider_cost', 'profit_amount', 'fixed_fee'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
    }

    public function test_new_inactive_relationship_can_be_created_incomplete_without_copying_catalog(): void
    {
        $consumer = Tenant::create(['name' => 'Second Consumer', 'slug' => 'second-consumer', 'status' => 'inactive']);
        $input = $this->input('inactive');
        $input['consumer_uuid'] = $consumer->uuid;
        $input['services'] = [];
        $this->asAdmin()->post('/network/provider-connections', $input)->assertRedirect();
        $this->assertDatabaseCount('network_provider_connections', 2);
        $this->assertDatabaseCount('local_shipping_services', 1);
        $this->assertDatabaseCount('local_shipping_zones', 2);
        $this->assertDatabaseCount('local_shipping_pricing_rules', 1);
    }

    public function test_duplicate_relationship_is_rejected_by_domain_validation(): void
    {
        $this->asAdmin()->post('/network/provider-connections', $this->input('inactive'))->assertSessionHasErrors('provider_uuid');
        $this->assertDatabaseCount('network_provider_connections', 1);
    }

    public function test_validity_end_without_start_is_supported_and_reversed_dates_rejected(): void
    {
        $input = $this->input('inactive') + ['valid_to' => now()->addDay()->toDateTimeString()];
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $input)->assertRedirect();
        $input['valid_from'] = now()->addDays(2)->toDateTimeString();
        $this->post('/network/provider-connections/'.$this->f['connection']->id, $input)->assertSessionHasErrors('valid_to');
    }

    public function test_unknown_tenant_uuid_cannot_create_a_relationship(): void
    {
        $input = $this->input('inactive');
        $input['provider_uuid'] = (string) Str::uuid();
        $this->asAdmin()->post('/network/provider-connections', $input)->assertNotFound();
        $this->assertDatabaseCount('network_provider_connections', 1);
    }

    public function test_command_fails_for_missing_tenant_or_bad_configuration(): void
    {
        $this->artisan('network:provider-network:check', ['--consumer' => 'slug:missing', '--provider' => 'slug:provider'])->assertFailed();
        $this->f['commercial']->update(['active' => false]);
        $this->artisan('network:provider-network:check', ['--consumer' => 'slug:consumer', '--provider' => 'slug:provider'])->assertFailed();
    }

    public function test_command_candidate_does_not_activate_inactive_connection(): void
    {
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $args = ['--consumer' => 'slug:consumer', '--provider' => 'slug:provider'];
        $this->artisan('network:provider-network:check', $args)->assertFailed();
        $this->artisan('network:provider-network:check', $args + ['--for-activation' => true])->assertSuccessful();
        $this->assertSame('inactive', $this->f['connection']->fresh()->status);
    }

    public function test_ambiguous_identifier_is_rejected_without_echoing_input(): void
    {
        $this->artisan('network:provider-network:check', ['--consumer' => 'secret-should-not-be-printed', '--provider' => 'slug:provider'])->expectsOutput('FAIL: Diagnóstico no disponible. Revisa esquema/configuración de aplicación; no se modificaron datos.')->assertFailed();
    }

    private function integration(): ProviderIntegration
    {
        $client = TenantApiClient::create(['tenant_id' => $this->f['provider']->id, 'name' => 'Internal placeholder', 'environment' => 'SANDBOX', 'status' => 'ACTIVE', 'created_by_user_id' => $this->admin->id]);

        return ProviderIntegration::forceCreate(['provider_tenant_id' => $this->f['provider']->id, 'api_client_id' => $client->id, 'provider_connection_id' => $this->f['connection']->id, 'status' => 'active', 'adapter' => 'disabled']);
    }

    public function test_disabled_crm_allows_activation_and_has_no_failed_retry_loop(): void
    {
        $this->integration();
        $this->assertTrue($this->report()['ready']);
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $this->input())->assertRedirect();
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('network:provider-crm:deliver')->assertSuccessful();
        }
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 0);
    }

    public function test_disabled_event_is_quiet_durable_and_does_not_retry_or_consume_attempts(): void
    {
        $this->integration();
        $this->fulfill();
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('network:provider-crm:deliver')->assertSuccessful();
        }
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
        $this->assertDatabaseHas('tenant_api_webhook_deliveries', ['status' => 'PR_DISABLED', 'attempt' => 0, 'last_error_safe' => null, 'next_retry_at' => null]);
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
    }

    public function test_internal_fulfillment_without_integration_never_creates_crm_outbox(): void
    {
        $this->fulfill();
        $this->artisan('network:provider-crm:deliver')->assertSuccessful();
        $this->assertDatabaseCount('network_provider_integrations', 0);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 0);
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    private function fulfill(): void
    {
        session()->put('cotizacion_publica_id', $this->f['quote']->id);
        $option = app(ProviderQuotePublisher::class)->publish($this->f['quote'],
            app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($this->f['quote'], $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $this->f['quote']->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->f['quote']->id])->save());
        app(ProviderFulfillmentService::class)->fulfill($this->f['quote']);
    }

    public function test_enabled_external_mode_requires_api_authority_and_valid_read_credential(): void
    {
        $integration = $this->integration();
        config(['provider_crm.adapters.fake' => FakeProviderCrmAdapter::class]);
        $integration->forceFill(['adapter' => 'fake'])->save();
        $this->assertSame('FAIL', $this->check('crm.external.'.$integration->id));
        DB::table('network_entitlements')->insert(['tenant_id' => $this->f['provider']->id, 'subscription_id' => DB::table('network_subscriptions')->value('id'), 'code' => 'API', 'is_enabled' => true]);
        $key = TenantApiKey::create(['tenant_id' => $this->f['provider']->id, 'api_client_id' => $integration->api_client_id,
            'name' => 'Non-disclosed key', 'key_prefix' => 'test', 'last_four' => 'test', 'key_hash' => hash('sha256', 'test-only-not-real'), 'scopes' => ['provider:fulfillment:read']]);
        $this->assertSame('PASS', $this->check('crm.external.'.$integration->id));
        $key->update(['revoked_at' => now()]);
        $this->assertSame('FAIL', $this->check('crm.external.'.$integration->id));
    }

    private function wrongCapability(): void
    {
        DB::table('network_provider_connection_services')->delete();
        $this->f['connection']->forceFill(['capability' => 'OTHER_CAPABILITY'])->save();
    }

    public function test_unknown_external_adapter_is_critical_fail_not_internal_mode(): void
    {
        $i = $this->integration();
        $i->forceFill(['adapter' => 'unregistered'])->save();
        $this->assertSame('FAIL', $this->check('crm.external.'.$i->id));
    }

    public function test_existing_connection_identity_cannot_change(): void
    {
        $tenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $input = $this->input('inactive');
        $input['consumer_uuid'] = $tenant->uuid;
        $this->asAdmin()->post('/network/provider-connections/'.$this->f['connection']->id, $input)->assertSessionHasErrors('provider_uuid');
        $this->assertSame($this->f['consumer']->id, $this->f['connection']->fresh()->consumer_tenant_id);
    }

    public function test_routes_have_network_security_and_no_cancel_or_refund_actions(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'network.provider-connections.'));
        $this->assertCount(5, $routes);
        foreach ($routes as $route) {
            foreach (['web', 'network.auth', 'network.superadmin', 'network.two-factor'] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
            $this->assertDoesNotMatchRegularExpression('/cancel|refund|delete/', $route->uri());
        }
    }
}
