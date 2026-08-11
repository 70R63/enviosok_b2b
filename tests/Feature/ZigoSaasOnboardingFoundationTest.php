<?php

namespace Tests\Feature;

use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use App\Domain\Network\Onboarding\Models\{SaasOnboardingApplication, SaasOnboardingEvent};
use App\Domain\Network\Onboarding\Services\{
    OnboardingApplicationService,
    OnboardingStateService,
    OnboardingSubdomainService
};
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ZigoSaasOnboardingFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        Carbon::setTestNow('2026-08-19 12:00:00');
        config([
            'zigo_onboarding.subdomain_base' => 'zigo-envios.com',
            'zigo_onboarding.reservation_minutes' => 60,
        ]);
        $this->schema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_creates_an_isolated_draft_with_normalized_email_and_no_operational_resources(): void
    {
        $application = $this->application('CORE-1', ['contact_email' => '  OWNER@Example.COM ']);

        $this->assertSame(SaasOnboardingApplication::DRAFT, $application->status);
        $this->assertSame('owner@example.com', $application->contact_email);
        $this->assertNotEmpty($application->uuid);
        $this->assertNotEmpty($application->public_token);
        foreach ([
            'network_tenants', 'users', 'network_tenant_memberships', 'network_subscriptions',
            'network_entitlements', 'network_tenant_brandings', 'network_tenant_domains',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} must remain empty before payment.");
        }
    }

    public function test_purchase_key_creation_is_idempotent_and_database_uniqueness_is_authoritative(): void
    {
        $first = $this->application('SAME-KEY');
        $second = $this->application('SAME-KEY', ['company_name' => 'Ignored retry']);
        $this->assertTrue($first->is($second));
        $this->assertSame('ACME', $second->company_name);
        $other = $this->application('OTHER-KEY');
        $this->assertNotSame($first->uuid, $other->uuid);

        $this->expectException(QueryException::class);
        SaasOnboardingApplication::create($this->payload() + ['purchase_key' => 'SAME-KEY']);
    }

    public function test_state_machine_accepts_only_canonical_transitions_and_records_events(): void
    {
        $states = app(OnboardingStateService::class);
        $application = $this->application('STATE-1');
        $application = $states->transition($application, SaasOnboardingApplication::PENDING_PAYMENT, 'CHECKOUT_READY');
        $application = $states->transition(
            $application,
            SaasOnboardingApplication::PAID,
            'PAYMENT_APPROVED',
            actorType: 'verified_payment',
            correlationKey: 'payment:state-1',
        );
        $application = $states->transition($application, SaasOnboardingApplication::PROVISIONING, 'PROVISIONING_STARTED');
        $application = $states->transition($application, SaasOnboardingApplication::FAILED, 'PROVISIONING_FAILED');
        $application = $states->transition($application, SaasOnboardingApplication::PROVISIONING, 'PROVISIONING_RETRIED');
        $application = $states->transition($application, SaasOnboardingApplication::ACTIVE, 'ACTIVATED');

        $this->assertSame(SaasOnboardingApplication::ACTIVE, $application->status);
        $this->assertSame(6, $application->events()->count());
        $this->expectException(ValidationException::class);
        $states->transition($application, SaasOnboardingApplication::PROVISIONING, 'ILLEGAL_ROLLBACK');
    }

    public function test_invalid_jump_is_rejected_and_same_transition_is_idempotent(): void
    {
        $states = app(OnboardingStateService::class);
        $application = $this->application('STATE-2');
        $same = $states->transition($application, SaasOnboardingApplication::DRAFT, 'RETRY');
        $this->assertTrue($application->is($same));
        $this->assertSame(0, SaasOnboardingEvent::count());

        $this->expectException(ValidationException::class);
        $states->transition($application, SaasOnboardingApplication::ACTIVE, 'INVALID');
    }

    public function test_expired_can_only_be_revived_by_late_approved_payment_path(): void
    {
        $states = app(OnboardingStateService::class);
        $application = $states->transition(
            $this->application('LATE-1'),
            SaasOnboardingApplication::PENDING_PAYMENT,
            'CHECKOUT_READY'
        );
        $application = $states->transition($application, SaasOnboardingApplication::EXPIRED, 'CHECKOUT_EXPIRED');
        $application = $states->transition(
            $application,
            SaasOnboardingApplication::PAID,
            'LATE_PAYMENT_APPROVED',
            actorType: 'verified_payment',
            correlationKey: 'payment:late-1',
        );
        $this->assertSame(SaasOnboardingApplication::PAID, $application->status);
        $this->assertNotNull($application->paid_at);
    }

    public function test_paid_requires_correlated_verified_payment_evidence(): void
    {
        $states = app(OnboardingStateService::class);
        $application = $states->transition(
            $this->application('PAYMENT-GATE'),
            SaasOnboardingApplication::PENDING_PAYMENT,
            'CHECKOUT_READY'
        );

        $this->expectException(ValidationException::class);
        $states->transition($application, SaasOnboardingApplication::PAID, 'UNVERIFIED_PAYMENT');
    }

    public function test_server_side_snapshot_is_frozen_and_ignores_later_catalog_changes(): void
    {
        $plan = $this->plan('GROWTH', '100.00', '1000.00');
        $module = $this->module('API');
        NetworkCommercialProduct::create([
            'code' => 'API-M', 'name' => 'API Monthly', 'type' => 'MODULE',
            'billing_type' => 'MONTHLY', 'price' => '25.50', 'currency' => 'MXN',
            'module_id' => $module->id, 'is_active' => true,
        ]);
        NetworkCommercialProduct::create([
            'code' => 'OPS-500', 'name' => '500 operations', 'type' => 'OPERATION_PACK',
            'billing_type' => 'ONE_TIME', 'price' => '50.00', 'currency' => 'MXN',
            'included_operations' => 500, 'is_active' => true,
        ]);
        $application = $this->application('SNAP-1', ['selected_plan_id' => $plan->id]);
        $application = app(OnboardingApplicationService::class)
            ->freezeCommercialSnapshot($application, [$module->id], 500, '0.16');
        $frozen = $application->commercial_snapshot_json;

        $plan->update(['monthly_price' => '999.00']);
        $again = app(OnboardingApplicationService::class)
            ->freezeCommercialSnapshot($application->fresh(), [], null, '0.00');

        $this->assertSame('175.50', $frozen['subtotal']);
        $this->assertSame('28.08', $frozen['tax_amount']);
        $this->assertSame('203.58', $frozen['total']);
        $this->assertSame($frozen, $again->commercial_snapshot_json);
        $this->assertSame('203.58', $again->total);
        $this->assertSame(1, SaasOnboardingEvent::where('event', 'COMMERCIAL_SNAPSHOT_FROZEN')->count());
    }

    public function test_subdomain_normalizes_rejects_reserved_and_builds_server_side_hostname(): void
    {
        $service = app(OnboardingSubdomainService::class);
        $this->assertSame('mi-empresa', $service->normalize('  Mi-Empresa '));
        $this->assertSame('mi-empresa.zigo-envios.com', $service->hostname('Mi-Empresa'));

        foreach (['admin', 'RapidGo', '-bad', 'bad-', 'bad.example', 'https://bad'] as $invalid) {
            try {
                $service->normalize($invalid);
                $this->fail("{$invalid} should be rejected.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_subdomain_detects_tenant_domain_and_active_reservation_collisions(): void
    {
        $service = app(OnboardingSubdomainService::class);
        $tenant = Tenant::create(['name' => 'Existing', 'slug' => 'existing', 'status' => 'active']);
        $tenant->domains()->create([
            'domain' => 'taken.zigo-envios.com', 'type' => 'subdomain', 'environment' => 'production',
            'is_primary' => true, 'status' => 'verified',
        ]);

        foreach (['existing', 'taken'] as $label) {
            try {
                $service->reserve($this->application('COLLISION-'.$label), $label);
                $this->fail("{$label} should collide.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $first = $service->reserve($this->application('RESERVE-1'), 'available');
        $this->assertSame('available', $first->reserved_subdomain_key);
        $this->expectException(ValidationException::class);
        $service->reserve($this->application('RESERVE-2'), 'available');
    }

    public function test_expired_reservation_can_be_reused_and_same_reservation_is_idempotent(): void
    {
        $service = app(OnboardingSubdomainService::class);
        $first = $service->reserve($this->application('EXP-1'), 'reusable');
        $same = $service->reserve($first, 'reusable');
        $this->assertTrue($first->is($same));

        $first->update(['subdomain_reserved_until' => now()->subSecond()]);
        $second = $service->reserve($this->application('EXP-2'), 'reusable');
        $this->assertSame('reusable', $second->reserved_subdomain_key);
        $this->assertNull($first->fresh()->reserved_subdomain_key);
    }

    public function test_event_metadata_is_sanitized_and_events_are_append_only(): void
    {
        $application = app(OnboardingStateService::class)->transition(
            $this->application('AUDIT-1'),
            SaasOnboardingApplication::PENDING_PAYMENT,
            'CHECKOUT_READY',
            metadata: [
                'password' => 'plain', 'access_token' => 'token-value',
                'nested' => ['APP_KEY' => 'secret-key', 'safe' => 'visible'],
            ],
        );
        $metadata = $application->events()->firstOrFail()->metadata_json;
        $encoded = json_encode($metadata);
        $this->assertStringNotContainsString('plain', $encoded);
        $this->assertStringNotContainsString('token-value', $encoded);
        $this->assertStringNotContainsString('secret-key', $encoded);
        $this->assertSame('visible', $metadata['nested']['safe']);

        $this->expectException(\LogicException::class);
        $application->events()->firstOrFail()->update(['event' => 'TAMPERED']);
    }

    private function application(string $purchaseKey, array $overrides = []): SaasOnboardingApplication
    {
        return app(OnboardingApplicationService::class)
            ->createOrRecover(array_merge($this->payload(), $overrides), $purchaseKey);
    }

    private function payload(): array
    {
        return [
            'contact_name' => 'Owner', 'contact_email' => 'owner@example.test',
            'company_name' => 'ACME', 'billing_period' => 'monthly',
        ];
    }

    private function plan(string $code, string $monthly, string $annual): Plan
    {
        return Plan::create([
            'code' => $code, 'name' => $code, 'status' => 'active',
            'monthly_price' => $monthly, 'annual_price' => $annual,
            'currency' => 'MXN', 'included_operations' => 100,
        ]);
    }

    private function module(string $code): Module
    {
        return Module::create([
            'code' => $code, 'name' => $code, 'type' => 'addon',
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function schema(): void
    {
        foreach ([
            'saas_onboarding_events', 'saas_onboarding_applications', 'network_entitlements',
            'network_subscriptions', 'network_tenant_memberships', 'network_tenant_brandings',
            'network_tenant_domains', 'network_commercial_products', 'network_plan_modules',
            'network_tenants', 'network_plans', 'network_modules', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->unsignedBigInteger('empresa_id'); $table->timestamps();
        });
        Schema::create('network_modules', function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name');
            $table->text('description')->nullable(); $table->string('type');
            $table->boolean('is_active'); $table->unsignedSmallInteger('sort_order'); $table->timestamps();
        });
        Schema::create('network_plans', function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name');
            $table->text('description')->nullable(); $table->string('status');
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('annual_price', 12, 2)->nullable(); $table->char('currency', 3);
            $table->unsignedInteger('included_operations')->nullable(); $table->timestamps();
        });
        Schema::create('network_tenants', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->string('name');
            $table->string('slug')->unique(); $table->string('status');
            $table->unsignedBigInteger('current_plan_id')->nullable(); $table->timestamps();
        });
        Schema::create('network_plan_modules', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_id'); $table->unsignedBigInteger('module_id');
            $table->boolean('is_included'); $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps(); $table->unique(['plan_id', 'module_id']);
        });
        Schema::create('network_tenant_domains', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->string('domain')->unique();
            $table->string('type'); $table->string('environment'); $table->boolean('is_primary');
            $table->string('status'); $table->timestamp('verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('network_tenant_brandings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id')->unique(); $table->timestamps();
        });
        Schema::create('network_tenant_memberships', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('user_id');
            $table->string('role'); $table->string('status'); $table->timestamps();
        });
        Schema::create('network_subscriptions', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('plan_id');
            $table->string('status'); $table->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('subscription_id');
            $table->string('code'); $table->timestamps();
        });
        Schema::create('network_commercial_products', function (Blueprint $table): void {
            $table->id(); $table->uuid('uuid')->unique(); $table->string('code')->unique();
            $table->string('name'); $table->text('description')->nullable(); $table->string('type');
            $table->string('billing_type'); $table->decimal('price', 12, 2); $table->char('currency', 3);
            $table->unsignedBigInteger('module_id')->nullable(); $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedInteger('included_operations')->nullable(); $table->boolean('is_active');
            $table->unsignedInteger('sort_order')->default(0); $table->json('metadata')->nullable(); $table->timestamps();
        });

        (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up();
        (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up();
    }
}
