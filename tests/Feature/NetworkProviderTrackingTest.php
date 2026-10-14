<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderDriverExecutionService;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\ProviderTrackingProjection;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DeliveryEvidenceService;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Http\Controllers\B2C\ProviderFulfillmentController;
use App\Http\Controllers\Tenant\DriverConsoleController;
use App\Http\Controllers\Tenant\ProviderFulfillmentInboxController;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\Support\ProviderTrackingFixture;
use Tests\TestCase;

final class NetworkProviderTrackingTest extends TestCase
{
    private array $f;

    private ProviderFulfillment $bridge;

    private User $operator;

    private DriverProfile $driver;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite required.');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('local');
        view()->share('errors', new ViewErrorBag);
        ProviderTrackingFixture::schema();
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        ProviderTrackingFixture::grantDriver($this->f);
        $quote = $this->f['quote'];
        app(TenantContext::class)->set($this->f['consumer']);
        session()->put('cotizacion_publica_id', $quote->id);
        $option = app(ProviderQuotePublisher::class)->publish($quote, app(TenantProviderQuoteSource::class)->options($quote, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($quote, $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $quote->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$quote->id])->save());
        $this->bridge = app(ProviderFulfillmentService::class)->fulfill($quote);
        $this->operator = User::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $this->operator->id, 'role' => 'admin', 'status' => 'active']);
        $this->driver = ProviderTrackingFixture::driver($this->f['provider']);
    }

    private function accept(): void
    {
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->operator, 'ACCEPTED');
    }

    private function assign(?DriverProfile $driver = null): DriverAssignment
    {
        return app(DriverAssignmentService::class)->assign($this->f['provider'], $this->bridge->shipment, $driver ?? $this->driver, $this->operator->id);
    }

    private function execution(): ProviderDriverExecutionService
    {
        return app(ProviderDriverExecutionService::class);
    }

    private function ready(): DriverAssignment
    {
        $this->accept();

        return $this->assign();
    }

    private function out(DriverAssignment $assignment): void
    {
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            $this->execution()->transition($assignment, $this->driver->user_id, $status);
        }
    }

    private function delivered(): LocalDeliveryProof
    {
        $assignment = $this->ready();
        $this->out($assignment);

        return $this->execution()->deliver($assignment, $this->driver->user_id, ProviderTrackingFixture::proofData(),
            UploadedFile::fake()->image('delivery.jpg', 240, 160), ProviderTrackingFixture::signature());
    }

    private function projection(): array
    {
        return app(ProviderTrackingProjection::class)->project($this->bridge->fresh(), $this->f['consumer']);
    }

    private function html(): string
    {
        app(TenantContext::class)->set($this->f['consumer']);
        auth()->forgetGuards();

        return app(ProviderFulfillmentController::class)->show($this->f['quote']->fresh())->getContent();
    }

    public static function unavailableDecision(): array
    {
        return [['PENDING_PROVIDER'], ['REJECTED']];
    }

    #[DataProvider('unavailableDecision')]
    public function test_unaccepted_fulfillment_cannot_dispatch_or_execute_legacy_assignment(string $status): void
    {
        if ($status === 'REJECTED') {
            app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->operator, $status);
        }
        $this->assertCount(0, app(DriverDispatchService::class)->manualCandidates($this->f['provider'], $this->bridge->shipment));
        $this->assertNull(app(DriverDispatchService::class)->autoAssign($this->f['provider'], $this->bridge->shipment, $this->operator->id));
        try {
            $this->assign();
            $this->fail('Decision required');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $legacy = DriverAssignment::create(['tenant_id' => $this->f['provider']->id, 'local_shipment_id' => $this->bridge->local_shipment_id,
            'active_shipment_id' => $this->bridge->local_shipment_id, 'driver_profile_id' => $this->driver->id,
            'assigned_by_user_id' => $this->operator->id, 'assigned_at' => now(), 'status' => 'ACTIVE']);
        $this->expectException(HttpException::class);
        $this->execution()->transition($legacy, $this->driver->user_id, 'PICKED_UP');
    }

    public function test_accepted_same_provider_assignment_and_duplicate_are_safe(): void
    {
        $one = $this->ready();
        $two = $this->assign();
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertSame($this->f['provider']->id, $one->tenant_id);
        $this->assertSame('BUSY', $this->driver->fresh()->availability_status);
        $this->assertDatabaseCount('local_driver_assignments', 1);
    }

    public function test_driver_of_another_provider_rejected(): void
    {
        $this->accept();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $driver = ProviderTrackingFixture::driver($other, 'other');
        $this->expectException(HttpException::class);
        $this->assign($driver);
    }

    public static function ineligibleDrivers(): array
    {
        return [['INACTIVE', 'AVAILABLE', 'active'], ['ACTIVE', 'UNAVAILABLE', 'active'], ['ACTIVE', 'BUSY', 'active'], ['ACTIVE', 'AVAILABLE', 'suspended']];
    }

    #[DataProvider('ineligibleDrivers')]
    public function test_inactive_unavailable_busy_or_suspended_driver_cannot_assign(string $status, string $availability, string $membership): void
    {
        $this->accept();
        $this->driver->update(['status' => $status, 'availability_status' => $availability]);
        TenantMembership::where('user_id', $this->driver->user_id)->update(['status' => $membership]);
        $this->expectException(HttpException::class);
        $this->assign();
    }

    public function test_existing_auto_dispatch_online_available_driver_is_idempotent(): void
    {
        $this->accept();
        $dispatch = app(DriverDispatchService::class);
        $one = $dispatch->autoAssign($this->f['provider'], $this->bridge->shipment, $this->operator->id);
        $two = $dispatch->autoAssign($this->f['provider'], $this->bridge->shipment, $this->operator->id);
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertDatabaseCount('local_driver_assignments', 1);
    }

    public function test_offline_driver_not_auto_assigned_but_manual_policy_remains_available(): void
    {
        $this->accept();
        $this->driver->update(['last_seen_at' => now()->subHour()]);
        $this->assertNull(app(DriverDispatchService::class)->autoAssign($this->f['provider'], $this->bridge->shipment, $this->operator->id));
        $this->assertNotNull($this->assign());
    }

    public function test_existing_reassignment_only_before_pickup_revokes_old_driver_authority(): void
    {
        $one = $this->ready();
        $other = ProviderTrackingFixture::driver($this->f['provider'], 'two');
        $two = $this->assign($other);
        $this->assertSame('UNASSIGNED', $one->fresh()->status);
        $this->assertSame($other->id, $two->driver_profile_id);
        $this->expectException(HttpException::class);
        $this->execution()->transition($one, $this->driver->user_id, 'PICKED_UP');
    }

    public static function transitions(): array
    {
        return [['PICKED_UP', 3], ['IN_TRANSIT', 4], ['OUT_FOR_DELIVERY', 5]];
    }

    #[DataProvider('transitions')]
    public function test_existing_transitions_durable_and_repeat_does_not_duplicate(string $target, int $count): void
    {
        $assignment = $this->ready();
        $event = null;
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            $event = $this->execution()->transition($assignment, $this->driver->user_id, $status);
            if ($status === $target) {
                break;
            }
        }
        $repeat = $this->execution()->transition($assignment, $this->driver->user_id, $target);
        $this->assertSame($event->id, $repeat->id);
        $this->assertSame($target, $this->bridge->shipment->fresh()->status);
        $this->assertSame($this->driver->user_id, $event->created_by_user_id);
        $this->assertDatabaseCount('local_tracking_events', $count);
    }

    public static function invalidJumps(): array
    {
        return [['IN_TRANSIT'], ['OUT_FOR_DELIVERY']];
    }

    #[DataProvider('invalidJumps')]
    public function test_invalid_jump_rejected_by_existing_state_machine(string $target): void
    {
        $assignment = $this->ready();
        $this->expectException(\DomainException::class);
        $this->execution()->transition($assignment, $this->driver->user_id, $target);
    }

    public function test_delivered_requires_pod_not_a_tracking_shortcut(): void
    {
        $assignment = $this->ready();
        $this->expectException(ValidationException::class);
        $this->execution()->transition($assignment, $this->driver->user_id, 'DELIVERED');
    }

    public function test_another_driver_cannot_transition_even_knowing_assignment(): void
    {
        $assignment = $this->ready();
        $other = ProviderTrackingFixture::driver($this->f['provider'], 'two');
        $this->expectException(HttpException::class);
        $this->execution()->transition($assignment, $other->user_id, 'PICKED_UP');
    }

    public function test_revoked_driver_membership_rejected_after_assignment(): void
    {
        $assignment = $this->ready();
        TenantMembership::where('user_id', $this->driver->user_id)->update(['status' => 'suspended']);
        $this->expectException(HttpException::class);
        $this->execution()->transition($assignment, $this->driver->user_id, 'PICKED_UP');
    }

    public function test_consumer_timeline_is_a_safe_whitelist(): void
    {
        $assignment = $this->ready();
        $this->out($assignment);
        DB::table('local_tracking_events')->update(['description' => 'SECRET_INTERNAL_NOTE', 'metadata' => json_encode(['driver_id' => 999, 'provider_cost' => 100, 'profit_amount' => 25])]);
        $projection = $this->projection();
        $this->assertSame('En reparto', $projection['status_label']);
        $this->assertCount(6, $projection['timeline']);
        $html = $this->html();
        foreach (['Paquete recolectado', 'En tránsito', 'En reparto', 'Seguimiento de tu envío'] as $public) {
            $this->assertStringContainsString($public, $html);
        }
        foreach (['SECRET_INTERNAL_NOTE', 'provider_cost', 'profit_amount', 'pricing_rule', 'tenant_id', 'driver_id', 'connection_id', 'REJECTED', 'Cancelar', 'latitude', 'longitude'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($projection));
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_pending_and_rejected_project_friendly_messages(): void
    {
        $this->assertSame('Solicitud enviada al operador logístico', $this->projection()['status_label']);
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->operator, 'REJECTED', 'PRIVATE_REJECTION');
        $this->assertSame('Estamos revisando tu envío', $this->projection()['status_label']);
        $this->assertStringNotContainsString('PRIVATE_REJECTION', $this->html());
    }

    public function test_delivery_failed_is_idempotent_no_cancel_refund_or_economic_change(): void
    {
        $assignment = $this->ready();
        $this->out($assignment);
        $quote = $this->f['quote']->fresh();
        $contract = $quote->providerContract;
        $before = [$quote->getAttributes(), $contract->getAttributes(), $contract->snapshot->getAttributes()];
        $one = $this->execution()->fail($assignment, $this->driver->user_id, ['reason' => 'NO_ANSWER', 'notes' => 'PRIVATE_FAILED_REASON']);
        $two = $this->execution()->fail($assignment, $this->driver->user_id, ['reason' => 'OTHER', 'notes' => 'Retry ignored']);
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertSame('DELIVERY_FAILED', $this->bridge->shipment->fresh()->status);
        $this->assertSame('ACCEPTED', $this->bridge->fresh()->acceptanceStatus());
        $this->assertSame($before, [$quote->fresh()->getAttributes(), $contract->fresh()->getAttributes(), $contract->snapshot->fresh()->getAttributes()]);
        $this->assertSame('No fue posible completar la entrega', $this->projection()['status_label']);
        $this->assertStringNotContainsString('PRIVATE_FAILED_REASON', $this->html());
        $this->assertDatabaseCount('local_delivery_failed_attempts', 1);
        $this->assertDatabaseCount('local_tracking_events', 6);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_failed_attempt_limit_reused_without_cancellation(): void
    {
        $assignment = $this->ready();
        $this->out($assignment);
        $this->execution()->fail($assignment, $this->driver->user_id, ['reason' => 'NO_ANSWER']);
        $this->execution()->transition($assignment, $this->driver->user_id, 'OUT_FOR_DELIVERY');
        $this->execution()->fail($assignment, $this->driver->user_id, ['reason' => 'NO_ANSWER']);
        $this->assertDatabaseCount('local_delivery_failed_attempts', 2);
        $this->expectException(ValidationException::class);
        $this->execution()->transition($assignment, $this->driver->user_id, 'OUT_FOR_DELIVERY');
    }

    public function test_delivery_pod_provider_owned_economy_and_guide_unchanged(): void
    {
        $contract = $this->f['quote']->fresh()->providerContract;
        $before = [$contract->getAttributes(), $contract->snapshot->getAttributes(), $this->bridge->shipment->guide_snapshot];
        $proof = $this->delivered();
        $this->assertSame($this->f['provider']->id, $proof->tenant_id);
        $this->assertSame($this->bridge->local_shipment_id, $proof->local_shipment_id);
        $this->assertSame('DELIVERED', $this->bridge->shipment->fresh()->status);
        $this->assertSame($before, [$contract->fresh()->getAttributes(), $contract->snapshot->fresh()->getAttributes(), $this->bridge->shipment->fresh()->guide_snapshot]);
        $this->assertSame('145.00', $this->f['quote']->fresh()->precio);
        $this->assertSame('Entregado', $this->projection()['status_label']);
        $this->assertDatabaseCount('local_tracking_events', 6);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_repeated_pod_reuses_original_files_and_event(): void
    {
        $one = $this->delivered();
        $files = Storage::disk('local')->allFiles();
        $two = $this->execution()->deliver($one->assignment, $this->driver->user_id, []);
        $this->assertSame($one->uuid, $two->uuid);
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('local_delivery_proofs', 1);
        $this->assertDatabaseCount('driver_delivery_attributions', 1);
        $this->assertDatabaseCount('local_tracking_events', 6);
    }

    public function test_receiver_photo_signature_project_only_after_delivered(): void
    {
        $this->assertNull($this->projection()['pod']);
        $this->delivered();
        $pod = $this->projection()['pod'];
        $this->assertSame('Allowed Receiver', $pod['receiver']);
        $this->assertTrue($pod['photo_available']);
        $this->assertTrue($pod['signature_available']);
        $this->assertArrayNotHasKey('receiver_notes', $pod);
        $html = $this->html();
        foreach (['Allowed Receiver', 'Ver fotografía', 'Ver firma'] as $public) {
            $this->assertStringContainsString($public, $html);
        }
        $this->assertStringNotContainsString('Private receiver note', $html);
        $this->assertStringNotContainsString('pod/photos', $html);
    }

    public function test_private_evidence_routes_return_own_files_and_never_accept_arbitrary_path(): void
    {
        $this->delivered();
        $controller = app(ProviderFulfillmentController::class);
        $contract = $this->f['quote']->fresh()->providerContract;
        $response = $controller->evidence($contract, 'photo');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(200, $controller->evidence($contract, 'signature')->getStatusCode());
        $this->expectException(HttpException::class);
        $controller->evidence($contract, '../anything');
    }

    public function test_other_consumer_cannot_read_tracking_or_evidence(): void
    {
        $this->delivered();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        try {
            app(ProviderTrackingProjection::class)->project($this->bridge, $other);
            $this->fail('Scope required');
        } catch (HttpException) {
        }
        app(TenantContext::class)->set($other);
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->evidence($this->f['quote']->fresh()->providerContract, 'photo');
    }

    public function test_evidence_with_wrong_tenant_is_not_projected(): void
    {
        $proof = $this->delivered();
        DB::table('local_delivery_proofs')->where('id', $proof->id)->update(['tenant_id' => $this->f['consumer']->id]);
        $this->assertNull($this->projection()['pod']);
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->evidence($this->f['quote']->fresh()->providerContract, 'photo');
    }

    public function test_guest_own_tracking_pod_and_original_guide_work_without_token_in_pdf(): void
    {
        $this->delivered();
        $plain = app(GuideRecoveryService::class)->issue($this->f['quote']->fresh(), 'guest@example.test');
        $access = app(GuideRecoveryService::class)->resolve($plain);
        $controller = app(ProviderFulfillmentController::class);
        $html = $controller->recovered($access, $plain)->getContent();
        $this->assertStringContainsString('Entregado', $html);
        $this->assertStringContainsString('Allowed Receiver', $html);
        $this->assertSame(200, $controller->recoveredEvidence($access, $plain, 'photo')->getStatusCode());
        $pdf = $controller->recovered($access, $plain, true)->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString($plain, $pdf);
        foreach (['provider_cost', 'profit_amount', 'tenant_id', 'Private receiver note'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_guest_wrong_token_rejected(): void
    {
        $plain = app(GuideRecoveryService::class)->issue($this->f['quote']->fresh(), 'guest@example.test');
        $access = app(GuideRecoveryService::class)->resolve($plain);
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->recovered($access, str_repeat('x', 80));
    }

    public function test_guest_other_consumer_rejected(): void
    {
        $plain = app(GuideRecoveryService::class)->issue($this->f['quote']->fresh(), 'guest@example.test');
        $access = app(GuideRecoveryService::class)->resolve($plain);
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']));
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->recoveredEvidence($access, $plain, 'photo');
    }

    public function test_guest_expired_token_cannot_read_projection(): void
    {
        $plain = app(GuideRecoveryService::class)->issue($this->f['quote']->fresh(), 'guest@example.test');
        $access = app(GuideRecoveryService::class)->resolve($plain);
        $access->expires_at = now()->subSecond();
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->recovered($access, $plain);
    }

    public function test_provider_and_consumer_context_remain_unchanged(): void
    {
        app(TenantContext::class)->set($this->f['provider']);
        $before = app(TenantContext::class)->current();
        $assignment = $this->ready();
        $this->out($assignment);
        $this->assertSame($before, app(TenantContext::class)->current());
        app(TenantContext::class)->set($this->f['consumer']);
        $before = app(TenantContext::class)->current();
        $this->html();
        $this->assertSame($before, app(TenantContext::class)->current());
    }

    public function test_provider_inbox_sees_tracking_without_internal_economics(): void
    {
        $assignment = $this->ready();
        $this->out($assignment);
        app(TenantContext::class)->set($this->f['provider']);
        $this->actingAs($this->operator);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $this->operator);
        $html = app(ProviderFulfillmentInboxController::class)->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('Paquete recolectado', $html);
        $this->assertStringNotContainsString('provider_cost', $html);
        $this->assertStringNotContainsString('Cancelar', $html);
    }

    public function test_driver_ui_transitions_and_repeat_delivery_use_domain_boundary(): void
    {
        $assignment = $this->ready();
        $this->actingAs($this->driver->user);
        app(TenantContext::class)->set($this->f['provider']);
        $request = Request::create('/', 'POST', ['status' => 'PICKED_UP']);
        $request->attributes->set('driver_profile', $this->driver);
        app(DriverConsoleController::class)->transition($request, $this->bridge->shipment->uuid,
            app(LocalTrackingService::class), app(DeliveryRequirementService::class));
        $this->execution()->transition($assignment, $this->driver->user_id, 'IN_TRANSIT');
        $this->execution()->transition($assignment, $this->driver->user_id, 'OUT_FOR_DELIVERY');
        $this->execution()->deliver($assignment, $this->driver->user_id, ProviderTrackingFixture::proofData(), UploadedFile::fake()->image('proof.jpg'), ProviderTrackingFixture::signature());
        $retry = Request::create('/', 'POST');
        $retry->attributes->set('driver_profile', $this->driver);
        app(DriverConsoleController::class)->storeProof($retry, $this->bridge->shipment->uuid, app(DeliveryEvidenceService::class), app(DeliveryRequirementService::class));
        $this->assertDatabaseCount('local_delivery_proofs', 1);
        $this->assertDatabaseCount('local_tracking_events', 6);
    }

    public function test_authenticated_customer_can_read_own_contract_but_other_customer_cannot(): void
    {
        $this->delivered();
        $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'unused']);
        $quote = $this->f['quote']->fresh();
        $quote->update(['user_id' => $customer->id]);
        session()->flush();
        $this->actingAs($customer);
        $controller = app(ProviderFulfillmentController::class);
        $this->assertStringContainsString('Entregado', $controller->publicShow($quote->providerContract)->getContent());
        $this->assertSame(200, $controller->evidence($quote->providerContract, 'photo')->getStatusCode());
        $other = User::create(['name' => 'Other Customer', 'email' => 'other-customer@example.test', 'password' => 'unused']);
        $this->actingAs($other);
        $this->expectException(HttpException::class);
        $controller->publicShow($quote->providerContract);
    }

    public function test_acceptance_timeline_precedes_pickup_even_with_second_precision(): void
    {
        $this->freezeTime();
        $assignment = $this->ready();
        $this->out($assignment);
        $labels = array_column($this->projection()['timeline'], 'label');
        $this->assertLessThan(array_search('Paquete recolectado', $labels), array_search('Recolección confirmada', $labels));
    }

    public function test_provider_cannot_read_another_provider_inbox(): void
    {
        $other = Tenant::create(['name' => 'Other Provider', 'slug' => 'other', 'status' => 'active']);
        TenantMembership::create(['tenant_id' => $other->id, 'user_id' => $this->operator->id, 'role' => 'admin', 'status' => 'active']);
        app(TenantContext::class)->set($other);
        $this->actingAs($this->operator);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $this->operator);
        $this->expectException(ModelNotFoundException::class);
        app(ProviderFulfillmentInboxController::class)->show($request, $this->bridge->uuid, app(TenantContext::class), app(ProviderFulfillmentDecisionService::class));
    }

    public function test_missing_evidence_or_malformed_signature_does_not_mark_delivered(): void
    {
        $assignment = $this->ready();
        $this->out($assignment);
        try {
            $this->execution()->deliver($assignment, $this->driver->user_id, ProviderTrackingFixture::proofData());
            $this->fail('Required evidence missing');
        } catch (ValidationException) {
        }
        try {
            $this->execution()->deliver($assignment, $this->driver->user_id, ProviderTrackingFixture::proofData(), UploadedFile::fake()->image('photo.jpg'), 'invalid');
            $this->fail('Malformed signature');
        } catch (ValidationException) {
        }
        $this->assertSame('OUT_FOR_DELIVERY', $this->bridge->shipment->fresh()->status);
        $this->assertDatabaseCount('local_delivery_proofs', 0);
        $this->assertDatabaseCount('local_tracking_events', 5);
    }

    public function test_consumer_actor_cannot_assign_driver_through_internal_service(): void
    {
        $this->accept();
        $consumerActor = User::create(['name' => 'Consumer Admin', 'email' => 'consumer-admin@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $this->f['consumer']->id, 'user_id' => $consumerActor->id, 'role' => 'owner', 'status' => 'active']);
        $this->expectException(HttpException::class);
        app(DriverAssignmentService::class)->assign($this->f['provider'], $this->bridge->shipment, $this->driver, $consumerActor->id);
    }
}
