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

final class NetworkProviderReleaseCandidateTest extends TestCase
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
        $report = app(ProviderNetworkReadinessService::class)->evaluate($this->f['consumer'], $this->f['provider'], $this->f['connection']);
        $this->assertTrue($report['ready'], json_encode($report));
        session()->put('cotizacion_publica_id', $this->f['quote']->id);
        $option = app(ProviderQuotePublisher::class)->publish($this->f['quote'], app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($this->f['quote'], $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $this->f['quote']->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
            'payment_verified_amount' => 145, 'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->f['quote']->id])->save());
        Storage::fake('public');
        $consumerLogo = 'tenant-branding/'.$this->f['consumer']->uuid.'/logo.png';
        $logoFile = UploadedFile::fake()->image('logo.png');
        Storage::disk('public')->put($consumerLogo, file_get_contents($logoFile->getRealPath()));
        $this->f['consumer']->branding->update(['logo_path' => $consumerLogo]);
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

    public function test_integrated_release_lifecycle_without_crm(): void
    {
        $this->assertSame('READY_FOR_PICKUP', $this->bridge->shipment->status);
        $this->assertSame($this->f['provider']->id, $this->bridge->shipment->tenant_id);
        $this->f['tariff']->update(['amount' => 999]);
        $this->f['commercial']->update(['margin_percentage' => 99]);
        $this->delivered();
        $projection = app(ProviderTrackingProjection::class)->project($this->bridge, $this->f['consumer']);
        $this->assertSame('Entregado', $projection['status_label']);
        $this->assertTrue($projection['pod']['photo_available']);
        $this->assertCount(7, $projection['timeline']);
        $this->assertSame('PAYABLE', $this->payable()->financialStatus());
        Http::assertNothingSent();
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

    public function test_rejected_is_review_without_deleting_obligation_or_checkout(): void
    {
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $this->bridge->uuid, $this->admin, 'REJECTED', 'No capacity');
        $this->assertSame('REVIEW', $this->payable()->financialStatus());
        $this->assertDatabaseCount('network_provider_payables', 1);
        $this->assertDatabaseCount('local_driver_assignments', 0);
        $projection = app(ProviderTrackingProjection::class)->project($this->bridge, $this->f['consumer']);
        $this->assertStringContainsString('revisando', $projection['status_label']);
        $this->assertStringNotContainsString('No capacity', json_encode($projection));
        Http::assertNothingSent();
        $this->assertSame('READY_FOR_PICKUP', $this->bridge->shipment->fresh()->status);
        $this->assertSame('GUIA_GENERADA', $this->f['quote']->fresh()->estatus);
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

    public function test_partner_publication_requires_explicit_consent_and_active_authorization(): void
    {
        $presentation = app(\App\Domain\Network\Providers\ProviderPresentation::class);
        $this->assertSame([], $presentation->partners());
        config(['provider_network.public_partner_uuids' => [$this->f['provider']->uuid]]);
        $partners = $presentation->partners();
        $this->assertCount(1, $partners);
        $this->assertSame(['name', 'logo'], array_keys($partners[0]));
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->assertSame([], $presentation->partners());
        $this->assertSame([], app(TenantProviderQuoteSource::class)->options($this->f['quote'], 'anonymous'));
    }

    public function test_public_branding_reuses_tenant_brand_and_rejects_foreign_or_traversal_paths(): void
    {
        Storage::fake('public');
        $provider = $this->f['provider']->fresh();
        $path = 'tenant-branding/'.$provider->uuid.'/logo.png';
        $logoFile = UploadedFile::fake()->image('logo.png');
        Storage::disk('public')->put($path, file_get_contents($logoFile->getRealPath()));
        $provider->branding->update(['brand_name' => 'Local Partner', 'logo_path' => $path]);
        $provider->unsetRelation('branding');
        $presentation = app(\App\Domain\Network\Providers\ProviderPresentation::class);
        $identity = $presentation->identity($provider);
        $this->assertSame('Local Partner', $identity['name']);
        $this->assertNotNull($identity['logo']);
        $this->assertNull($presentation->logo($this->f['consumer'], $path));
        $this->assertNull($presentation->logo($provider, 'tenant-branding/'.$provider->uuid.'/../logo.png'));
        $this->assertNull($presentation->logo($provider, 'https://example.test/logo.png'));
    }
    public function test_guide_uses_consumer_logo_and_customer_provider_identity(): void
    {
        $guide = $this->bridge->shipment->guide_snapshot;
        $this->assertStringStartsWith('tenant-branding/'.$this->f['consumer']->uuid.'/guide-assets/', $guide['branding']['logo_path']);
        $this->assertTrue(Storage::disk('public')->exists($guide['branding']['logo_path']));
        $this->assertSame('Consumer Store', $guide['branding']['brand_name']);
        $this->assertSame('Provider Delivery', $guide['provider']);
        $pdf = app(ProviderFulfillmentController::class)->guide($this->f['quote']->fresh())->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }
}
