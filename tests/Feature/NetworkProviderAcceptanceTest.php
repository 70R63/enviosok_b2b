<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderFulfillmentDecision;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderDispatchEligibility;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Http\Controllers\B2C\ProviderFulfillmentController;
use App\Http\Controllers\Tenant\ProviderFulfillmentInboxController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderAcceptanceFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

final class NetworkProviderAcceptanceTest extends TestCase
{
    private array $f;

    private ProviderFulfillment $bridge;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite required.');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        Http::preventStrayRequests();
        Mail::fake();
        view()->share('errors', new ViewErrorBag);
        ProviderAcceptanceFixture::schema();
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        $quote = $this->f['quote'];
        app(TenantContext::class)->set($this->f['consumer']);
        session()->put('cotizacion_publica_id', $quote->id);
        $option = app(ProviderQuotePublisher::class)->publish($quote, app(TenantProviderQuoteSource::class)->options($quote, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($quote, $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $quote->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$quote->id])->save());
        $this->bridge = app(ProviderFulfillmentService::class)->fulfill($quote);
        $this->actor = User::create(['name' => 'Provider Operator', 'email' => 'operator@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $this->actor->id, 'role' => 'operator', 'status' => 'active']);
    }

    private function decide(string $status = 'ACCEPTED', ?string $reason = null): ProviderFulfillmentDecision
    {
        return app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->actor, $status, $reason);
    }

