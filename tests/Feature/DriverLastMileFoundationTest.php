<?php

namespace Tests\Feature;

use App\Domain\Network\Billing\Models\Entitlement;
use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\DriverDeliveryService;
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Domain\Shipping\LastMile\Models\DriverCompensationPolicy;
use App\Domain\Shipping\LastMile\Models\DriverDeliveryAttribution;
use App\Domain\Shipping\LastMile\Models\DriverEarningEntry;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use App\Domain\Shipping\LastMile\Models\LocalDeliveryProof;
use App\Domain\Shipping\LastMile\Models\LocalShipmentDeliveryRequirement;
use App\Domain\Shipping\LastMile\Models\TenantDeliveryProofOption;
use App\Domain\Shipping\LastMile\DeliveryRequirementService;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class DriverLastMileFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_owner_and_admin_manage_tenant_scoped_driver_profiles_but_operator_cannot(): void
    {
        [$tenant,$owner] = $this->tenantOwner('manage');
        $candidate = $this->user('candidate@test.local');
        $membership = $tenant->memberships()->create(['user_id' => $candidate->id, 'role' => 'viewer', 'status' => 'active']);
        $this->actingAs($owner)->post($this->url($tenant, '/admin/drivers'), ['membership_id' => $membership->id, 'code' => 'DRV-01', 'vehicle_label' => 'Moto 1'])->assertRedirect();
        $profile = DriverProfile::firstOrFail();
        $this->assertSame($tenant->id, $profile->tenant_id);
        $this->assertSame('driver', $membership->fresh()->role);

        $admin = $this->member($tenant, 'admin', 'admin@test.local');
        $this->actingAs($admin)->patch($this->url($tenant, "/admin/drivers/{$profile->uuid}/toggle"))->assertRedirect();
        $this->assertSame('INACTIVE', $profile->fresh()->status);
        $operator = $this->member($tenant, 'operator', 'operator@test.local');
        $this->actingAs($operator)->get($this->url($tenant, '/admin/drivers'))->assertOk();
        $this->patch($this->url($tenant, "/admin/drivers/{$profile->uuid}/toggle"))->assertForbidden();
    }

    public function test_owner_creates_a_new_tenant_scoped_driver_with_hashed_password(): void
    {
        [$tenant, $owner] = $this->tenantOwner('create-driver');
        $other = $this->tenant('create-driver-other');
        $payload = [
            'name' => 'Nueva Motorista', 'email' => 'new-driver@test.local',
            'password' => 'Password!123', 'password_confirmation' => 'Password!123',
            'code' => 'NEW-01', 'vehicle_label' => 'Moto azul', 'tenant_id' => $other->id,
        ];

        $this->actingAs($owner)->post($this->url($tenant, '/admin/drivers'), $payload)->assertRedirect();
        $user = User::where('email', 'new-driver@test.local')->firstOrFail();
        $this->assertTrue(Hash::check('Password!123', $user->password));
        $this->assertNotSame('Password!123', $user->password);
        $this->assertDatabaseHas('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'driver', 'status' => 'active']);
        $this->assertDatabaseMissing('network_tenant_memberships', ['tenant_id' => $other->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('tenant_driver_profiles', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'code' => 'NEW-01', 'status' => 'ACTIVE']);

        $this->post($this->url($tenant, '/driver/login'), ['email' => $user->email, 'password' => 'Password!123'])->assertRedirect('/driver');
        $this->assertSame($tenant->id, DriverProfile::where('user_id', $user->id)->firstOrFail()->tenant_id);
    }

    public function test_existing_email_is_not_claimed_or_modified_when_creating_driver(): void
    {
        [$tenant, $owner] = $this->tenantOwner('existing-driver-email');
        $existing = $this->user('existing-driver@test.local');
        $password = $existing->password;

        $this->actingAs($owner)->post($this->url($tenant, '/admin/drivers'), [
            'name' => 'Intento', 'email' => strtoupper($existing->email),
            'password' => 'Different!123', 'password_confirmation' => 'Different!123', 'code' => 'EXISTS-01',
        ])->assertSessionHasErrors('email');

        $this->assertSame($password, $existing->fresh()->password);
        $this->assertDatabaseMissing('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $existing->id]);
        $this->assertDatabaseMissing('tenant_driver_profiles', ['tenant_id' => $tenant->id, 'user_id' => $existing->id]);
    }

    public function test_tenant_without_driver_entitlement_cannot_create_driver(): void
    {
        [$tenant, $owner] = $this->tenantOwner('create-without-driver', false);
        $this->actingAs($owner)->post($this->url($tenant, '/admin/drivers'), [
            'name' => 'Blocked', 'email' => 'blocked-driver@test.local',
            'password' => 'Password!123', 'password_confirmation' => 'Password!123', 'code' => 'BLOCKED-01',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked-driver@test.local']);
    }

    public function test_driver_capability_requires_entitlement_and_operational_subscription(): void
    {
        [$without,$owner] = $this->tenantOwner('without-driver', false);
        $this->actingAs($owner)->get($this->url($without, '/admin/drivers'))->assertForbidden();
        [$suspended,$driverUser,$profile] = $this->driverTenant('suspended-driver', true, 'suspended');
        $this->actingAs($driverUser)->get($this->url($suspended, '/driver'))->assertRedirect(route('driver.workspaces.index'));
        $profile->update(['status' => 'INACTIVE']);
        [$active,$activeDriver,$activeProfile] = $this->driverTenant('inactive-profile');
        $activeProfile->update(['status' => 'INACTIVE']);
        $this->actingAs($activeDriver)->get($this->url($active, '/driver'))->assertRedirect(route('driver.workspaces.index'));
    }

    public function test_driver_login_reuses_user_identity_and_rejects_non_driver_membership(): void
    {
        [$tenant,$driverUser,$profile] = $this->driverTenant('driver-login');
        $this->post($this->url($tenant, '/driver/login'), ['email' => $driverUser->email, 'password' => 'tenant-secret'])->assertRedirect('/driver');
        $this->post($this->url($tenant, '/driver/logout'))->assertRedirect('/driver/login');
        $profile->update(['status' => 'INACTIVE']);
        $this->post($this->url($tenant, '/driver/login'), ['email' => $driverUser->email, 'password' => 'tenant-secret'])->assertSessionHasErrors('email');
        $viewer = $this->member($tenant, 'viewer', 'viewer-login@test.local');
        $this->post($this->url($tenant, '/driver/login'), ['email' => $viewer->email, 'password' => 'tenant-secret'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_assignment_reassignment_history_unique_guard_and_tenant_isolation(): void
    {
        [$tenant,$owner] = $this->tenantOwner('assign');
        $first = $this->driver($tenant, 'first@test.local', 'DRV1');
        $second = $this->driver($tenant, 'second@test.local', 'DRV2');
        $shipment = $this->shipment($tenant);
        $this->ready($shipment);
        $service = app(DriverAssignmentService::class);
        $one = $service->assign($tenant, $shipment, $first, $owner->id);
        $same = $service->assign($tenant, $shipment, $first, $owner->id);
        $this->assertSame($one->id, $same->id);
        $two = $service->assign($tenant, $shipment, $second, $owner->id);
        $this->assertNotSame($one->id, $two->id);
        $this->assertSame('UNASSIGNED', $one->fresh()->status);
        $this->assertNotNull($one->fresh()->unassigned_at);
        $this->assertDatabaseCount('local_driver_assignments', 2);
        $this->assertSame(1, DriverAssignment::whereNotNull('active_shipment_id')->count());
        try {
            DriverAssignment::create(['tenant_id' => $tenant->id, 'local_shipment_id' => $shipment->id, 'active_shipment_id' => $shipment->id, 'driver_profile_id' => $first->id, 'assigned_by_user_id' => $owner->id, 'assigned_at' => now(), 'status' => 'ACTIVE']);
            $this->fail('Unique active shipment guard expected.');
        } catch (QueryException) {
        }
        [$other] = $this->tenantOwner('other');
        $this->expectException(NotFoundHttpException::class);
        $service->assign($other, $shipment, $second, $owner->id);
    }

    public function test_driver_console_is_assignment_scoped_private_and_cannot_enter_privileged_surfaces(): void
    {
        $bufferLevel = ob_get_level();
        [$tenant,$driverUser,$profile] = $this->driverTenant('console');
        $owner = $this->member($tenant, 'owner', 'console-owner@test.local');
        $own = $this->shipment($tenant, 'ZL260810OWN00001');
        $other = $this->shipment($tenant, 'ZL260810OTHER001');
        $this->ready($own);
        $this->ready($other);
        $otherDriver = $this->driver($tenant, 'other-driver@test.local', 'DRV2');
        app(DriverAssignmentService::class)->assign($tenant, $own, $profile, $owner->id);
        app(DriverAssignmentService::class)->assign($tenant, $other, $otherDriver, $owner->id);
        $this->actingAs($driverUser)->get($this->url($tenant, '/driver'))->assertOk()->assertSee($own->tracking_number)->assertDontSee($other->tracking_number)->assertDontSee('base_cost');
        $this->assertSame($bufferLevel, ob_get_level());
        $this->get($this->url($tenant, "/driver/shipments/{$own->uuid}"))->assertOk()->assertSee('Private Sender')->assertDontSee('80.00');
        $this->assertSame($bufferLevel, ob_get_level());
        $this->get($this->url($tenant, "/driver/shipments/{$other->uuid}"))->assertNotFound();
        $this->assertSame($bufferLevel, ob_get_level());
        foreach (['tenant.admin.dashboard', 'tenant.admin.users.index', 'tenant.admin.plan', 'tenant.admin.configuration.edit'] as $routeName) {
            $this->assertContains('tenant.admin.access', app('router')->getRoutes()->getByName($routeName)->gatherMiddleware());
        }
        $this->assertFalse($driverUser->hasRol('sysadmin'));
    }

    public function test_driver_global_identity_cannot_access_network(): void
    {
        [$tenant, $driverUser] = $this->driverTenant('no-network');
        $this->actingAs($driverUser)->get('/network')->assertForbidden();
        $this->assertNotSame($tenant->id, 0);
    }

    public function test_permissions_state_machine_public_tracking_append_only_and_usage_contract(): void
    {
        [$tenant,$driverUser,$profile] = $this->driverTenant('transitions');
        $owner = $this->member($tenant, 'owner', 'transition-owner@test.local');
        $shipment = $this->shipment($tenant, 'ZL260810FLOW0001');
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 1, 'created_at' => now()]);
        $this->actingAs($owner)->post($this->url($tenant, "/admin/operations/{$shipment->operation->uuid}/pickup-request"))->assertRedirect();
        app(DriverAssignmentService::class)->assign($tenant, $shipment->fresh(), $profile, $owner->id);
        $this->post($this->url($tenant, "/admin/local-shipments/{$shipment->uuid}/transition"), ['status' => 'IN_TRANSIT'])->assertSessionHasErrors('status');
        $this->actingAs($driverUser);
        $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition"), ['status' => 'DELIVERED'])->assertSessionHasErrors('status');
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition"), ['status' => $status])->assertRedirect();
        }
        $this->deliverWithProof($assignment = DriverAssignment::where('local_shipment_id', $shipment->id)->where('status', 'ACTIVE')->firstOrFail(), $driverUser);
        $before = DB::table('local_tracking_events')->count();
        $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition"), ['status' => 'DELIVERED'])->assertSessionHasErrors('status');
        $this->assertSame($before, DB::table('local_tracking_events')->count());
        $this->assertSame('DELIVERED', $shipment->fresh()->status);
        $this->assertDatabaseCount('local_tracking_events', 6);
        $this->get($this->url($tenant, "/tracking/{$shipment->tracking_number}"))->assertOk()->assertSee('DELIVERED')->assertDontSee($driverUser->name)->assertDontSee('Private Recipient');
        $this->assertDatabaseCount('network_usage_events', 1);
        $event = DB::table('local_tracking_events')->latest('id')->first();
        $this->assertSame($driverUser->id, $event->created_by_user_id);
    }

    public function test_customer_pickup_is_fixed_idempotent_ignores_client_logistics_fields_and_enters_tenant_queue(): void
    {
        [$tenant, $operator] = $this->tenantOwner('pickup');
        $shipment = $this->shipment($tenant, 'ZL260810PICKUP01');
        DB::table('local_shipments')->where('id', $shipment->id)->update(['sender_snapshot' => json_encode(['name'=>'Pickup Sender','address'=>['address'=>'Av. Tamaulipas 10','settlement'=>'Tamaulipas Sección Virgencitas','postal_code'=>'57300','municipality'=>'Nezahualcóyotl','state'=>'México']], JSON_UNESCAPED_UNICODE)]);
        $shipment->refresh();
        $url = $this->url($tenant, "/admin/operations/{$shipment->operation->uuid}/pickup-request");

        $this->actingAs($operator)->post($url, ['status' => 'DELIVERED', 'driver_uuid' => '00000000-0000-0000-0000-000000000000', 'tenant_id' => 999])->assertRedirect();
        $this->assertSame('READY_FOR_PICKUP', $shipment->fresh()->status);
        $events = DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count();
        $this->post($url)->assertRedirect();
        $this->assertSame($events, DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count());
        $this->get($this->url($tenant, '/admin/dispatch/pickups'))->assertOk()->assertSee($shipment->tracking_number)->assertSee('Sin asignar')->assertSee('Tamaulipas Sección Virgencitas')->assertSee('CP 57300')->assertSee('Nezahualcóyotl, México');
        $driver = $this->driver($tenant, 'pickup-driver@test.local', 'PICKUP-DRV');
        $this->post($this->url($tenant, "/admin/local-shipments/{$shipment->uuid}/driver"), ['driver_uuid' => $driver->uuid])->assertRedirect();
        $this->assertSame($driver->id, $shipment->fresh()->activeDriverAssignment->driver_profile_id);
        $this->get($this->url($tenant, '/admin/dispatch/pickups'))->assertOk()->assertSee('pickup-driver@test.local')->assertSee('Lista para recolectar');
        $this->get($this->url($tenant, "/tracking/{$shipment->tracking_number}"))->assertOk()->assertSee('READY FOR PICKUP');
        app(LocalTrackingService::class)->transition($shipment->fresh(), 'PICKED_UP', $driver->user_id);
        $this->get($this->url($tenant, '/admin/dispatch/pickups'))->assertOk()->assertDontSee($shipment->tracking_number);
        $this->assertDatabaseCount('network_usage_events', 0);

        [$otherTenant, $otherOwner] = $this->tenantOwner('pickup-other');
        $this->actingAs($otherOwner)->post($this->url($otherTenant, "/admin/operations/{$shipment->operation->uuid}/pickup-request"))->assertNotFound();
    }

    public function test_pickup_preserves_preexisting_active_assignment_and_restores_driver_visibility(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('pickup-existing');
        $owner = $this->member($tenant, 'owner', 'pickup-existing-owner@test.local');
        $shipment = $this->shipment($tenant, 'ZL2608089ABYVWRN8A');
        $assignment = DriverAssignment::create([
            'tenant_id' => $tenant->id,
            'local_shipment_id' => $shipment->id,
            'active_shipment_id' => $shipment->id,
            'driver_profile_id' => $profile->id,
            'assigned_by_user_id' => $owner->id,
            'assigned_at' => now(),
            'status' => 'ACTIVE',
        ]);
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 1, 'created_at' => now()]);
        $url = $this->url($tenant, "/admin/operations/{$shipment->operation->uuid}/pickup-request");

        $detail = $this->actingAs($owner)->get($this->url($tenant, "/admin/operations/{$shipment->operation->uuid}"))->assertOk();
        $this->assertMatchesRegularExpression('/<form[^>]+pickup-request[^>]*>.*?Solicitar recolección.*?<\/form>/s', $detail->getContent());
        preg_match('/<form[^>]+pickup-request[^>]*>(.*?)<\/form>/s', $detail->getContent(), $pickupForm);
        $this->assertStringNotContainsString('name="status"', $pickupForm[1]);
        $this->assertStringNotContainsString('driver_uuid', $pickupForm[1]);

        $this->post($url)->assertRedirect();
        $this->assertSame('READY_FOR_PICKUP', $shipment->fresh()->status);
        $this->assertSame('ACTIVE', $assignment->fresh()->status);
        $this->assertSame($shipment->id, $assignment->fresh()->active_shipment_id);
        $this->assertNull($assignment->fresh()->unassigned_at);
        $eventCount = DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count();
        $this->post($url, ['status' => 'CANCELED'])->assertRedirect();
        $this->assertSame($eventCount, DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count());
        $this->assertDatabaseCount('network_usage_events', 1);

        $this->actingAs($driverUser)->get($this->url($tenant, '/driver'))->assertOk()->assertSee($shipment->tracking_number);
    }

    public function test_picked_up_admin_detail_uses_in_progress_last_mile_copy(): void
    {
        [$tenant, $owner] = $this->tenantOwner('picked-up-copy');
        $shipment = $this->shipment($tenant, 'ZL260818PICKEDUP1');
        DB::table('local_shipments')->where('id', $shipment->id)->update(['status' => 'PICKED_UP']);

        $this->actingAs($owner)->get($this->url($tenant, "/admin/operations/{$shipment->operation->uuid}"))
            ->assertOk()->assertSee('Paquete recolectado. Envío en curso.')
            ->assertDontSee('La asignación estará disponible cuando se solicite la recolección.');
    }

    public function test_network_map_keeps_driver_marketplace_and_payouts_future(): void
    {
        $nodes = collect(config('zigo_network_map.nodes'))->keyBy('code');
        foreach (['DRIVER_MARKETPLACE', 'DRIVER_PAYOUTS'] as $code) {
            $this->assertTrue($nodes->has($code));
            $this->assertSame('planned', $nodes->get($code)['implementation_status']);
        }
    }

    public function test_auto_dispatch_requires_fresh_online_available_presence_and_is_tenant_scoped(): void
    {
        [$tenant, $owner] = $this->tenantOwner('presence');
        $driver = $this->driver($tenant, 'presence-driver@test.local', 'PRESENCE');
        $shipment = $this->shipment($tenant, 'ZL260811PRESENCE');
        $this->ready($shipment);
        $dispatch = app(DriverDispatchService::class);

        $driver->update(['availability_status' => 'AVAILABLE']);
        $this->assertCount(0, $dispatch->candidates($tenant, $shipment->fresh()));
        $driver->update(['last_seen_at' => now()->subMinutes(30)]);
        $this->assertCount(0, $dispatch->candidates($tenant, $shipment->fresh()));
        $driver->update(['last_seen_at' => now(), 'availability_status' => 'UNAVAILABLE']);
        $this->assertCount(0, $dispatch->candidates($tenant, $shipment->fresh()));
        $driver->update(['availability_status' => 'AVAILABLE']);
        $this->assertSame($driver->id, $dispatch->candidates($tenant, $shipment->fresh())->first()->id);

        [$other] = $this->tenantOwner('presence-other');
        $this->assertCount(0, $dispatch->candidates($other, $shipment->fresh()));
        $assignment = $dispatch->autoAssign($tenant, $shipment->fresh(), $owner->id);
        $this->assertSame($assignment->id, $dispatch->autoAssign($tenant, $shipment->fresh(), $owner->id)->id);
        $this->assertDatabaseCount('local_driver_assignments', 1);
    }

    public function test_auto_dispatch_selects_lowest_operational_load_with_deterministic_tie_break(): void
    {
        [$tenant, $owner] = $this->tenantOwner('load');
        $busy = $this->driver($tenant, 'busy@test.local', 'BUSY');
        $free = $this->driver($tenant, 'free@test.local', 'FREE');
        foreach ([$busy, $free] as $profile) {
            $profile->update(['availability_status' => 'AVAILABLE', 'last_seen_at' => now()]);
        }
        $existing = $this->shipment($tenant, 'ZL260811LOAD0001');
        $this->ready($existing);
        app(DriverAssignmentService::class)->assign($tenant, $existing, $busy, $owner->id);
        $busy->update(['availability_status' => 'AVAILABLE']);
        $target = $this->shipment($tenant, 'ZL260811LOAD0002');
        $this->ready($target);

        $selected = app(DriverDispatchService::class)->autoAssign($tenant, $target->fresh(), $owner->id);
        $this->assertSame($free->id, $selected->driver_profile_id);
    }

    public function test_navigation_uses_only_assigned_pickup_then_delivery_address(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('navigation');
        $owner = $this->member($tenant, 'owner', 'navigation-owner@test.local');
        $shipment = $this->shipment($tenant, 'ZL260811NAV00001');
        $this->ready($shipment);
        app(DriverAssignmentService::class)->assign($tenant, $shipment, $profile, $owner->id);

        $this->actingAs($driverUser)->get($this->url($tenant, "/driver/shipments/{$shipment->uuid}"))->assertOk()->assertSee('IR POR EL PAQUETE')->assertSee('Origin%201', false)->assertDontSee('Destination%202', false);
        app(LocalTrackingService::class)->transition($shipment->fresh(), 'PICKED_UP', $driverUser->id);
        $this->get($this->url($tenant, "/driver/shipments/{$shipment->uuid}"))->assertOk()->assertSee('IR A ENTREGA')->assertSee('Destination%202', false);
        $other = $this->driver($tenant, 'navigation-other@test.local', 'NAV2');
        $this->actingAs($other->user)->withSession([config('zigo_driver.context_session_key') => $other->uuid])->get($this->url($tenant, "/driver/shipments/{$shipment->uuid}"))->assertNotFound();
    }

    public function test_delivery_attribution_and_compensation_ledger_are_immutable_idempotent_and_usage_free(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('earnings');
        $owner = $this->member($tenant, 'owner', 'earnings-owner@test.local');
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 1, 'created_at' => now()]);
        $policy = DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'SALARIED', 'settlement_frequency' => 'WEEKLY', 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);

        $salaryShipment = $this->shipment($tenant, 'ZL260811SALARY01');
        $salaryAssignment = $this->assignedOutForDelivery($tenant, $salaryShipment, $profile, $owner, $driverUser);
        $this->deliverWithProof($salaryAssignment, $driverUser);
        $this->assertDatabaseCount('driver_delivery_attributions', 1);
        $this->assertDatabaseCount('driver_earning_entries', 0);

        foreach ([['PER_DELIVERY', 75], ['HYBRID', 35]] as [$type, $amount]) {
            $policy->update(['compensation_type' => $type, 'amount_per_delivery' => $amount]);
            $shipment = $this->shipment($tenant, 'ZL260811'.str_replace('_', '', $type));
            $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
            $this->deliverWithProof($assignment, $driverUser);
        }
        $this->assertSame(110.0, (float) DriverEarningEntry::sum('amount'));
        $this->assertDatabaseCount('driver_earning_entries', 2);
        $this->assertDatabaseCount('driver_delivery_attributions', 3);
        $this->assertDatabaseCount('network_usage_events', 1);
        $firstEarning = DriverEarningEntry::oldest('id')->firstOrFail();
        $policy->update(['compensation_type' => 'PER_DELIVERY', 'amount_per_delivery' => 70, 'currency' => 'USD']);
        $this->assertSame('75.00', $firstEarning->fresh()->amount);
        $this->assertSame('MXN', $firstEarning->fresh()->currency);
        $this->assertSame('PER_DELIVERY', $firstEarning->fresh()->compensation_type);
        try {
            app(DriverDeliveryService::class)->deliver($assignment, $driverUser->id);
            $this->fail('Una asignación completada no debe liquidarse nuevamente.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        }
        $this->assertDatabaseCount('driver_earning_entries', 2);
        $attribution = DriverDeliveryAttribution::latest('id')->firstOrFail();
        $this->assertSame($profile->id, $attribution->driver_profile_id);
        $this->assertSame($driverUser->id, $attribution->driver_user_id);
        $this->expectException(\LogicException::class);
        $attribution->update(['driver_code_snapshot' => 'ALTERED']);
    }

    public function test_driver_and_admin_reports_are_isolated_and_public_tracking_hides_economics(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('reports');
        $owner = $this->member($tenant, 'owner', 'reports-owner@test.local');
        $other = $this->driver($tenant, 'reports-other@test.local', 'REPORT2');
        $policy = DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'PER_DELIVERY', 'settlement_frequency' => 'BIWEEKLY', 'amount_per_delivery' => 88, 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);
        $shipment = $this->shipment($tenant, 'ZL260811REPORT01');
        $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
        $this->deliverWithProof($assignment, $driverUser);
        DriverEarningEntry::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $other->id, 'driver_user_id' => $other->user_id, 'local_shipment_id' => $shipment->id, 'driver_assignment_id' => $assignment->id, 'compensation_policy_id' => $policy->id, 'entry_type' => 'ADJUSTMENT', 'compensation_type' => 'PER_DELIVERY', 'amount' => 999, 'currency' => 'MXN', 'occurred_at' => now(), 'created_at' => now()]);

        $this->actingAs($driverUser)->get($this->url($tenant, '/driver'))->assertOk()->assertSee('88.00')->assertDontSee('999.00');
        $this->actingAs($owner)->get($this->url($tenant, "/admin/drivers/{$profile->uuid}"))->assertOk()->assertSee('Liquidación / actividad')->assertSee('88.00');
        $this->get($this->url($tenant, "/tracking/{$shipment->tracking_number}"))->assertOk()->assertDontSee($driverUser->name)->assertDontSee('88.00')->assertDontSee('AVAILABLE')->assertDontSee('REPORT2');
    }

    public function test_availability_and_compensation_requests_are_strictly_authorized(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('authorization');
        $this->actingAs($driverUser)->post($this->url($tenant, '/driver/availability'), ['availability_status' => 'BUSY', 'last_seen_at' => now()->addYear()])->assertSessionHasErrors('availability_status');
        $this->post($this->url($tenant, '/driver/availability'), ['availability_status' => 'AVAILABLE', 'status' => 'INACTIVE'])->assertRedirect();
        $this->assertSame('ACTIVE', $profile->fresh()->status);
        $this->assertSame('AVAILABLE', $profile->fresh()->availability_status);

        $operator = $this->member($tenant, 'operator', 'policy-operator@test.local');
        $payload = ['compensation_type' => 'PER_DELIVERY', 'settlement_frequency' => 'WEEKLY', 'amount_per_delivery' => 50, 'currency' => 'MXN'];
        $this->actingAs($operator)->put($this->url($tenant, "/admin/drivers/{$profile->uuid}/compensation"), $payload)->assertForbidden();
        $owner = $this->member($tenant, 'owner', 'policy-owner@test.local');
        $this->actingAs($owner)->put($this->url($tenant, "/admin/drivers/{$profile->uuid}/compensation"), $payload + ['driver_profile_id' => 999999])->assertRedirect();
        $this->assertDatabaseHas('driver_compensation_policies', ['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'PER_DELIVERY', 'amount_per_delivery' => 50]);
    }

    public function test_each_driver_has_an_isolated_policy_and_its_own_delivery_economics(): void
    {
        [$tenant, $owner] = $this->tenantOwner('driver-policies');
        $perDelivery = $this->driver($tenant, 'per-delivery@test.local', 'DRV-001');
        $salaried = $this->driver($tenant, 'salaried@test.local', 'DRV-002');
        $hybrid = $this->driver($tenant, 'hybrid@test.local', 'DRV-003');
        $policies = [
            [$perDelivery, 'PER_DELIVERY', 'WEEKLY', 60],
            [$salaried, 'SALARIED', 'BIWEEKLY', null],
            [$hybrid, 'HYBRID', 'MONTHLY', 25],
        ];

        foreach ($policies as [$profile, $type, $frequency, $amount]) {
            DriverCompensationPolicy::create([
                'tenant_id' => $tenant->id,
                'driver_profile_id' => $profile->id,
                'compensation_type' => $type,
                'settlement_frequency' => $frequency,
                'amount_per_delivery' => $amount,
                'currency' => 'MXN',
                'updated_by_user_id' => $owner->id,
            ]);
            $shipment = $this->shipment($tenant);
            $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $profile->user);
            $this->deliverWithProof($assignment, $profile->user);
        }

        $this->assertDatabaseCount('driver_compensation_policies', 3);
        $this->assertDatabaseCount('driver_delivery_attributions', 3);
        $this->assertDatabaseCount('driver_earning_entries', 2);
        $this->assertSame(60.0, (float) DriverEarningEntry::where('driver_profile_id', $perDelivery->id)->sum('amount'));
        $this->assertSame(0.0, (float) DriverEarningEntry::where('driver_profile_id', $salaried->id)->sum('amount'));
        $this->assertSame(25.0, (float) DriverEarningEntry::where('driver_profile_id', $hybrid->id)->sum('amount'));
    }

    public function test_compensation_route_cannot_target_another_tenant_or_trust_profile_id(): void
    {
        [$tenant, $owner] = $this->tenantOwner('policy-route');
        $ownDriver = $this->driver($tenant, 'policy-own@test.local', 'OWN');
        [$otherTenant] = $this->tenantOwner('policy-other');
        $otherDriver = $this->driver($otherTenant, 'policy-other-driver@test.local', 'OTHER');
        $payload = ['compensation_type' => 'HYBRID', 'settlement_frequency' => 'MONTHLY', 'amount_per_delivery' => 25, 'currency' => 'MXN'];

        $this->actingAs($owner)->put($this->url($tenant, "/admin/drivers/{$ownDriver->uuid}/compensation"), $payload + ['driver_profile_id' => $otherDriver->id])->assertRedirect();
        $this->assertDatabaseHas('driver_compensation_policies', ['tenant_id' => $tenant->id, 'driver_profile_id' => $ownDriver->id]);
        $this->assertDatabaseMissing('driver_compensation_policies', ['driver_profile_id' => $otherDriver->id]);
        $this->put($this->url($tenant, "/admin/drivers/{$otherDriver->uuid}/compensation"), $payload)->assertNotFound();
    }

    public function test_terminal_delivery_redirects_to_safe_driver_history_without_leaking_other_deliveries(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('terminal-ux');
        $owner = $this->member($tenant, 'owner', 'terminal-owner@test.local');
        $otherDriver = $this->driver($tenant, 'terminal-other@test.local', 'TERMINAL-OTHER');
        DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'PER_DELIVERY', 'settlement_frequency' => 'WEEKLY', 'amount_per_delivery' => 60, 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);
        DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $otherDriver->id, 'compensation_type' => 'PER_DELIVERY', 'settlement_frequency' => 'WEEKLY', 'amount_per_delivery' => 99, 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 39, 'created_at' => now()]);

        $shipment = $this->shipment($tenant, 'ZL260812TERMINAL1');
        $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
        $otherShipment = $this->shipment($tenant, 'ZL260812OTHERDRV1');
        $otherAssignment = $this->assignedOutForDelivery($tenant, $otherShipment, $otherDriver, $owner, $otherDriver->user);
        $this->deliverWithProof($otherAssignment, $otherDriver->user);

        $url = $this->url($tenant, "/driver/shipments/{$shipment->uuid}/proof");
        $payload = ['received_by_name' => 'Safe Receiver', 'receiver_type' => 'RECIPIENT', 'photo' => UploadedFile::fake()->image('pod.jpg'), 'signature' => $this->signaturePayload(), 'latitude' => 25.6866, 'longitude' => -100.3161, 'accuracy_meters' => 8];
        $response = $this->actingAs($driverUser)->post($url, $payload);
        $response->assertRedirect(route('driver.dashboard'))->assertSessionHas('success', 'Entrega completada correctamente.');
        $this->get($this->url($tenant, "/driver/shipments/{$shipment->uuid}"))->assertNotFound();

        $dashboard = $this->get($this->url($tenant, '/driver'))->assertOk()
            ->assertSee('Entregadas: <strong>1</strong>', false)
            ->assertSee('Ganancias generadas: <strong>60.00 MXN</strong>', false)
            ->assertSee('Pendientes de liquidación: <strong>60.00 MXN</strong>', false)
            ->assertSee('PER_DELIVERY')->assertSee('WEEKLY')->assertSee('60.00 MXN')
            ->assertSee('Entregas completadas')->assertSee($shipment->tracking_number)
            ->assertDontSee($otherShipment->tracking_number)->assertDontSee('99.00')
            ->assertDontSee('Private Recipient')->assertDontSee('Private Sender')
            ->assertDontSee('Destination 2')->assertDontSee('Origin 1')->assertDontSee('8112345678');

        $counts = [DriverDeliveryAttribution::count(), DriverEarningEntry::count(), DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->where('status', 'DELIVERED')->count(), DB::table('network_usage_events')->sum('quantity')];
        $this->post($url, $payload + ['photo' => UploadedFile::fake()->image('again.jpg')])->assertNotFound();
        $this->assertSame($counts, [DriverDeliveryAttribution::count(), DriverEarningEntry::count(), DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->where('status', 'DELIVERED')->count(), DB::table('network_usage_events')->sum('quantity')]);

        [$otherTenant, $otherOwner] = $this->tenantOwner('terminal-other-tenant');
        $otherTenantDriver = $this->driver($otherTenant, 'terminal-cross-tenant@test.local', 'CROSS-TENANT');
        $crossTenantShipment = $this->shipment($otherTenant, 'ZL260812OTHERTEN1');
        DriverDeliveryAttribution::create(['tenant_id' => $otherTenant->id, 'local_shipment_id' => $crossTenantShipment->id, 'driver_assignment_id' => DriverAssignment::create(['tenant_id' => $otherTenant->id, 'local_shipment_id' => $crossTenantShipment->id, 'driver_profile_id' => $otherTenantDriver->id, 'assigned_by_user_id' => $otherOwner->id, 'assigned_at' => now(), 'status' => 'COMPLETED'])->id, 'driver_profile_id' => $otherTenantDriver->id, 'driver_user_id' => $otherTenantDriver->user_id, 'driver_code_snapshot' => $otherTenantDriver->code, 'delivered_at' => now(), 'created_at' => now()]);
        $this->get($this->url($tenant, '/driver'))->assertOk()->assertDontSee($crossTenantShipment->tracking_number);
    }

    public function test_pod_validation_assignment_scope_and_delivery_without_proof_are_enforced(): void
    {
        Storage::fake('local');
        [$tenant, $driverUser, $profile] = $this->driverTenant('pod-validation');
        $owner = $this->member($tenant, 'owner', 'pod-validation-owner@test.local');
        $other = $this->driver($tenant, 'pod-validation-other@test.local', 'POD-OTHER');
        $shipment = $this->shipment($tenant, 'ZL260812PODVALID1');
        $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
        $url = $this->url($tenant, "/driver/shipments/{$shipment->uuid}/proof");

        try {
            app(DriverDeliveryService::class)->deliver($assignment, $driverUser->id);
            $this->fail('DELIVERED sin POD debe rechazarse.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $valid = ['received_by_name' => 'Receiver', 'receiver_type' => 'RECIPIENT', 'photo' => UploadedFile::fake()->image('pod.jpg'), 'signature' => $this->signaturePayload(), 'latitude' => 25.6, 'longitude' => -100.3, 'accuracy_meters' => 5];
        $this->actingAs($other->user)->withSession([config('zigo_driver.context_session_key') => $other->uuid])->post($url, $valid)->assertNotFound();
        $this->actingAs($driverUser)->withSession([config('zigo_driver.context_session_key') => $profile->uuid])->post($url, array_merge($valid, ['receiver_type' => 'ARBITRARY']))->assertSessionHasErrors('receiver_type');
        $this->post($url, array_merge($valid, ['photo' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')]))->assertSessionHasErrors('photo');
        $this->post($url, array_merge($valid, ['photo' => UploadedFile::fake()->createWithContent('fake.jpg', '<script>alert(1)</script>')]))->assertSessionHasErrors('photo');
        $this->post($url, array_merge($valid, ['photo' => UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg')]))->assertSessionHasErrors('photo');
        $this->post($url, array_merge($valid, ['signature' => 'data:image/png;base64,bm90LWltYWdl']))->assertSessionHasErrors('signature');
        $this->post($url, array_merge($valid, ['latitude' => 91]))->assertSessionHasErrors('latitude');
        $this->assertSame('OUT_FOR_DELIVERY', $shipment->fresh()->status);
        $this->assertDatabaseCount('local_delivery_proofs', 0);
    }

    public function test_active_assignment_ui_is_clear_and_owner_reassignment_remains_available(): void
    {
        [$tenant, $owner] = $this->tenantOwner('assignment-ux');
        $driver = $this->driver($tenant, 'assignment-ux-driver@test.local', 'UX-DRV-01');
        $replacement = $this->driver($tenant, 'assignment-ux-replacement@test.local', 'UX-DRV-02');
        $driver->update(['availability_status' => 'AVAILABLE']);
        $shipment = $this->shipment($tenant, 'ZL260812ASSIGNUX1');
        $this->ready($shipment);
        app(DriverAssignmentService::class)->assign($tenant, $shipment->fresh(), $driver, $owner->id);

        $page = $this->actingAs($owner)->get($this->url($tenant, "/admin/operations/{$shipment->operation->uuid}"));
        $page->assertOk()->assertSee('Conductor asignado')->assertSee($driver->user->name)->assertSee('UX-DRV-01')
            ->assertSee('BUSY')->assertSee('Reasignar conductor')->assertSee('Confirmar reasignación')
            ->assertDontSee('Asignar / reasignar')->assertDontSee('Sin conductor asignado.');

        $this->post($this->url($tenant, "/admin/local-shipments/{$shipment->uuid}/driver"), ['driver_uuid' => $replacement->uuid])->assertRedirect();
        $this->assertSame($replacement->id, $shipment->fresh()->activeDriverAssignment->driver_profile_id);
    }

    public function test_proof_validation_errors_are_visible_and_have_no_delivery_side_effects(): void
    {
        Storage::fake('local');
        [$tenant, $driverUser, $profile] = $this->driverTenant('pod-ux-validation');
        $owner = $this->member($tenant, 'owner', 'pod-ux-validation-owner@test.local');
        DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'PER_DELIVERY', 'settlement_frequency' => 'WEEKLY', 'amount_per_delivery' => 75, 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 40, 'created_at' => now()]);
        $shipment = $this->shipment($tenant, 'ZL260812PODUX001');
        $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
        $url = $this->url($tenant, "/driver/shipments/{$shipment->uuid}/proof");
        $base = ['received_by_name' => 'Receiver Preserved', 'receiver_type' => 'FAMILY', 'signature' => $this->signaturePayload(), 'latitude' => 25.6, 'longitude' => -100.3, 'accuracy_meters' => 5];

        $this->actingAs($driverUser)->from($url)->post($url, $base)->assertRedirect($url)->assertSessionHasErrors('photo');
        $this->get($url)->assertOk()->assertSee('No fue posible finalizar la entrega.')->assertSee('Selecciona una fotografía de evidencia.')
            ->assertSee('Receiver Preserved')->assertSee('value="FAMILY" selected', false)->assertSee('Evidencia pendiente:')
            ->assertSee('window.isSecureContext')->assertSee('Permiso de ubicación denegado.')->assertSee('Finalizando entrega...');

        $withoutGps = $base + ['photo' => UploadedFile::fake()->image('pod.jpg')];
        unset($withoutGps['latitude'], $withoutGps['longitude'], $withoutGps['accuracy_meters']);
        $this->post($url, $withoutGps)->assertSessionHasErrors(['latitude', 'longitude', 'accuracy_meters']);

        $this->assertDatabaseCount('local_delivery_proofs', 0);
        $this->assertDatabaseCount('driver_delivery_attributions', 0);
        $this->assertDatabaseCount('driver_earning_entries', 0);
        $this->assertSame('OUT_FOR_DELIVERY', $shipment->fresh()->status);
        $this->assertSame(0, DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->where('status', 'DELIVERED')->count());
        $this->assertSame(40, (int) DB::table('network_usage_events')->sum('quantity'));
    }

    public function test_failed_evidence_is_append_only_and_protected_pod_is_authorized(): void
    {
        Storage::fake('local');
        [$tenant, $driverUser, $profile] = $this->driverTenant('pod-access');
        $owner = $this->member($tenant, 'owner', 'pod-access-owner@test.local');
        DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'SALARIED', 'settlement_frequency' => 'WEEKLY', 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);
        $failedShipment = $this->shipment($tenant, 'ZL260812FAIL0001');
        $this->assignedOutForDelivery($tenant, $failedShipment, $profile, $owner, $driverUser);
        $failUrl = $this->url($tenant, "/driver/shipments/{$failedShipment->uuid}/failure");
        $this->actingAs($driverUser)->post($failUrl, ['reason' => 'RECIPIENT_ABSENT', 'notes' => 'No answer'])->assertRedirect(route('driver.dashboard'));
        $this->post($this->url($tenant, "/driver/shipments/{$failedShipment->uuid}/transition"), ['status' => 'OUT_FOR_DELIVERY'])->assertRedirect();
        $this->post($failUrl, ['reason' => 'OTHER', 'notes' => 'Second attempt'])->assertRedirect();
        $this->assertDatabaseCount('local_delivery_failed_attempts', 2);
        $this->assertDatabaseCount('driver_earning_entries', 0);

        $shipment = $this->shipment($tenant, 'ZL260812ACCESS01');
        $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
        $payload = ['received_by_name' => 'Private POD Name', 'receiver_type' => 'SECURITY', 'photo' => UploadedFile::fake()->image('pod.png'), 'signature' => $this->signaturePayload(), 'latitude' => 25.6, 'longitude' => -100.3, 'accuracy_meters' => 5];
        $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/proof"), $payload)->assertRedirect(route('driver.dashboard'));
        $proof = LocalDeliveryProof::firstOrFail();
        $this->assertDatabaseCount('driver_earning_entries', 0);
        $this->actingAs($owner)->get($this->url($tenant, "/admin/operations/{$shipment->operation->uuid}"))->assertOk()->assertSee('PRUEBA DE ENTREGA')->assertSee('Private POD Name');
        $this->get($this->url($tenant, "/admin/delivery-proofs/{$proof->uuid}/photo"))->assertOk();
        [$otherTenant, $otherOwner] = $this->tenantOwner('pod-access-other');
        $this->actingAs($otherOwner)->get($this->url($otherTenant, "/admin/delivery-proofs/{$proof->uuid}/photo"))->assertNotFound();
        $this->get($this->url($otherTenant, "/tracking/{$shipment->tracking_number}"))->assertNotFound();
        $this->actingAs($driverUser)->get($this->url($tenant, "/tracking/{$shipment->tracking_number}"))->assertOk()->assertDontSee('Private POD Name')->assertDontSee((string) $proof->latitude);

        $sysadmin = $this->user('pod-sysadmin@test.local');
        $role = DB::table('roles')->insertGetId(['name' => 'Sysadmin', 'slug' => 'sysadmin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users_roles')->insert(['user_id' => $sysadmin->id, 'roles_id' => $role]);
        $this->actingAs($sysadmin)->get("/network/delivery-proofs/{$proof->uuid}/signature")->assertOk();
        $this->get('/network/local-shipping')->assertOk()->assertSee($shipment->tracking_number)->assertSee('YES')->assertSee('2');
    }

    public function test_tenant_proof_options_defaults_validation_and_isolation(): void
    {
        [$tenant,$owner] = $this->tenantOwner('proof-options');
        [$other,$otherOwner] = $this->tenantOwner('proof-options-other');
        $url = $this->url($tenant, '/admin/configuracion/entregas');
        $base = ['name'=>'Firma al recibir','description'=>'Firma','require_signature'=>1,'receiver_policy'=>'RECIPIENT_ONLY','max_delivery_attempts'=>2,'surcharge_amount'=>5,'currency'=>'MXN','is_active'=>1,'sort_order'=>1];
        $this->actingAs($owner)->post($url, $base+['code'=>'SIGNATURE','is_default'=>1])->assertRedirect();
        $this->post($url, $base+['code'=>'PHOTO','name'=>'Foto','require_signature'=>0,'require_photo'=>1,'is_default'=>1])->assertRedirect();
        $this->assertSame(2, TenantDeliveryProofOption::where('tenant_id',$tenant->id)->count());
        $this->assertSame(1, TenantDeliveryProofOption::where('tenant_id',$tenant->id)->where('is_default',true)->where('is_active',true)->count());
        $this->post($url, ['code'=>'EMPTY','name'=>'Vacía','receiver_policy'=>'RECIPIENT_ONLY','max_delivery_attempts'=>2,'currency'=>'MXN','is_active'=>1,'sort_order'=>3])->assertSessionHasErrors('evidence');
        $foreign = TenantDeliveryProofOption::where('tenant_id',$tenant->id)->firstOrFail();
        $this->actingAs($otherOwner)->put($this->url($other, "/admin/configuracion/entregas/{$foreign->uuid}"), $base+['code'=>'HACK'])->assertNotFound();
        $foreign->update(['is_active'=>false]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(DeliveryRequirementService::class)->option($tenant, $foreign->uuid);
    }

    public function test_shipment_requirement_snapshot_is_frozen_and_immutable(): void
    {
        [$tenant] = $this->tenantOwner('proof-snapshot');
        $option = $this->proofOption($tenant, 'SIGNED', ['name'=>'Firma original','require_receiver_name'=>true,'require_signature'=>true,'receiver_policy'=>'AUTHORIZED_PERSON','max_delivery_attempts'=>3,'surcharge_amount'=>10]);
        $operation=TenantOperation::create(['tenant_id'=>$tenant->id,'subscription_id'=>$tenant->subscriptions()->first()->id,'channel'=>'b2c','status'=>'confirmed','provider'=>'ZIGO_LOCAL','service_code'=>'LOCAL64000SD','metadata'=>[]]);
        $shipment=app(LocalShipmentService::class)->create($tenant,$operation,['sender'=>['name'=>'A','address'=>'Origen','postal_code'=>'64000'],'recipient'=>['name'=>'B','address'=>'Destino','postal_code'=>'64000'],'package'=>['type'=>'sobre','weight'=>1],'pricing'=>['final_price'=>100,'currency'=>'MXN'],'delivery_proof_option_uuid'=>$option->uuid]);
        $snapshot = $shipment->deliveryRequirement;
        $option->update(['name'=>'Foto nueva','require_signature'=>false,'require_photo'=>true,'max_delivery_attempts'=>1,'surcharge_amount'=>99]);
        $snapshot->refresh();
        $this->assertSame('Firma original',$snapshot->proof_option_name_snapshot); $this->assertTrue($snapshot->require_signature); $this->assertFalse($snapshot->require_photo); $this->assertSame(3,$snapshot->max_delivery_attempts); $this->assertSame(10.0,(float)$snapshot->surcharge_amount_snapshot);
        $this->expectException(\LogicException::class); $snapshot->update(['require_photo'=>true]);
    }

    public function test_dynamic_receiver_signature_photo_and_gps_modalities(): void
    {
        Storage::fake('local');
        [$tenant,$driver,$profile] = $this->driverTenant('dynamic-pod'); $owner=$this->member($tenant,'owner','dynamic-pod-owner@test.local');
        $cases = [
            ['RECEIVER',['require_receiver_name'=>true],['received_by_name'=>'Persona']],
            ['SIGNATURE',['require_signature'=>true],['signature'=>$this->signaturePayload()]],
            ['GPS',['require_gps'=>true],['latitude'=>25.6,'longitude'=>-100.3,'accuracy_meters'=>4]],
            ['PHOTO',['require_photo'=>true],['photo'=>UploadedFile::fake()->image('pod.jpg')]],
        ];
        foreach($cases as $index=>[$code,$flags,$payload]) {
            TenantDeliveryProofOption::where('tenant_id',$tenant->id)->update(['is_default'=>false]);
            $option=$this->proofOption($tenant,$code,$flags+['is_default'=>true]);
            $shipment=$this->shipment($tenant,'ZL260813DYNAMIC'.($index+1)); $this->assignedOutForDelivery($tenant,$shipment,$profile,$owner,$driver);
            $this->actingAs($driver)->post($this->url($tenant,"/driver/shipments/{$shipment->uuid}/proof"),$payload)->assertRedirect(route('driver.dashboard'));
            $this->assertSame('DELIVERED',$shipment->fresh()->status);
        }
        $this->assertDatabaseCount('local_delivery_proofs',4);
    }

    public function test_required_evidence_receiver_policy_and_attempt_limit_are_enforced(): void
    {
        Storage::fake('local');
        [$tenant,$driver,$profile]=$this->driverTenant('pod-rules'); $owner=$this->member($tenant,'owner','pod-rules-owner@test.local');
        $full=$this->proofOption($tenant,'FULL',['require_receiver_name'=>true,'require_receiver_type'=>true,'require_signature'=>true,'require_photo'=>true,'require_gps'=>true,'receiver_policy'=>'RECIPIENT_ONLY','max_delivery_attempts'=>2,'is_default'=>true]);
        $shipment=$this->shipment($tenant,'ZL260813RULES001'); $this->assignedOutForDelivery($tenant,$shipment,$profile,$owner,$driver); $url=$this->url($tenant,"/driver/shipments/{$shipment->uuid}/proof");
        $valid=['received_by_name'=>'Persona','receiver_type'=>'RECIPIENT','signature'=>$this->signaturePayload(),'photo'=>UploadedFile::fake()->image('pod.jpg'),'latitude'=>25.6,'longitude'=>-100.3,'accuracy_meters'=>5];
        foreach(['signature','photo','latitude'] as $field){$payload=$valid;unset($payload[$field]);$this->actingAs($driver)->post($url,$payload)->assertSessionHasErrors($field);}
        $this->post($url,array_merge($valid,['receiver_type'=>'FAMILY']))->assertSessionHasErrors('receiver_type');
        $this->assertDatabaseCount('local_delivery_proofs',0);

        $attemptShipment=$this->shipment($tenant,'ZL260813ATTEMPT1'); $this->assignedOutForDelivery($tenant,$attemptShipment,$profile,$owner,$driver); app(DeliveryRequirementService::class)->snapshot($attemptShipment,$full);
        $fail=$this->url($tenant,"/driver/shipments/{$attemptShipment->uuid}/failure");
        $this->post($fail,['reason'=>'OTHER'])->assertSessionHasErrors('notes');
        $this->post($fail,['reason'=>'NO_ANSWER'])->assertRedirect(route('driver.dashboard'));
        $this->post($this->url($tenant,"/driver/shipments/{$attemptShipment->uuid}/transition"),['status'=>'OUT_FOR_DELIVERY'])->assertRedirect();
        $this->post($fail,['reason'=>'PROPERTY_APPEARS_ABANDONED','notes'=>'Sin actividad visible','latitude'=>25.61,'longitude'=>-100.31,'accuracy_meters'=>6])->assertRedirect(route('driver.dashboard'));
        $attempts=$attemptShipment->failedDeliveryAttempts()->orderBy('attempt_number')->get(); $this->assertSame([1,2],$attempts->pluck('attempt_number')->all());
        $this->post($this->url($tenant,"/driver/shipments/{$attemptShipment->uuid}/transition"),['status'=>'OUT_FOR_DELIVERY'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('driver_earning_entries',0); $this->assertSame('DELIVERY_FAILED',$attemptShipment->fresh()->status);
        $this->get($this->url($tenant,"/tracking/{$attemptShipment->tracking_number}"))->assertOk()->assertSee('Intento de entrega no completado')->assertDontSee('PROPERTY_APPEARS_ABANDONED')->assertDontSee('Sin actividad visible')->assertDontSee((string)$attempts->last()->latitude);
    }

    public function test_zn_07b_2_migration_is_reversible_in_isolation(): void
    {
        $migration=require database_path('migrations/2026_08_13_100000_create_configurable_delivery_proof_tables.php'); $migration->down();
        $this->assertFalse(Schema::hasTable('tenant_delivery_proof_options')); $this->assertFalse(Schema::hasTable('local_shipment_delivery_requirements')); $this->assertFalse(Schema::hasColumn('local_delivery_failed_attempts','attempt_number'));
    }

    public function test_zn_07a_2_migration_is_reversible_in_isolation(): void
    {
        $migration = require database_path('migrations/2026_08_11_100000_create_driver_presence_earnings_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('driver_earning_entries'));
        $this->assertFalse(Schema::hasTable('driver_delivery_attributions'));
        $this->assertFalse(Schema::hasTable('driver_compensation_policies'));
        $this->assertFalse(Schema::hasColumn('tenant_driver_profiles', 'last_seen_at'));
    }

    public function test_zn_07b_migration_is_reversible_in_isolation(): void
    {
        $migration = require database_path('migrations/2026_08_12_100000_create_driver_delivery_evidence_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('local_delivery_proofs'));
        $this->assertFalse(Schema::hasTable('local_delivery_failed_attempts'));
    }

    public function test_central_driver_host_auto_selects_single_operational_context_and_renders_surfaces(): void
    {
        [$tenant, $user, $profile] = $this->driverTenant('central-single');
        $host = config('zigo_driver.host');
        $this->actingAs($user)->get("http://{$host}/driver")
            ->assertOk()->assertSee('ZIGO DRIVER')->assertSee('Operando para')->assertSee($tenant->name)
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        $this->assertSame($profile->uuid, session(config('zigo_driver.context_session_key')));
        foreach (['deliveries' => 'Entregas', 'earnings' => 'Mis ganancias', 'profile' => 'Perfil', 'support' => 'Ayuda y soporte'] as $path => $copy) {
            $this->get("http://{$host}/driver/{$path}")->assertOk()->assertSee($copy);
        }
    }

    public function test_central_driver_renders_nested_addresses_and_uses_status_specific_navigation(): void
    {
        [$tenant, $user, $profile] = $this->driverTenant('central-nested-address');
        $owner = $this->member($tenant, 'owner', 'central-nested-owner@test.local');
        $shipment = $this->shipment($tenant, 'ZL-NESTED-ADDRESS');
        DB::table('local_shipments')->where('id', $shipment->id)->update([
            'sender_snapshot' => json_encode(['name' => 'Remitente', 'address' => ['address' => 'Av. Tamaulipas 10, Tamaulipas Sección Virgencitas, Nezahualcóyotl, México, CP 57300', 'settlement' => 'Tamaulipas Sección Virgencitas', 'postal_code' => '57300', 'municipality' => 'Nezahualcóyotl', 'state' => 'México']], JSON_UNESCAPED_UNICODE),
            'recipient_snapshot' => json_encode(['name' => 'Destinatario', 'phone' => '8112345678', 'address' => ['address' => 'Av. Juárez 20, Monterrey Centro, Monterrey, Nuevo León, CP 64000', 'settlement' => 'Monterrey Centro', 'postal_code' => '64000', 'municipality' => 'Monterrey', 'state' => 'Nuevo León']], JSON_UNESCAPED_UNICODE),
        ]);
        $shipment->refresh();
        $this->ready($shipment);
        app(DriverAssignmentService::class)->assign($tenant, $shipment->fresh(), $profile, $owner->id);
        $host = config('zigo_driver.host');
        $session = [config('zigo_driver.context_session_key') => $profile->uuid];

        $dashboardPickup = $this->actingAs($user)->withSession($session)->get("http://{$host}/driver")
            ->assertOk()->assertSee('Listo para recolectar')->assertSee('Tamaulipas Sección Virgencitas')->assertSee('CP 57300')
            ->assertSee('Google Maps')->assertSee('Waze')->assertSee('Apple Maps')->assertSee('Ver detalle');
        $this->assertStringContainsString(rawurlencode('Av. Tamaulipas 10, Tamaulipas Sección Virgencitas, Nezahualcóyotl, México, CP 57300'), $dashboardPickup->getContent());
        $pickup = $this->get("http://{$host}/driver/shipments/{$shipment->uuid}")
            ->assertOk()->assertSee('Tamaulipas Sección Virgencitas')->assertSee('CP 57300');
        $this->assertStringContainsString(rawurlencode('Av. Tamaulipas 10, Tamaulipas Sección Virgencitas, Nezahualcóyotl, México, CP 57300'), $pickup->getContent());

        app(LocalTrackingService::class)->transition($shipment->fresh(), 'PICKED_UP', $user->id);
        $dashboardDelivery = $this->get("http://{$host}/driver")
            ->assertOk()->assertSee('Recolectado')->assertSee('Monterrey Centro')->assertSee('CP 64000')
            ->assertSee('Google Maps')->assertSee('Waze')->assertSee('Apple Maps')->assertSee('Ver detalle');
        $this->assertStringContainsString(rawurlencode('Av. Juárez 20, Monterrey Centro, Monterrey, Nuevo León, CP 64000'), $dashboardDelivery->getContent());
        $delivery = $this->get("http://{$host}/driver/shipments/{$shipment->uuid}")
            ->assertOk()->assertSee('Monterrey Centro')->assertSee('CP 64000');
        $this->assertStringContainsString(rawurlencode('Av. Juárez 20, Monterrey Centro, Monterrey, Nuevo León, CP 64000'), $delivery->getContent());
    }

    public function test_tenant_driver_urls_redirect_to_the_central_portal_without_login_loop(): void
    {
        [$tenant] = $this->driverTenant('legacy-driver-redirect');
        $central = rtrim((string) config('zigo_driver.url'), '/');
        $tenantBase = 'http://'.$tenant->domains()->first()->domain;
        $this->get($tenantBase.'/driver')->assertRedirect($central.'/driver');
        $this->get($tenantBase.'/driver/login')->assertRedirect($central.'/driver/login');
        $this->get($tenantBase.'/driver/shipments/example-uuid')->assertRedirect($central.'/driver/shipments/example-uuid');
        $this->get($central.'/driver/login')->assertOk();
    }

    public function test_central_driver_context_rejects_arbitrary_profile_and_allows_only_owned_workspace(): void
    {
        [$first, $user, $firstProfile] = $this->driverTenant('central-first');
        $second = $this->tenant('central-second');
        $second->memberships()->create(['user_id' => $user->id, 'role' => 'driver', 'status' => 'active']);
        $secondProfile = DriverProfile::create(['tenant_id' => $second->id, 'user_id' => $user->id, 'code' => 'CENTRAL-2', 'status' => 'ACTIVE']);
        $host = config('zigo_driver.host');

        $this->actingAs($user)->withSession([config('zigo_driver.context_session_key') => (string) \Illuminate\Support\Str::uuid()])
            ->get("http://{$host}/driver")->assertRedirect(route('driver.workspaces.index'));
        $this->get("http://{$host}/driver/workspaces")->assertOk()->assertSee($first->name)->assertSee($second->name);
        $this->post("http://{$host}/driver/workspaces", ['profile' => (string) \Illuminate\Support\Str::uuid()])->assertNotFound();
        $this->post("http://{$host}/driver/workspaces", ['profile' => $secondProfile->uuid])->assertRedirect(route('driver.dashboard'));
        $this->assertSame($secondProfile->uuid, session(config('zigo_driver.context_session_key')));
        $this->assertNotSame($firstProfile->uuid, session(config('zigo_driver.context_session_key')));
    }

    public function test_central_driver_logout_clears_active_workspace(): void
    {
        [, $user, $profile] = $this->driverTenant('central-logout');
        $host = config('zigo_driver.host');
        $this->actingAs($user)->withSession([config('zigo_driver.context_session_key') => $profile->uuid])
            ->post("http://{$host}/driver/logout")->assertRedirect(route('driver.login'));
        $this->assertGuest();
        $this->assertNull(session(config('zigo_driver.context_session_key')));
    }

    private function driverTenant(string $slug, bool $entitlement = true, string $subscriptionStatus = 'active'): array
    {
        $tenant = $this->tenant($slug, $entitlement, $subscriptionStatus);
        $user = $this->member($tenant, 'driver', $slug.'@test.local');
        $profile = DriverProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'code' => 'DRV-'.$tenant->id, 'status' => 'ACTIVE']);

        return [$tenant, $user, $profile];
    }

    private function tenantOwner(string $slug, bool $driver = true): array
    {
        $tenant = $this->tenant($slug, $driver);

        return [$tenant, $this->member($tenant, 'owner', $slug.'@owner.test')];
    }

    private function tenant(string $slug, bool $driver = true, string $status = 'active'): Tenant
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $tenant->domains()->create(['domain' => $slug.'.zigo.local', 'type' => 'subdomain', 'environment' => 'production', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $plan = Plan::create(['code' => strtoupper($slug), 'name' => $slug, 'status' => 'active', 'currency' => 'MXN', 'included_operations' => 300]);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status, 'operations_limit' => 300, 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        foreach (array_filter(['B2C', 'SHIPPING', 'TRACKING', $driver ? 'DRIVER' : null]) as $code) {
            $module = Module::firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'addon', 'is_active' => true, 'sort_order' => 1]);
            Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']);
        }

        return $tenant;
    }

    private function member(Tenant $tenant, string $role, string $email): User
    {
        $user = $this->user($email);
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active']);

        return $user;
    }

    private function driver(Tenant $tenant, string $email, string $code): DriverProfile
    {
        $user = $this->member($tenant, 'driver', $email);

        return DriverProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'code' => $code, 'status' => 'ACTIVE']);
    }

    private function user(string $email): User
    {
        return User::forceCreate(['name' => 'Driver '.$email, 'email' => $email, 'password' => Hash::make('tenant-secret'), 'empresa_id' => 1]);
    }

    private function shipment(Tenant $tenant, ?string $tracking = null): LocalShipment
    {
        $operation = TenantOperation::create(['tenant_id' => $tenant->id, 'subscription_id' => $tenant->subscriptions()->first()->id, 'channel' => 'b2c', 'status' => 'confirmed', 'provider' => 'ZIGO_LOCAL', 'service_code' => 'LOCAL64000SD', 'metadata' => []]);
        $shipment = LocalShipment::create(['tenant_id' => $tenant->id, 'tenant_operation_id' => $operation->id, 'tracking_number' => $tracking ?? 'ZL'.str_pad((string) $operation->id, 14, '0', STR_PAD_LEFT), 'service_code' => 'LOCAL64000SD', 'status' => 'CREATED', 'sender_snapshot' => ['name' => 'Private Sender', 'address' => 'Origin 1', 'postal_code' => '64000'], 'recipient_snapshot' => ['name' => 'Private Recipient', 'address' => 'Destination 2', 'phone' => '8112345678', 'postal_code' => '64000'], 'package_snapshot' => ['type' => 'sobre', 'weight' => 1], 'pricing_snapshot' => ['base_cost' => 80, 'final_price' => 120], 'guide_snapshot' => ['reference' => 'REF-PRIVATE']]);
        $shipment->events()->create(['status' => 'CREATED', 'event_code' => 'SHIPMENT_CREATED', 'occurred_at' => now()]);

        return $shipment;
    }

    private function ready(LocalShipment $shipment): void
    {
        app(LocalTrackingService::class)->transition($shipment, 'READY_FOR_PICKUP');
    }

    private function assignedOutForDelivery(Tenant $tenant, LocalShipment $shipment, DriverProfile $profile, User $owner, User $driverUser): DriverAssignment
    {
        $this->ready($shipment);
        $assignment = app(DriverAssignmentService::class)->assign($tenant, $shipment->fresh(), $profile, $owner->id);
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            app(LocalTrackingService::class)->transition($shipment->fresh(), $status, $driverUser->id);
        }

        return $assignment;
    }

    private function deliverWithProof(DriverAssignment $assignment, User $driver): void
    {
        $proof = LocalDeliveryProof::create(['tenant_id' => $assignment->tenant_id, 'local_shipment_id' => $assignment->local_shipment_id, 'driver_assignment_id' => $assignment->id, 'driver_profile_id' => $assignment->driver_profile_id, 'received_by_name' => 'Test Receiver', 'receiver_type' => 'RECIPIENT', 'photo_path' => 'tests/photo.png', 'signature_path' => 'tests/signature.png', 'latitude' => 25.6866, 'longitude' => -100.3161, 'accuracy_meters' => 10, 'captured_at' => now(), 'created_at' => now()]);
        app(DriverDeliveryService::class)->deliver($assignment, $driver->id, $proof->id);
    }

    private function signaturePayload(): string
    {
        $image = imagecreatetruecolor(120, 60);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        imageline($image, 10, 40, 110, 20, $black);
        ob_start(); imagepng($image); $binary = ob_get_clean(); imagedestroy($image);
        return 'data:image/png;base64,'.base64_encode($binary);
    }

    private function proofOption(Tenant $tenant, string $code, array $overrides=[]): TenantDeliveryProofOption
    {
        return TenantDeliveryProofOption::create(array_merge(['tenant_id'=>$tenant->id,'code'=>$code,'name'=>$code,'require_receiver_name'=>false,'require_receiver_type'=>false,'require_signature'=>false,'require_photo'=>false,'require_gps'=>false,'receiver_policy'=>'ANY_PERSON_AT_ADDRESS','max_delivery_attempts'=>2,'surcharge_amount'=>0,'currency'=>'MXN','is_default'=>false,'is_active'=>true,'sort_order'=>0],$overrides));
    }

    private function url(Tenant $tenant, string $path): string
    {
        if ($path === '/driver' || str_starts_with($path, '/driver/')) {
            return 'http://'.config('zigo_driver.host').$path;
        }

        return 'http://'.$tenant->domains()->first()->domain.$path;
    }

    private function schema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->unsignedBigInteger('empresa_id');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->timestamps();
        });
        Schema::create('users_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('roles_id');
        });
        Schema::create('network_tenants', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug');
            $t->string('status');
            $t->unsignedBigInteger('current_plan_id')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_domains', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('domain');
            $t->string('type');
            $t->string('environment');
            $t->boolean('is_primary');
            $t->string('status');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_brandings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('brand_name')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('primary_color')->nullable();
            $t->string('secondary_color')->nullable();
            $t->string('accent_color')->nullable();
            $t->string('favicon_path')->nullable();
            $t->string('support_email')->nullable();
            $t->string('support_phone')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenant_memberships', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role');
            $t->string('status');
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id']);
        });
        Schema::create('network_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
            $t->string('status');
            $t->string('currency');
            $t->unsignedInteger('included_operations')->nullable();
            $t->timestamps();
        });
        Schema::create('network_modules', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type');
            $t->boolean('is_active');
            $t->integer('sort_order');
            $t->timestamps();
        });
        Schema::create('network_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('plan_id');
            $t->string('status');
            $t->integer('operations_limit')->nullable();
            foreach (['started_at', 'current_period_start', 'current_period_end', 'trial_ends_at', 'grace_ends_at', 'canceled_at', 'ended_at'] as $c) {
                $t->timestamp($c)->nullable();
            }$t->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('subscription_id');
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('module_id');
            $t->string('code');
            $t->boolean('is_enabled');
            $t->integer('limit_value')->nullable();
            $t->string('source');
            $t->timestamps();
        });
        Schema::create('network_tenant_operations', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('subscription_id')->nullable();
            $t->string('channel');
            $t->string('status');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('provider')->nullable();
            $t->string('service_code')->nullable();
            $t->string('external_reference')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('network_usage_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('metric');
            $t->integer('quantity');
            $t->timestamp('created_at')->nullable();
        });
        (require database_path('migrations/2026_08_09_100000_create_local_shipping_foundation_tables.php'))->up();
        (require database_path('migrations/2026_08_10_100000_create_driver_last_mile_tables.php'))->up();
        (require database_path('migrations/2026_08_11_100000_create_driver_presence_earnings_tables.php'))->up();
        (require database_path('migrations/2026_08_12_100000_create_driver_delivery_evidence_tables.php'))->up();
        (require database_path('migrations/2026_08_13_100000_create_configurable_delivery_proof_tables.php'))->up();
    }
}
