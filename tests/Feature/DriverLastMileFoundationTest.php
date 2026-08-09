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
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    public function test_driver_capability_requires_entitlement_and_operational_subscription(): void
    {
        [$without,$owner] = $this->tenantOwner('without-driver', false);
        $this->actingAs($owner)->get($this->url($without, '/admin/drivers'))->assertForbidden();
        [$suspended,$driverUser,$profile] = $this->driverTenant('suspended-driver', true, 'suspended');
        $this->actingAs($driverUser)->get($this->url($suspended, '/driver'))->assertStatus(503);
        $profile->update(['status' => 'INACTIVE']);
        [$active,$activeDriver,$activeProfile] = $this->driverTenant('inactive-profile');
        $activeProfile->update(['status' => 'INACTIVE']);
        $this->actingAs($activeDriver)->get($this->url($active, '/driver'))->assertNotFound();
    }

    public function test_driver_login_reuses_user_identity_and_rejects_non_driver_membership(): void
    {
        [$tenant,$driverUser] = $this->driverTenant('driver-login');
        $this->post($this->url($tenant, '/driver/login'), ['email' => $driverUser->email, 'password' => 'tenant-secret'])->assertRedirect(route('tenant.driver.dashboard'));
        $this->post($this->url($tenant, '/driver/logout'))->assertRedirect(route('tenant.driver.login'));
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
        $this->get($this->url($tenant, "/driver/shipments/{$own->uuid}"))->assertOk()->assertSee('Private Recipient')->assertDontSee('80.00');
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
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED'] as $status) {
            $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition"), ['status' => $status])->assertRedirect();
        }
        $before = DB::table('local_tracking_events')->count();
        $this->post($this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition"), ['status' => 'DELIVERED'])->assertNotFound();
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
        $url = $this->url($tenant, "/admin/operations/{$shipment->operation->uuid}/pickup-request");

        $this->actingAs($operator)->post($url, ['status' => 'DELIVERED', 'driver_uuid' => '00000000-0000-0000-0000-000000000000', 'tenant_id' => 999])->assertRedirect();
        $this->assertSame('READY_FOR_PICKUP', $shipment->fresh()->status);
        $events = DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count();
        $this->post($url)->assertRedirect();
        $this->assertSame($events, DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->count());
        $this->get($this->url($tenant, '/admin/dispatch/pickups'))->assertOk()->assertSee($shipment->tracking_number)->assertSee('Sin asignar');
        $driver = $this->driver($tenant, 'pickup-driver@test.local', 'PICKUP-DRV');
        $this->post($this->url($tenant, "/admin/local-shipments/{$shipment->uuid}/driver"), ['driver_uuid' => $driver->uuid])->assertRedirect();
        $this->assertSame($driver->id, $shipment->fresh()->activeDriverAssignment->driver_profile_id);
        $this->get($this->url($tenant, '/admin/dispatch/pickups'))->assertOk()->assertSee('Asignado')->assertSee('pickup-driver@test.local');
        $this->get($this->url($tenant, "/tracking/{$shipment->tracking_number}"))->assertOk()->assertSee('READY FOR PICKUP');
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
        $this->actingAs($other->user)->get($this->url($tenant, "/driver/shipments/{$shipment->uuid}"))->assertNotFound();
    }

    public function test_delivery_attribution_and_compensation_ledger_are_immutable_idempotent_and_usage_free(): void
    {
        [$tenant, $driverUser, $profile] = $this->driverTenant('earnings');
        $owner = $this->member($tenant, 'owner', 'earnings-owner@test.local');
        DB::table('network_usage_events')->insert(['tenant_id' => $tenant->id, 'metric' => 'operations', 'quantity' => 1, 'created_at' => now()]);
        $policy = DriverCompensationPolicy::create(['tenant_id' => $tenant->id, 'driver_profile_id' => $profile->id, 'compensation_type' => 'SALARIED', 'settlement_frequency' => 'WEEKLY', 'currency' => 'MXN', 'updated_by_user_id' => $owner->id]);

        $salaryShipment = $this->shipment($tenant, 'ZL260811SALARY01');
        $salaryAssignment = $this->assignedOutForDelivery($tenant, $salaryShipment, $profile, $owner, $driverUser);
        app(DriverDeliveryService::class)->deliver($salaryAssignment, $driverUser->id);
        $this->assertDatabaseCount('driver_delivery_attributions', 1);
        $this->assertDatabaseCount('driver_earning_entries', 0);

        foreach ([['PER_DELIVERY', 75], ['HYBRID', 35]] as [$type, $amount]) {
            $policy->update(['compensation_type' => $type, 'amount_per_delivery' => $amount]);
            $shipment = $this->shipment($tenant, 'ZL260811'.str_replace('_', '', $type));
            $assignment = $this->assignedOutForDelivery($tenant, $shipment, $profile, $owner, $driverUser);
            app(DriverDeliveryService::class)->deliver($assignment, $driverUser->id);
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
        app(DriverDeliveryService::class)->deliver($assignment, $driverUser->id);
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
            app(DriverDeliveryService::class)->deliver($assignment, $profile->user_id);
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
        app(DriverDeliveryService::class)->deliver($otherAssignment, $otherDriver->user_id);

        $url = $this->url($tenant, "/driver/shipments/{$shipment->uuid}/transition");
        $response = $this->actingAs($driverUser)->post($url, ['status' => 'DELIVERED']);
        $response->assertRedirect(route('tenant.driver.dashboard'))->assertSessionHas('success', 'Entrega completada correctamente.');
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
        $this->post($url, ['status' => 'DELIVERED'])->assertNotFound();
        $this->assertSame($counts, [DriverDeliveryAttribution::count(), DriverEarningEntry::count(), DB::table('local_tracking_events')->where('local_shipment_id', $shipment->id)->where('status', 'DELIVERED')->count(), DB::table('network_usage_events')->sum('quantity')]);

        [$otherTenant, $otherOwner] = $this->tenantOwner('terminal-other-tenant');
        $otherTenantDriver = $this->driver($otherTenant, 'terminal-cross-tenant@test.local', 'CROSS-TENANT');
        $crossTenantShipment = $this->shipment($otherTenant, 'ZL260812OTHERTEN1');
        DriverDeliveryAttribution::create(['tenant_id' => $otherTenant->id, 'local_shipment_id' => $crossTenantShipment->id, 'driver_assignment_id' => DriverAssignment::create(['tenant_id' => $otherTenant->id, 'local_shipment_id' => $crossTenantShipment->id, 'driver_profile_id' => $otherTenantDriver->id, 'assigned_by_user_id' => $otherOwner->id, 'assigned_at' => now(), 'status' => 'COMPLETED'])->id, 'driver_profile_id' => $otherTenantDriver->id, 'driver_user_id' => $otherTenantDriver->user_id, 'driver_code_snapshot' => $otherTenantDriver->code, 'delivered_at' => now(), 'created_at' => now()]);
        $this->get($this->url($tenant, '/driver'))->assertOk()->assertDontSee($crossTenantShipment->tracking_number);
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

    private function url(Tenant $tenant, string $path): string
    {
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
    }
}
