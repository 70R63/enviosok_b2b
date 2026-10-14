<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderPayable;
use App\Domain\Network\Providers\Models\ProviderPayableEvent;
use App\Domain\Network\Providers\Models\ProviderSettlementEntry;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderDriverExecutionService;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderNetworkReadinessService;
use App\Domain\Network\Providers\ProviderPayableService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\ProviderSettlementService;
use App\Domain\Network\Providers\ProviderTrackingProjection;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Http\Controllers\B2C\ProviderFulfillmentController;
use App\Models\B2cCotizacion;
use App\Models\Roles\Roles;
use App\Models\User;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderApiFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\Support\ProviderTrackingFixture;
use Tests\TestCase;

final class NetworkProviderSettlementTest extends TestCase
{
    private array $f;

    private User $admin;

    private ProviderFulfillment $bridge;

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
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        ProviderTrackingFixture::grantDriver($this->f);
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
        $this->admin = User::create(['name' => 'Financial Admin', 'email' => 'finance@example.test', 'password' => 'unused']);
        $role = Roles::create(['slug' => 'sysadmin']);
        $this->admin->roles()->attach($role->id);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $this->admin->id, 'role' => 'operator', 'status' => 'active']);
        app(TenantContext::class)->set($this->f['consumer']);
        session()->put('cotizacion_publica_id', $this->f['quote']->id);
        $option = app(ProviderQuotePublisher::class)->publish($this->f['quote'], app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($this->f['quote'], $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $this->f['quote']->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
            'payment_verified_amount' => 145, 'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->f['quote']->id])->save());
        $this->bridge = app(ProviderFulfillmentService::class)->fulfill($this->f['quote']);
    }

    private function payable(): ProviderPayable
    {
        return ProviderPayable::firstOrFail();
    }

    private function payment(string $amount = '50.00', string $reference = 'PAY-ONE', string $key = 'manual-key-one'): array
    {
        return ['amount' => $amount, 'currency' => 'MXN', 'payment_reference' => $reference, 'idempotency_key' => $key, 'paid_at' => now()->subMinute()->format('Y-m-d H:i:00'), 'notes' => 'Net allocation reconciled'];
    }

    private function settle(array $data): ProviderSettlementEntry
    {
        return app(ProviderSettlementService::class)->settle($this->admin, $this->payable()->uuid, $data);
    }

    private function accept(): void
    {
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->admin, 'ACCEPTED');
    }

    private function outbound()
    {
        $this->accept();
        $driver = ProviderTrackingFixture::driver($this->f['provider']);
        $assignment = app(DriverAssignmentService::class)->assign($this->f['provider'], $this->bridge->shipment, $driver, $this->admin->id);
        foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            app(ProviderDriverExecutionService::class)->transition($assignment, $driver->user_id, $status);
        }

        return [$assignment, $driver];
    }

    private function delivered(): void
    {
        [$a,$d] = $this->outbound();
        app(ProviderDriverExecutionService::class)->deliver($a, $d->user_id, ProviderTrackingFixture::proofData(), UploadedFile::fake()->image('pod.jpg'), ProviderTrackingFixture::signature());
    }

    public function test_fulfillment_creates_one_pending_payable_and_duplicate_events_do_not_duplicate(): void
    {
        for ($i = 0; $i < 3; $i++) {
            app(ProviderPayableService::class)->sync($this->bridge);
        }
        app(ProviderFulfillmentService::class)->fulfill($this->f['quote']);
        $this->assertDatabaseCount('network_provider_payables', 1);
        $this->assertDatabaseCount('network_provider_payable_events', 1);
        $this->assertSame('PENDING', $this->payable()->financialStatus());
        $this->assertSame('100.00', $this->payable()->provider_net_amount);
    }

    public function test_snapshot_cost_currency_and_provider_tax_are_frozen_without_invented_vat(): void
    {
        $this->f['tariff']->update(['amount' => '999.00']);
        $this->f['commercial']->update(['margin_percentage' => 99]);
        app(ProviderPayableService::class)->sync($this->bridge);
        $p = $this->payable();
        $this->assertSame('100.00', $p->provider_net_amount);
        $this->assertNull($p->provider_tax_amount);
        $this->assertNull($p->provider_gross_amount);
        $this->assertSame('MXN', $p->currency);
        $this->assertSame('125.00', $p->snapshot->subtotal_amount);
        $this->assertSame('20.00', $p->snapshot->tax_amount);
        $this->assertSame('145.00', $p->snapshot->total_amount);
        $this->assertSame('25.00', $p->snapshot->profit_amount);
    }

    public function test_accepted_is_in_service_and_repeated_acceptance_idempotent(): void
    {
        $this->accept();
        $this->accept();
        $this->assertSame('IN_SERVICE', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_payable_events', 2);
    }

    public function test_rejected_is_review_without_deleting_obligation_or_checkout(): void
    {
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->admin, 'REJECTED', 'No capacity');
        $this->assertSame('REVIEW', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_payables', 1);
        $this->assertSame('READY_FOR_PICKUP', $this->bridge->shipment->fresh()->status);
        $this->assertSame('GUIA_GENERADA', $this->f['quote']->fresh()->estatus);
    }

    public function test_delivered_is_payable_not_settled_and_projection_repeat_is_idempotent(): void
    {
        $this->delivered();
        for ($i = 0; $i < 3; $i++) {
            app(ProviderPayableService::class)->sync($this->bridge);
        }
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_settlement_entries', 0);
        $this->assertDatabaseCount('network_provider_payable_events', 3);
    }

    public function test_delivery_failed_is_review_without_zeroing_cost_refund_or_cancellation(): void
    {
        [$a,$d] = $this->outbound();
        app(ProviderDriverExecutionService::class)->fail($a, $d->user_id, ['reason' => 'NO_ANSWER']);
        $this->assertSame('REVIEW', $this->payable()->financialStatus());
        $this->assertSame('100.00', $this->payable()->remainingAmount());
        $this->assertSame('GUIA_GENERADA', $this->f['quote']->fresh()->estatus);
        $this->assertDatabaseCount('network_provider_settlement_entries', 0);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_partial_then_full_settlement_preserves_economy_and_audit(): void
    {
        $this->delivered();
        $before = $this->payable()->snapshot->getAttributes();
        $entry = $this->settle($this->payment());
        $this->assertSame('50.00', $this->payable()->remainingAmount());
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        $this->settle($this->payment('50.00', 'PAY-TWO', 'manual-key-two'));
        $this->assertSame('0.00', $this->payable()->remainingAmount());
        $this->assertSame('100.00', $this->payable()->settledAmount());
        $this->assertSame('SETTLED', $this->payable()->financialStatus());
        $this->assertSame($before, $this->payable()->snapshot->fresh()->getAttributes());
        $this->assertSame($this->admin->id, (int) $entry->actor_user_id);
        $this->assertNotNull($entry->created_at);
        $this->assertDatabaseCount('network_admin_audit_events', 2);
        $this->assertDatabaseCount('network_provider_payable_events', 5);
        app(ProviderPayableService::class)->sync($this->bridge);
        $this->assertSame('SETTLED', $this->payable()->financialStatus());
    }

    public function test_repeated_submit_and_same_reference_reuse_are_idempotent(): void
    {
        $this->delivered();
        $data = $this->payment();
        $first = $this->settle($data);
        $second = $this->settle($data);
        $data['idempotency_key'] = 'another-key-123';
        $third = $this->settle($data);
        $this->assertSame($first->uuid, $second->uuid);
        $this->assertSame($first->uuid, $third->uuid);
        $this->assertDatabaseCount('network_provider_settlement_entries', 1);
        $this->assertDatabaseCount('network_admin_audit_events', 1);
    }

    public function test_same_key_changed_payload_conflicts(): void
    {
        $this->delivered();
        $data = $this->payment();
        $this->settle($data);
        $data['amount'] = '40.00';
        $this->expectException(HttpException::class);
        $this->settle($data);
    }

    public function test_same_reference_changed_amount_conflicts(): void
    {
        $this->delivered();
        $this->settle($this->payment());
        $this->expectException(HttpException::class);
        $this->settle($this->payment('40.00', 'PAY-ONE', 'other-reference-key'));
    }

    public static function invalidPayments(): array
    {
        return [['101.00', 'MXN'], ['50.00', 'USD'], ['0.00', 'MXN'], ['-1.00', 'MXN'], ['1.001', 'MXN'], ['NaN', 'MXN']];
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_overpayment_currency_and_decimal_are_rejected(string $amount, string $currency): void
    {
        $this->delivered();
        $data = $this->payment($amount);
        $data['currency'] = $currency;
        try {
            $this->settle($data);
            $this->fail('Invalid payment accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('network_provider_settlement_entries', 0);
            $this->assertSame('100.00', $this->payable()->remainingAmount());
        }
    }

    public function test_pending_cannot_be_settled(): void
    {
        $this->expectException(ValidationException::class);
        $this->settle($this->payment());
    }

    public function test_review_cannot_be_settled_automatically_and_retains_full_obligation(): void
    {
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->admin, 'REJECTED');
        try {
            $this->settle($this->payment());
            $this->fail('REVIEW settled without financial resolution');
        } catch (ValidationException) {
            $this->assertSame('100.00', $this->payable()->remainingAmount());
        }
    }

    public function test_browser_cannot_replace_payable_owner_cost_or_status(): void
    {
        $this->delivered();
        $input = $this->payment() + ['provider_tenant_id' => 999, 'consumer_tenant_id' => 999, 'provider_net_amount' => '1.00', 'status' => 'SETTLED'];
        $this->actingAs($this->admin)->post('/network/provider-settlements/'.$this->payable()->uuid, $input)->assertRedirect();
        $this->assertSame('100.00', $this->payable()->provider_net_amount);
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        $this->assertSame('50.00', $this->payable()->remainingAmount());
    }

    public function test_financial_readiness_passes_with_manual_mode_no_banking_or_crm_configuration(): void
    {
        $report = app(ProviderNetworkReadinessService::class)->evaluate($this->f['consumer'], $this->f['provider'], $this->f['connection']);
        $check = collect($report['checks'])->firstWhere('code', 'settlement.infrastructure');
        $this->assertSame('PASS', $check['status']);
        $this->assertTrue($report['ready']);
        $this->assertDatabaseCount('network_provider_settlement_entries', 0);
        $this->assertDatabaseCount('network_provider_integrations', 0);
    }

    public function test_precise_cents_without_float_accumulation(): void
    {
        $this->delivered();
        $this->settle($this->payment('0.10', 'CENT-1', 'cent-one-123'));
        $this->settle($this->payment('0.20', 'CENT-2', 'cent-two-123'));
        $this->assertSame('0.30', $this->payable()->settledAmount());
        $this->assertSame('99.70', $this->payable()->remainingAmount());
    }

    public static function immutableModels(): array
    {
        return [['payable', 'save'], ['payable', 'bulk'], ['payable', 'delete'], ['entry', 'save'], ['entry', 'bulk'], ['entry', 'delete'], ['event', 'save'], ['event', 'bulk'], ['event', 'delete']];
    }

    #[DataProvider('immutableModels')]
    public function test_financial_history_is_not_mutable(string $kind, string $method): void
    {
        $this->delivered();
        $this->settle($this->payment('100.00'));
        $model = match ($kind) {
            'payable' => $this->payable(),'entry' => ProviderSettlementEntry::firstOrFail(),'event' => ProviderPayableEvent::firstOrFail()
        };
        $this->expectException(\LogicException::class);
        match ($method) {
            'save' => $model->forceFill(['created_at' => now()->addMinute()])->save(),'bulk' => $model->newQuery()->whereKey($model->id)->update(['created_at' => now()]),'delete' => $model->delete()
        };
    }

    public function test_network_ui_admin_can_read_filter_and_register_but_guest_and_tenant_users_cannot(): void
    {
        $this->delivered();
        $p = $this->payable();
        $this->get('/network/provider-settlements/'.$p->uuid)->assertRedirect('/network/login');
        foreach (['consumer', 'provider'] as $side) {
            $u = User::create(['name' => $side, 'email' => $side.'@example.test', 'password' => 'unused']);
            TenantMembership::create(['tenant_id' => $this->f[$side]->id, 'user_id' => $u->id, 'role' => 'owner', 'status' => 'active']);
            $this->actingAs($u)->get('/network/provider-settlements/'.$p->uuid)->assertForbidden();
            $this->post('/network/provider-settlements/'.$p->uuid, $this->payment())->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/network/provider-settlements?provider_uuid='.$this->f['provider']->uuid.'&status=PAYABLE')->assertOk()->assertSee('100.00');
        $this->get('/network/provider-settlements/'.$p->uuid)->assertOk()->assertSee('125.00')->assertSee('25.00');
        $this->post('/network/provider-settlements/'.$p->uuid, $this->payment())->assertRedirect();
        $this->assertSame('50.00', $this->payable()->remainingAmount());
    }

    public function test_other_consumer_or_provider_has_no_financial_access_and_no_api_settled_route(): void
    {
        $u = User::create(['name' => 'Other tenant user', 'email' => 'other@example.test', 'password' => 'unused']);
        $tenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $u->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($u)->get('/network/provider-settlements/'.$this->payable()->uuid)->assertForbidden();
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'api.hub.v1.provider.')) {
                $this->assertDoesNotMatchRegularExpression('/settle|payout|refund|cancel/', $route->uri());
            }
        }
    }

    public function test_payable_not_added_to_consumer_projection_or_guest_tracking(): void
    {
        $projection = app(ProviderTrackingProjection::class)->project($this->bridge, $this->f['consumer']);
        $json = json_encode($projection);
        foreach (['provider_net_amount', 'provider_cost', 'profit_amount', 'settled', 'payment_reference', 'payable'] as $field) {
            $this->assertStringNotContainsString($field, $json);
        }
        $this->assertSame($this->f['consumer']->id, app(TenantContext::class)->id());
    }

    public function test_customer_and_guest_html_never_expose_financial_ledger(): void
    {
        $this->delivered();
        $this->settle($this->payment('50.00', 'PRIVATE-SETTLEMENT-REFERENCE'));
        $controller = app(ProviderFulfillmentController::class);
        $customer = $controller->show($this->f['quote']->fresh())->getContent();
        $recovery = app(GuideRecoveryService::class);
        $token = $recovery->issue($this->f['quote']->fresh(), 'guest@example.test');
        $guest = $controller->recovered($recovery->resolve($token), $token)->getContent();
        foreach ([$customer, $guest] as $html) {
            foreach (['provider_net_amount', 'profit_amount', 'PRIVATE-SETTLEMENT-REFERENCE', 'Liquidaciones provider', $this->payable()->uuid] as $private) {
                $this->assertStringNotContainsString($private, $html);
            }
        }
    }

    public function test_recovery_is_idempotent_and_repairs_crash_gap_without_crm_or_money(): void
    {
        DB::table('network_provider_payable_events')->delete();
        DB::table('network_provider_payables')->delete();
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('network:provider-payables:reconcile')->assertSuccessful();
        }
        $this->assertDatabaseCount('network_provider_payables', 1);
        $this->assertDatabaseCount('network_provider_payable_events', 1);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries', 0);
        $this->assertDatabaseCount('network_provider_settlement_entries', 0);
    }

    public function test_missed_delivered_projection_is_repaired_without_duplicating_payments(): void
    {
        $this->delivered();
        DB::table('network_provider_payable_events')->where('action', 'DELIVERED')->delete();
        $this->artisan('network:provider-payables:reconcile')->assertSuccessful();
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_payable_events', 3);
    }

    public function test_recovery_of_older_acceptance_does_not_regress_delivered_financial_status(): void
    {
        $this->delivered();
        DB::table('network_provider_payable_events')->where('action', 'ACCEPTED')->delete();
        $this->artisan('network:provider-payables:reconcile')->assertSuccessful();
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        $this->assertDatabaseHas('network_provider_payable_events', ['action' => 'RECONCILED', 'status' => 'PAYABLE']);
        $count = ProviderPayableEvent::count();
        $this->artisan('network:provider-payables:reconcile')->assertSuccessful();
        $this->assertSame($count, ProviderPayableEvent::count());
    }

    public function test_schema_rollback_with_history_is_rejected_before_ddl(): void
    {
        $migration = require database_path('migrations/2026_10_13_150000_create_provider_payable_ledger.php');
        try {
            $migration->down();
            $this->fail('History erased');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No changes applied', $e->getMessage());
            $this->assertDatabaseCount('network_provider_payables', 1);
        }
    }

    public function test_empty_schema_rollback_and_reapply_preserve_operational_bridge(): void
    {
        DB::table('network_provider_payable_events')->delete();
        DB::table('network_provider_payables')->delete();
        $migration = require database_path('migrations/2026_10_13_150000_create_provider_payable_ledger.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('network_provider_payables'));
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $migration->up();
        app(ProviderPayableService::class)->sync($this->bridge);
        $this->assertDatabaseCount('network_provider_payables', 1);
    }

    public function test_business_rollback_has_no_financial_side_effects(): void
    {
        $this->accept();
        DB::beginTransaction();
        try {
            app(ProviderPayableService::class)->event($this->payable(), 'test-rolled-back', 'TEST', 'REVIEW', $this->admin->id, 'SYSTEM', now());
        } finally {
            DB::rollBack();
        }
        $this->assertSame('IN_SERVICE', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_payable_events', 2);
    }

    public function test_financial_routes_have_network_security_no_destructive_commands(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'network.provider-settlements.')) {
                foreach (['web', 'network.auth', 'network.superadmin', 'network.two-factor'] as $m) {
                    $this->assertContains($m, $route->gatherMiddleware());
                }
                $this->assertDoesNotMatchRegularExpression('/cancel|refund|delete|payout/', $route->uri());
            }
        }
    }
}
