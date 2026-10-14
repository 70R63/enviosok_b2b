<?php

namespace Tests\Feature;

use App\Domain\ApiHub\Models\TenantApiClient;
use App\Domain\ApiHub\Models\TenantApiKey;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\ProviderApiOperations;
use App\Domain\Network\Providers\ProviderApiPrincipal;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderCrmOutbox;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\ProviderTrackingProjection;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Http\Middleware\AuthorizeProviderApi;
use App\Models\B2cCotizacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

final class NetworkProviderApiTest extends TestCase
{
    private array $f;

    private ProviderFulfillment $b;

    private ProviderIntegration $integration;

    private TenantApiClient $client;

    private TenantApiKey $key;

    private string $plain = 'testing-provider-secret-never-real';

    private ProviderApiPrincipal $p;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite required');
        }
        DB::statement('PRAGMA foreign_keys=ON');
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('local');
        config(['zigo_api_hub.enabled' => true, 'cache.default' => 'array']);
        ProviderApiFixture::schema();
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        ProviderTrackingFixture::grantDriver($this->f);
        DB::table('network_entitlements')->insert(['tenant_id' => $this->f['provider']->id, 'subscription_id' => DB::table('network_subscriptions')->value('id'), 'code' => 'API', 'is_enabled' => true]);
        $creator = User::create(['name' => 'Provisioning Admin', 'email' => 'creator@example.test', 'password' => 'unused']);
        $this->client = TenantApiClient::create(['tenant_id' => $this->f['provider']->id, 'name' => 'Generic CRM', 'environment' => 'SANDBOX', 'status' => 'ACTIVE', 'created_by_user_id' => $creator->id]);
        $this->key = TenantApiKey::create(['tenant_id' => $this->client->tenant_id, 'api_client_id' => $this->client->id, 'name' => 'Integration', 'key_prefix' => 'test', 'last_four' => 'real', 'key_hash' => hash('sha256', $this->plain),
            'scopes' => ['provider:fulfillment:read', 'provider:fulfillment:decide', 'provider:dispatch:write', 'provider:tracking:write', 'provider:pod:write']]);
        $this->integration = ProviderIntegration::forceCreate(['provider_tenant_id' => $this->client->tenant_id, 'api_client_id' => $this->client->id, 'provider_connection_id' => $this->f['connection']->id, 'status' => 'active', 'adapter' => 'disabled']);
        $this->p = new ProviderApiPrincipal($this->integration->id, $this->key->id);
        app(TenantContext::class)->set($this->f['consumer']);
        session()->put('cotizacion_publica_id', $this->f['quote']->id);
        $option = app(ProviderQuotePublisher::class)->publish($this->f['quote'], app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($this->f['quote'], $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $this->f['quote']->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
            'payment_verified_amount' => 145, 'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->f['quote']->id])->save());
        $this->b = app(ProviderFulfillmentService::class)->fulfill($this->f['quote']);
    }

    private function url(string $action = 'show'): string
    {
        return route('api.hub.v1.provider.'.$action, $action === 'index' ? [] : ['fulfillment' => $this->b->uuid]);
    }

    private function apiPost(string $action, array $data = [], string $key = 'same-key-123')
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->plain, 'Idempotency-Key' => $key, 'Accept' => 'application/json'])->postJson($this->url($action), $data);
    }

    private function command(string $action, array $data = [], string $key = 'domain-key-123', ?UploadedFile $photo = null, ?string $signature = null): array
    {
        return app(ProviderApiOperations::class)->command($this->p, $this->b->uuid, $action, $key, $data, $photo, $signature, 'request-123');
    }

    private function out(): void
    {
        $this->command('accept');
        $driver = ProviderTrackingFixture::driver($this->f['provider']);
        $this->command('dispatch', ['driver_uuid' => $driver->uuid]);
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            $this->command('tracking', ['status' => $status], 'track-'.$status);
        }
    }

    public function test_bearer_auth_read_response_no_internal_ids_or_economy(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url());
        $response->assertOk()->assertJsonPath('data.fulfillment_uuid', $this->b->uuid);
        foreach (['provider_cost', 'profit_amount', 'tenant_id', 'connection_id', 'pricing_rule', 'api_client_id', $this->plain] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertSame($this->f['consumer']->id, app(TenantContext::class)->id());
    }

    public static function invalidCredentials(): array
    {
        return [['revoked'], ['expired'], ['client'], ['integration'], ['connection']];
    }

    #[DataProvider('invalidCredentials')]
    public function test_revoked_expired_inactive_credential_or_authorization_denied(string $kind): void
    {
        match ($kind) {
            'revoked' => $this->key->update(['revoked_at' => now()]),'expired' => $this->key->update(['expires_at' => now()->subHour()]),'client' => $this->client->update(['status' => 'SUSPENDED']),
            'integration' => $this->integration->forceFill(['status' => 'inactive'])->save(),'connection' => $this->f['connection']->forceFill(['status' => 'inactive'])->save()
        };
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url())->assertStatus(in_array($kind, ['revoked', 'expired', 'client']) ? 401 : 403);
    }

    public function test_invalid_bearer_denied(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong')->getJson($this->url())->assertUnauthorized();
    }

    public function test_expired_integration_denies_authenticated_client(): void
    {
        $this->integration->forceFill(['valid_to' => now()->subMinute()])->save();
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url())->assertForbidden();
    }

    public function test_nonlocal_api_requires_https_without_changing_environment_configuration(): void
    {
        $original = app('env');
        app()->instance('env', 'production');
        try {
            $response = app(AuthorizeProviderApi::class)->handle(Request::create('http://example.test/provider'), fn () => response('unexpected'));
            $this->assertSame(426, $response->getStatusCode());
            $this->assertStringContainsString('TLS_REQUIRED', $response->getContent());
        } finally {
            app()->instance('env', $original);
        }
    }

    public function test_read_scope_does_not_allow_mutation(): void
    {
        $this->key->update(['scopes' => ['provider:fulfillment:read']]);
        $this->apiPost('accept')->assertForbidden();
        $this->assertDatabaseCount('network_provider_fulfillment_decisions', 0);
    }

    public function test_missing_scope_denies_read(): void
    {
        $this->key->update(['scopes' => []]);
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url())->assertForbidden();
    }

    public function test_other_connection_fulfillment_denied(): void
    {
        $consumer = Tenant::create(['name' => 'Other Consumer', 'slug' => 'other-consumer', 'status' => 'active']);
        $connection = ProviderConnection::forceCreate(['consumer_tenant_id' => $consumer->id, 'provider_tenant_id' => $this->f['provider']->id, 'capability' => 'LOCAL_SHIPPING', 'status' => 'active']);
        $client = $this->newClient();
        ProviderIntegration::forceCreate(['provider_tenant_id' => $client->tenant_id, 'api_client_id' => $client->id, 'provider_connection_id' => $connection->id, 'status' => 'active']);
        TenantApiKey::create(['tenant_id' => $client->tenant_id, 'api_client_id' => $client->id, 'name' => 'Other', 'key_prefix' => 'test', 'last_four' => 'ther', 'key_hash' => hash('sha256', 'other-provider-secret'), 'scopes' => ['provider:fulfillment:read']]);
        $this->withHeader('Authorization', 'Bearer other-provider-secret')->getJson($this->url())->assertNotFound();
    }

    private function newClient(): TenantApiClient
    {
        return TenantApiClient::create(['tenant_id' => $this->client->tenant_id, 'name' => 'Second', 'environment' => 'SANDBOX', 'status' => 'ACTIVE', 'created_by_user_id' => $this->client->created_by_user_id]);
    }

    public static function decisions(): array
    {
        return [['accept', 'ACCEPTED'], ['reject', 'REJECTED']];
    }

    #[DataProvider('decisions')]
    public function test_api_decisions_reuse_domain_idempotent_and_audit_nonhuman(string $action, string $status): void
    {
        $this->apiPost($action)->assertOk()->assertJsonPath('data.acceptance', $status);
        $this->apiPost($action)->assertOk()->assertHeader('X-ZIGO-Idempotent-Replay', 'true');
        $record = DB::table('network_provider_fulfillment_decisions')->first();
        $this->assertNull($record->actor_user_id);
        $this->assertSame($this->client->id, $record->actor_api_client_id);
        $this->assertSame('CRM_API', $record->channel);
        $this->assertDatabaseCount('network_provider_fulfillment_decisions', 1);
        $this->assertDatabaseCount('tenant_api_audit_events', 1);
    }

    public function test_same_key_different_payload_conflicts(): void
    {
        $this->apiPost('reject', ['reason' => 'One'])->assertOk();
        $this->apiPost('reject', ['reason' => 'Two'])->assertConflict();
    }

    public function test_missing_idempotency_key_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->postJson($this->url('accept'))->assertUnprocessable();
    }

    public function test_manipulated_tenant_and_price_rejected(): void
    {
        $this->apiPost('accept', ['provider_tenant_id' => 999, 'price' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('network_provider_fulfillment_decisions', 0);
    }

    public function test_invalid_transition_rejected(): void
    {
        $this->command('accept');
        $driver = ProviderTrackingFixture::driver($this->f['provider']);
        $this->command('dispatch', ['driver_uuid' => $driver->uuid]);
        $this->apiPost('tracking', ['status' => 'DELIVERED'])->assertUnprocessable();
        $this->apiPost('tracking', ['status' => 'OUT_FOR_DELIVERY'], 'other-key-123')->assertUnprocessable();
        $this->assertDatabaseCount('local_tracking_events', 2);
    }

    public function test_tracking_uses_existing_engine_no_duplicate_and_actor_api(): void
    {
        $this->out();
        $this->apiPost('tracking', ['status' => 'OUT_FOR_DELIVERY'], 'repeat-key-123')->assertOk();
        $this->apiPost('tracking', ['status' => 'OUT_FOR_DELIVERY'], 'repeat-key-123')->assertOk();
        $this->assertDatabaseCount('local_tracking_events', 5);
        $e = DB::table('local_tracking_events')->where('status', 'PICKED_UP')->first();
        $this->assertNull($e->created_by_user_id);
        $this->assertStringContainsString('CRM_API', $e->metadata);
        $projection = app(ProviderTrackingProjection::class)->project($this->b->fresh(), $this->f['consumer']);
        $this->assertSame('En reparto', $projection['status_label']);
    }

    public function test_failed_attempt_idempotent_and_not_cancellation(): void
    {
        $this->out();
        $this->apiPost('failed', ['reason' => 'NO_ANSWER'])->assertOk();
        $this->apiPost('failed', ['reason' => 'NO_ANSWER'])->assertOk();
        $this->assertDatabaseCount('local_delivery_failed_attempts', 1);
        $this->assertSame('DELIVERY_FAILED', $this->b->shipment->fresh()->status);
        $this->assertSame('145.00', $this->f['quote']->fresh()->precio);
    }

    public function test_pod_via_domain_idempotent_and_economy_frozen(): void
    {
        $this->out();
        $data = ProviderTrackingFixture::proofData();
        $photo = UploadedFile::fake()->image('proof.jpg');
        $sig = ProviderTrackingFixture::signature();
        $one = $this->command('pod', $data, 'pod-key-123', $photo, $sig);
        $two = $this->command('pod', $data, 'pod-key-123', $photo, $sig);
        $this->assertSame($one['body'], $two['body']);
        $this->assertDatabaseCount('local_delivery_proofs', 1);
        $this->assertDatabaseCount('local_tracking_events', 6);
        $this->assertSame('145.00', $this->f['quote']->fresh()->precio);
        $this->assertSame('Entregado', app(ProviderTrackingProjection::class)->project($this->b->fresh(), $this->f['consumer'])['status_label']);
    }

    public function test_outbox_after_commit_idempotent_and_no_http(): void
    {
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
        app(ProviderCrmOutbox::class)->publish($this->b);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
        Http::assertNothingSent();
        DB::beginTransaction();
        try {
            app(ProviderCrmOutbox::class)->publish($this->b);
            $this->fail('Committed only');
        } catch (\LogicException) {
        } finally {
            DB::rollBack();
        }
    }

    public function test_disabled_adapter_does_not_attempt_http_or_fail_fulfillment(): void
    {
        $id = DB::table('tenant_api_webhook_deliveries')->value('id');
        app(ProviderCrmOutbox::class)->deliver($id);
        $this->assertSame('PR_DISABLED', DB::table('tenant_api_webhook_deliveries')->value('status'));
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        Http::assertNothingSent();
    }

    private function fake(): FakeProviderCrmAdapter
    {
        $fake = new FakeProviderCrmAdapter;
        $this->app->instance(FakeProviderCrmAdapter::class, $fake);
        config(['provider_crm.adapters.fake' => FakeProviderCrmAdapter::class]);
        $this->integration->forceFill(['adapter' => 'fake'])->save();

        return $fake;
    }

    public function test_retry_recovers_and_errors_sanitized(): void
    {
        $fake = $this->fake();
        $fake->failures = 1;
        $id = DB::table('tenant_api_webhook_deliveries')->value('id');
        $outbox = app(ProviderCrmOutbox::class);
        $outbox->deliver($id);
        $row = DB::table('tenant_api_webhook_deliveries')->first();
        $this->assertSame('PR_RETRY', $row->status);
        $this->assertStringNotContainsString('SECRET', $row->last_error_safe);
        $this->travel(3)->minutes();
        $outbox->deliver($id);
        $this->assertSame('PR_DELIVERED', DB::table('tenant_api_webhook_deliveries')->value('status'));
        $this->assertFalse($fake->insideTransaction);
        $this->assertDatabaseCount('network_provider_external_references', 1);
    }

    public function test_max_retry_requires_attention(): void
    {
        $fake = $this->fake();
        $fake->failures = 10;
        config(['provider_crm.max_attempts' => 2]);
        $id = DB::table('tenant_api_webhook_deliveries')->value('id');
        $outbox = app(ProviderCrmOutbox::class);
        $outbox->deliver($id);
        $this->travel(3)->minutes();
        $outbox->deliver($id);
        $outbox->deliver($id);
        $this->assertSame(2, $fake->calls);
        $this->assertSame('PR_ATTENTION', DB::table('tenant_api_webhook_deliveries')->value('status'));
    }

    public function test_external_reference_immutable_and_scoped(): void
    {
        $this->apiPost('accept', ['external_reference' => 'crm-123'])->assertOk();
        $this->apiPost('accept', ['external_reference' => 'different'], 'second-key-123')->assertConflict();
        $this->assertDatabaseCount('network_provider_external_references', 1);
    }

    public function test_provider_rate_limit_reused(): void
    {
        config(['zigo_api_hub.default_rate_limit' => 1]);
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url())->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url())->assertStatus(429);
    }

    public function test_no_cancel_refund_or_delete_routes(): void
    {
        foreach (app('router')->getRoutes() as $r) {
            if (str_starts_with((string) $r->getName(), 'api.hub.v1.provider.')) {
                $this->assertStringNotContainsString('cancel', $r->uri());
                $this->assertStringNotContainsString('refund', $r->uri());
                $this->assertNotContains('DELETE', $r->methods());
            }
        }
    }

    public function test_rollback_refuses_destroying_api_actor_history_before_any_ddl(): void
    {
        $this->command('accept');
        $m = require database_path('migrations/2026_10_13_140000_add_provider_api_boundary.php');
        try {
            $m->down();
            $this->fail('Protect audit');
        } catch (\RuntimeException) {
        }$this->assertDatabaseCount('network_provider_fulfillment_decisions', 1);
        $this->assertTrue(Schema::hasColumn('network_provider_fulfillment_decisions', 'actor_api_client_id'));
    }

    public function test_empty_module_rollback_and_reapply_preserve_fulfillment(): void
    {
        DB::table('tenant_api_webhook_deliveries')->delete();
        $m = require database_path('migrations/2026_10_13_140000_add_provider_api_boundary.php');
        $m->down();
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $m->up();
        $this->assertTrue(Schema::hasTable('network_provider_integrations'));
    }

    public function test_unknown_uuid_not_enumerable(): void
    {
        $url = str_replace($this->b->uuid, (string) Str::uuid(), $this->url());
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($url)->assertNotFound();
    }

    public function test_listing_scoped_to_connection(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->plain)->getJson($this->url('index'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_request_size_limit_present(): void
    {
        $r = Request::create('/', 'POST', [], [], [], ['CONTENT_LENGTH' => 8388609]);
        $response = app(AuthorizeProviderApi::class)->handle($r, fn () => response('not-called'));
        $this->assertSame(413, $response->getStatusCode());
    }

    public function test_consumer_client_cannot_bind_provider_connection(): void
    {
        $client = TenantApiClient::create(['tenant_id' => $this->f['consumer']->id, 'name' => 'Consumer', 'environment' => 'SANDBOX', 'status' => 'ACTIVE', 'created_by_user_id' => $this->client->created_by_user_id]);
        $this->expectException(QueryException::class);
        ProviderIntegration::forceCreate(['provider_tenant_id' => $this->f['consumer']->id, 'api_client_id' => $client->id, 'provider_connection_id' => $this->f['connection']->id, 'status' => 'active']);
    }

    public function test_other_provider_valid_key_cannot_access_fulfillment(): void
    {
        $other = Tenant::create(['name' => 'Other Provider', 'slug' => 'other-provider', 'status' => 'active']);
        $sub = (array) DB::table('network_subscriptions')->first();
        unset($sub['id']);
        $sub['tenant_id'] = $other->id;
        $sub['uuid'] = (string) Str::uuid();
        $subId = DB::table('network_subscriptions')->insertGetId($sub);
        DB::table('network_entitlements')->insert(['tenant_id' => $other->id, 'subscription_id' => $subId, 'code' => 'API', 'is_enabled' => true]);
        $conn = ProviderConnection::forceCreate(['consumer_tenant_id' => $this->f['consumer']->id, 'provider_tenant_id' => $other->id, 'capability' => 'LOCAL_SHIPPING', 'status' => 'active']);
        $client = TenantApiClient::create(['tenant_id' => $other->id, 'name' => 'Other CRM', 'environment' => 'SANDBOX', 'status' => 'ACTIVE', 'created_by_user_id' => $this->client->created_by_user_id]);
        ProviderIntegration::forceCreate(['provider_tenant_id' => $other->id, 'api_client_id' => $client->id, 'provider_connection_id' => $conn->id, 'status' => 'active']);
        TenantApiKey::create(['tenant_id' => $other->id, 'api_client_id' => $client->id, 'name' => 'Other', 'key_prefix' => 'test', 'last_four' => 'ther', 'key_hash' => hash('sha256', 'foreign-provider-key'), 'scopes' => ['provider:fulfillment:read']]);
        $this->withHeader('Authorization', 'Bearer foreign-provider-key')->getJson($this->url())->assertNotFound();
    }

    public function test_pod_multipart_http_reuses_domain_and_replays(): void
    {
        $this->out();
        $data = ProviderTrackingFixture::proofData() + ['photo' => UploadedFile::fake()->image('proof.jpg'), 'signature' => 'data:image/png;base64,'.base64_encode(ProviderTrackingFixture::signature())];
        $this->withHeaders(['Authorization' => 'Bearer '.$this->plain, 'Idempotency-Key' => 'pod-http-123', 'Accept' => 'application/json'])->post($this->url('pod'), $data)->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->plain, 'Idempotency-Key' => 'pod-http-123', 'Accept' => 'application/json'])->post($this->url('pod'), $data)->assertOk()->assertHeader('X-ZIGO-Idempotent-Replay', 'true');
        $this->assertDatabaseCount('local_delivery_proofs', 1);
    }

    public function test_disabled_or_revoked_connection_does_not_send_outbound(): void
    {
        $fake = $this->fake();
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        app(ProviderCrmOutbox::class)->deliver(DB::table('tenant_api_webhook_deliveries')->value('id'));
        $this->assertSame(0, $fake->calls);
    }

    public function test_outbox_crash_lease_recovery(): void
    {
        $fake = $this->fake();
        $id = DB::table('tenant_api_webhook_deliveries')->value('id');
        DB::table('tenant_api_webhook_deliveries')->where('id', $id)->update(['status' => 'PR_SENDING', 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->subMinute()]);
        app(ProviderCrmOutbox::class)->deliver($id);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('PR_DELIVERED', DB::table('tenant_api_webhook_deliveries')->value('status'));
    }

    public function test_reconcile_missing_event_after_crash(): void
    {
        DB::table('tenant_api_webhook_deliveries')->delete();
        $this->artisan('network:provider-crm:deliver')->assertSuccessful();
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
    }

    public function test_opposite_decision_conflict_and_no_snapshot_change(): void
    {
        $quote = $this->f['quote']->fresh();
        $before = $quote->providerContract->snapshot->getAttributes();
        $this->apiPost('accept')->assertOk();
        $this->apiPost('reject', [], 'different-key-123')->assertUnprocessable();
        $this->assertSame($before, $quote->providerContract->snapshot->fresh()->getAttributes());
    }

    public function test_actual_business_rollback_does_not_publish_uncommitted_operation(): void
    {
        DB::beginTransaction();
        try {
            $this->secondFulfillment();
            $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
    }

    public function test_second_business_commit_publishes_after_transaction(): void
    {
        DB::beginTransaction();
        $this->secondFulfillment();
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 1);
        DB::commit();
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 2);
    }

    private function secondFulfillment(): ProviderFulfillment
    {
        $q = $this->f['quote']->fresh()->replicate();
        $q->forceFill(['estatus' => 'COTIZADA', 'logistico' => null, 'provider' => null, 'payment_status' => null, 'payment_verification_status' => null, 'payment_verified_at' => null,
            'guia_id' => null, 'tracking_number' => null, 'guia_estatus' => null, 'guia_generated_at' => null, 'provider_quote_reference' => null]);
        B2cCotizacion::withoutEvents(fn () => $q->save());
        session()->put('cotizacion_publica_id',$q->id);
        $option = app(ProviderQuotePublisher::class)->publish($q,app(TenantProviderQuoteSource::class)->options($q,'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($q,$option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $q->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
            'payment_verified_external_reference' => 'B2C-'.$q->id])->save());

        return app(ProviderFulfillmentService::class)->fulfill($q);
    }
}