    private function request(array $data = []): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $this->actor);
        $this->actingAs($this->actor);
        app(TenantContext::class)->set($this->f['provider']);

        return $request;
    }

    private function customerHtml(): string
    {
        app(TenantContext::class)->set($this->f['consumer']);
        auth()->forgetGuards();

        return app(ProviderFulfillmentController::class)->show($this->f['quote']->fresh())->getContent();
    }

    public function test_pending_has_no_decision_and_separate_pickup_state(): void
    {
        $this->assertSame('PENDING_PROVIDER', $this->bridge->acceptanceStatus());
        $this->assertSame('READY_FOR_PICKUP', $this->bridge->shipment->status);
        $this->assertDatabaseCount('network_provider_fulfillment_decisions', 0);
    }

    public static function decisions(): array
    {
        return [['ACCEPTED'], ['REJECTED']];
    }

    #[DataProvider('decisions')]
    public function test_pending_can_decide_with_actor_timestamp_channel(string $status): void
    {
        $decision = $this->decide($status);
        $this->assertSame($status, $this->bridge->fresh()->acceptanceStatus());
        $this->assertSame($this->actor->id, $decision->actor_user_id);
        $this->assertNotNull($decision->decided_at);
        $this->assertSame('TENANT_UI', $decision->channel);
    }

    #[DataProvider('decisions')]
    public function test_repeat_is_idempotent_preserves_first_audit(string $status): void
    {
        $one = $this->decide($status, $status === 'REJECTED' ? 'First reason' : null);
        $this->travel(5)->minutes();
        $two = $this->decide($status, $status === 'REJECTED' ? 'Replacement ignored' : null);
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertTrue($one->decided_at->equalTo($two->decided_at));
        $this->assertSame($one->rejection_reason, $two->rejection_reason);
        $this->assertDatabaseCount('network_provider_fulfillment_decisions', 1);
    }

    #[DataProvider('decisions')]
    public function test_opposite_terminal_transition_rejected(string $status): void
    {
        $this->decide($status);
        $this->expectException(ValidationException::class);
        $this->decide($status === 'ACCEPTED' ? 'REJECTED' : 'ACCEPTED');
    }

    #[DataProvider('decisions')]
    public function test_decision_preserves_economy_operations_shipments_usage_and_no_mail(string $status): void
    {
        $contract = $this->f['quote']->fresh()->providerContract;
        $before = [$contract->getAttributes(), $contract->snapshot->getAttributes(), $this->bridge->shipment->getAttributes(), $this->f['quote']->fresh()->getAttributes()];
        $this->decide($status);
        $after = [$contract->fresh()->getAttributes(), $contract->snapshot->fresh()->getAttributes(), $this->bridge->shipment->fresh()->getAttributes(), $this->f['quote']->fresh()->getAttributes()];
        $this->assertSame($before, $after);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('network_usage_events', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_other_provider_cannot_decide_even_with_membership(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        TenantMembership::create(['tenant_id' => $other->id, 'user_id' => $this->actor->id, 'role' => 'owner', 'status' => 'active']);
        $this->expectException(ModelNotFoundException::class);
        app(ProviderFulfillmentDecisionService::class)->decide($other, $this->bridge->uuid, $this->actor, 'ACCEPTED');
    }

    public function test_consumer_membership_does_not_authorize_provider(): void
    {
        TenantMembership::where('user_id', $this->actor->id)->delete();
        TenantMembership::create(['tenant_id' => $this->f['consumer']->id, 'user_id' => $this->actor->id, 'role' => 'owner', 'status' => 'active']);
        $this->expectException(HttpException::class);
        $this->decide();
    }

    public static function unauthorized(): array
    {
        return [['viewer', 'active'], ['driver', 'active'], ['billing', 'active'], ['operator', 'suspended']];
    }

    #[DataProvider('unauthorized')]
    public function test_ineligible_membership_denied(string $role, string $status): void
    {
        TenantMembership::where('user_id', $this->actor->id)->update(compact('role', 'status'));
        $this->expectException(HttpException::class);
        $this->decide();
    }

    public function test_suspended_provider_denied(): void
    {
        $this->f['provider']->update(['status' => 'suspended']);
        $this->expectException(HttpException::class);
        $this->decide();
    }

    public static function badReasons(): array
    {
        return [[str_repeat('x', 501)], ["bad\nreason"], ["bad\0reason"]];
    }

    #[DataProvider('badReasons')]
    public function test_unsafe_reason_rejected(string $reason): void
    {
        $this->expectException(ValidationException::class);
        $this->decide('REJECTED', $reason);
    }

    public function test_reason_trimmed_and_retained_only_in_internal_audit(): void
    {
        $record = $this->decide('REJECTED', '  Private reason  ');
        $this->assertSame('Private reason', $record->rejection_reason);
        $this->assertArrayNotHasKey('rejection_reason', $record->toArray());
        $this->assertStringNotContainsString('Private reason', $this->customerHtml());
        $this->assertStringContainsString('Estamos revisando tu envío', $this->customerHtml());
    }

    public function test_pending_consumer_projection(): void
    {
        $this->assertStringContainsString('Solicitud enviada al operador logístico', $this->customerHtml());
    }

    public function test_accepted_consumer_projection(): void
    {
        $this->decide();
        $this->assertStringContainsString('Recolección confirmada', $this->customerHtml());
    }

    public function test_provider_inbox_ownership_and_private_view(): void
    {
        $request = $this->request();
        $controller = app(ProviderFulfillmentInboxController::class);
        $html = $controller->index($request, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString($this->bridge->shipment->tracking_number, $html);
        $html = $controller->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class))->getContent();
        foreach (['Pickup Street', 'Delivery Street', 'Aceptar solicitud', 'Rechazar solicitud', 'Consumer Store'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['provider_cost', 'profit_amount', 'customer_price', 'connection_id', 'tenant_id', 'Cancelar', '145.00', $this->f['quote']->fresh()->providerContract->uuid] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_other_provider_inbox_is_empty(): void
    {
        $request = $this->request();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        TenantMembership::create(['tenant_id' => $other->id, 'user_id' => $this->actor->id, 'role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($other);
        $subscription = DB::table('network_subscriptions')->where('tenant_id', $this->f['provider']->id)->first();
        $attributes = (array) $subscription;
        unset($attributes['id']);
        $attributes['tenant_id'] = $other->id;
        $attributes['uuid'] = (string) Str::uuid();
        $subscriptionId = DB::table('network_subscriptions')->insertGetId($attributes);
        $entitlement = (array) DB::table('network_entitlements')->where('tenant_id', $this->f['provider']->id)->first();
        unset($entitlement['id']);
        $entitlement['tenant_id'] = $other->id;
        $entitlement['subscription_id'] = $subscriptionId;
        DB::table('network_entitlements')->insert($entitlement);
        $response = app(ProviderFulfillmentInboxController::class)->index($request, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class));
        $this->assertCount(0, $response->original->getData()['fulfillments']);
        $this->expectException(ModelNotFoundException::class);
        app(ProviderFulfillmentInboxController::class)->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class));
    }

    public function test_post_ignores_manipulated_tenant_price_service_and_status(): void
    {
        $request = $this->request(['provider_tenant_id' => $this->f['consumer']->id, 'price' => 1, 'service' => 'NATIONAL', 'status' => 'REJECTED']);
        app(ProviderFulfillmentInboxController::class)->accept($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class));
        $this->assertSame('ACCEPTED', $this->bridge->fresh()->acceptanceStatus());
        $this->assertSame('145.00', $this->f['quote']->fresh()->precio);
        $this->assertSame($this->f['provider']->id, app(TenantContext::class)->id());
    }

    public function test_consumer_context_not_modified_by_domain_service(): void
    {
        $this->decide();
        $this->assertSame($this->f['consumer']->id, app(TenantContext::class)->id());
    }

    public function test_future_internal_adapter_reuses_domain_without_controller(): void
    {
        $record = app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->actor, 'ACCEPTED', null, 'INTERNAL');
        $this->assertSame('INTERNAL', $record->channel);
    }

    public function test_unsupported_crm_channel_not_enabled(): void
    {
        $this->expectException(ValidationException::class);
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->actor, 'ACCEPTED', null, 'CRM');
    }

    public function test_pending_and_rejected_block_existing_dispatch(): void
    {
        $shipment = $this->bridge->shipment;
        $gate = app(ProviderDispatchEligibility::class);
        $this->assertFalse($gate->allows($shipment));
        $this->assertCount(0, app(DriverDispatchService::class)->manualCandidates($this->f['provider'], $shipment));
        $this->assertCount(0, $gate->scope(LocalShipment::query())->get());
        $this->decide('REJECTED');
        $this->assertFalse($gate->allows($shipment));
        $this->assertNull(app(DriverDispatchService::class)->autoAssign($this->f['provider'], $shipment, $this->actor->id));
    }

    public function test_accepted_is_dispatch_eligible_without_automatic_assignment(): void
    {
        $this->decide();
        $this->assertTrue(app(ProviderDispatchEligibility::class)->allows($this->bridge->shipment));
        $this->assertCount(1, app(ProviderDispatchEligibility::class)->scope(LocalShipment::query())->get());
        $this->assertDatabaseCount('local_driver_assignments', 0);
    }

    public function test_non_provider_dispatch_remains_eligible(): void
    {
        DB::table('network_tenant_operations')->where('id', $this->bridge->tenant_operation_id)->update(['source_type' => 'tenant_b2c']);
        $this->assertTrue(app(ProviderDispatchEligibility::class)->allows($this->bridge->fresh()->shipment));
        $this->assertCount(1, app(ProviderDispatchEligibility::class)->scope(LocalShipment::query())->get());
    }

    public function test_decision_immutable_and_duplicate_unique(): void
    {
        $record = $this->decide();
        try {
            $record->forceFill(['status' => 'REJECTED'])->saveQuietly();
            $this->fail('Immutable');
        } catch (\LogicException) {
        }
        try {
            $record->deleteQuietly();
            $this->fail('Immutable');
        } catch (\LogicException) {
        }
        $this->expectException(QueryException::class);
        DB::table('network_provider_fulfillment_decisions')->insert(array_replace($record->fresh()->getAttributes(), ['id' => 99, 'uuid' => (string) Str::uuid()]));
    }

    public function test_rollback_preserves_fulfillment_operation_shipment_and_economy(): void
    {
        $this->decide();
        $migration = require database_path('migrations/2026_10_13_130000_create_network_provider_fulfillment_decisions.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('network_provider_fulfillment_decisions'));
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertFalse(app(ProviderDispatchEligibility::class)->allows($this->bridge->shipment));
        $migration->up();
        $this->assertSame('145.00', $this->f['quote']->fresh()->precio);
    }

    public function test_guest_recovery_and_guide_survive_rejection_without_internal_details(): void
    {
        $this->decide('REJECTED', 'Private provider reason');
        $plain = app(GuideRecoveryService::class)->issue($this->f['quote']->fresh(), 'guest@example.test');
        $token = app(GuideRecoveryService::class)->resolve($plain);
        $controller = app(ProviderFulfillmentController::class);
        $html = $controller->recovered($token, $plain)->getContent();
        foreach (['Private provider reason', 'provider_cost', 'profit_amount', 'tenant_id', 'Cancelar'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringContainsString('Estamos revisando tu envío', $html);
        $this->assertStringStartsWith('%PDF-', $controller->recovered($token, $plain, true)->getContent());
        $this->assertSame($this->f['consumer']->id, app(TenantContext::class)->id());
    }

    public function test_endpoints_are_post_and_protected_by_existing_tenant_and_web_boundary(): void
    {
        foreach (['accept', 'reject'] as $action) {
            $route = app('router')->getRoutes()->getByName('tenant.admin.provider-inbox.'.$action);
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('tenant.entitlement:LOCAL_SHIPPING', $route->gatherMiddleware());
        }
    }

    public function test_reject_endpoint_records_safe_escaped_reason(): void
    {
        $request = $this->request(['reason' => '<script>alert(1)</script>']);
        $controller = app(ProviderFulfillmentInboxController::class);
        $controller->reject($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class));
        $html = $controller->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('Aceptar solicitud', $html);
        $this->assertStringNotContainsString('Cancelar', $html);
    }

    public function test_csrf_rejects_missing_token_and_accepts_matching_token(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'local';
        $request = Request::create('/admin/solicitudes-provider/'.$this->bridge->uuid.'/accept', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->regenerateToken();
        $middleware = app(VerifyCsrfToken::class);
        try {
            try {
                $middleware->handle($request, fn () => response('OK'));
                $this->fail('CSRF required');
            } catch (TokenMismatchException) {
            }
            $request->request->set('_token', $request->session()->token());
            $this->assertSame('OK', $middleware->handle($request, fn () => response('OK'))->getContent());
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_accepted_reuses_real_manual_driver_assignment_idempotently(): void
    {
        Schema::drop('local_driver_assignments');
        (require database_path('migrations/2026_08_10_100000_create_driver_last_mile_tables.php'))->up();
        Schema::table('tenant_driver_profiles', function (Blueprint $t): void {
            $t->string('availability_status')->default('AVAILABLE');
            $t->dateTime('last_seen_at')->nullable();
        });
        $entitlement = (array) DB::table('network_entitlements')->where('tenant_id', $this->f['provider']->id)->first();
        unset($entitlement['id']);
        $entitlement['code'] = 'DRIVER';
        DB::table('network_entitlements')->insert($entitlement);
        $driverUser = User::create(['name' => 'Driver', 'email' => 'driver@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $driverUser->id, 'role' => 'driver', 'status' => 'active']);
        $driver = DriverProfile::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $driverUser->id, 'code' => 'D1', 'status' => 'ACTIVE']);
        $assignments = app(DriverAssignmentService::class);
        try {
            $assignments->assign($this->f['provider'], $this->bridge->shipment, $driver, $this->actor->id);
            $this->fail('Pending must not dispatch');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->decide();
        $one = $assignments->assign($this->f['provider'], $this->bridge->shipment, $driver, $this->actor->id);
        $two = $assignments->assign($this->f['provider'], $this->bridge->shipment, $driver, $this->actor->id);
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertDatabaseCount('local_driver_assignments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        TenantMembership::where('user_id', $this->actor->id)->update(['role' => 'admin']);
        $request = $this->request();
        $html = app(ProviderFulfillmentInboxController::class)->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('Asignar conductor', $html);
        $this->assertStringNotContainsString('Cancelar', $html);
    }
}
