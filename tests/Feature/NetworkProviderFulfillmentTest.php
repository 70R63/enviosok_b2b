<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\Models\LocalShipment;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Http\Controllers\B2C\GuestGuideRecoveryController;
use App\Http\Controllers\B2C\ProviderFulfillmentController;
use App\Mail\GuideRecoveryMail;
use App\Mail\ProviderFulfillmentConfirmedMail;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\Billing\B2cCheckoutDebtService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

final class NetworkProviderFulfillmentTest extends TestCase
{
    private array $f;

    private B2cCotizacion $quote;

    private ProviderContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite required.');
        }
        DB::statement('PRAGMA foreign_keys = ON');
        Http::preventStrayRequests();
        Mail::fake();
        ProviderFulfillmentFixture::schema();
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        $this->quote = $this->f['quote'];
        app(TenantContext::class)->set($this->f['consumer']);
        session()->put('cotizacion_publica_id', $this->quote->id);
        $option = app(ProviderQuotePublisher::class)->publish($this->quote,
            app(TenantProviderQuoteSource::class)->options($this->quote, 'anonymous'))[0];
        $this->contract = app(ProviderContractSelectionService::class)->select($this->quote, $option['provider_snapshot_uuid']);
        $this->mock(B2cCheckoutDebtService::class, function ($mock): void {
            $mock->shouldReceive('expectedPaymentTotal')->andReturn(145.0);
            $mock->shouldReceive('applyReserved')->andReturn([]);
        });
    }

    private function verified(bool $events = false): B2cCotizacion
    {
        $save = fn () => $this->quote->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->quote->id])->save();
        if ($events) {
            $save();
        } else {
            B2cCotizacion::withoutEvents($save);
        }

        return $this->quote->refresh();
    }

    private function fulfill(): ProviderFulfillment
    {
        return app(ProviderFulfillmentService::class)->fulfill($this->quote);
    }

    public function test_selection_and_checkout_without_verified_purchase_create_nothing(): void
    {
        $this->quote->refresh()->update(['estatus' => 'CHECKOUT_COMPLETO']);
        try {
            $this->fulfill();
            $this->fail('Payment required');
        } catch (\DomainException $e) {
            $this->assertSame('PAYMENT_NOT_CONFIRMED', $e->getMessage());
        }
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_verified_payment_creates_provider_owned_operation_shipment_and_automatic_pickup(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $this->assertSame($this->f['provider']->id, $bridge->operation->tenant_id);
        $this->assertSame($this->f['provider']->id, $bridge->shipment->tenant_id);
        $this->assertNotSame($this->f['consumer']->id, $bridge->shipment->tenant_id);
        $this->assertSame('confirmed', $bridge->operation->status);
        $this->assertSame('READY_FOR_PICKUP', $bridge->shipment->status);
        $this->assertNotNull($bridge->pickup_requested_at);
        $this->assertDatabaseCount('network_usage_events', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        $this->assertDatabaseCount('local_driver_assignments', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_directions_and_package_are_complete_and_frozen(): void
    {
        $this->verified();
        $shipment = $this->fulfill()->shipment;
        $this->assertSame('Pickup Street', $shipment->sender_snapshot['address']['street']);
        $this->assertSame('10', $shipment->sender_snapshot['address']['exterior']);
        $this->assertSame('A', $shipment->sender_snapshot['address']['interior']);
        $this->assertSame('64000', $shipment->sender_snapshot['address']['postal_code']);
        $this->assertSame('Puerta azul', $shipment->sender_snapshot['address']['references']);
        $this->assertSame('Delivery Street', $shipment->recipient_snapshot['address']['street']);
        $this->assertSame('64001', $shipment->recipient_snapshot['address']['postal_code']);
        $this->assertSame('Timbre 2', $shipment->recipient_snapshot['address']['references']);
        $this->assertSame(20, $shipment->package_snapshot['length']);
        $this->assertSame('2.00', $shipment->package_snapshot['weight']);
        $this->quote->update(['remitente_direccion' => 'Changed profile address', 'destinatario_direccion' => 'Changed delivery']);
        $this->assertSame($shipment->sender_snapshot, $shipment->fresh()->sender_snapshot);
        $this->assertSame($shipment->recipient_snapshot, $this->fulfill()->shipment->recipient_snapshot);
    }

    public function test_double_confirmation_is_idempotent(): void
    {
        $this->verified();
        $first = $this->fulfill();
        $second = $this->fulfill();
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->tenant_operation_id, $second->tenant_operation_id);
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        $this->assertDatabaseCount('network_usage_events', 1);
    }

    public function test_unique_contract_link_rejects_duplicate_sql_write(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $data = $bridge->getAttributes();
        unset($data['id']);
        $data['uuid'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('network_provider_fulfillments')->insert($data);
    }

    public function test_revoked_connection_before_fulfillment_keeps_payment_but_no_operation(): void
    {
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->verified(true);
        $this->assertSame('PAGADA', $this->quote->fresh()->estatus);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->expectException(\DomainException::class);
        $this->fulfill();
    }

    public function test_existing_fulfillment_is_returned_after_later_revocation(): void
    {
        $this->verified();
        $first = $this->fulfill();
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->assertSame($first->id, $this->fulfill()->id);
    }

    public function test_missing_contract_rejects_fulfillment(): void
    {
        $other = B2cCotizacion::create(['estatus' => 'PAGADA', 'provider' => 'tenant_provider_local']);
        $this->expectException(\DomainException::class);
        app(ProviderFulfillmentService::class)->fulfill($other);
    }

    public function test_provider_suspension_prevents_execution(): void
    {
        $this->verified();
        $this->f['provider']->update(['status' => 'suspended']);
        try {
            $this->fulfill();
            $this->fail('Suspended');
        } catch (\DomainException) {
        }
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_removed_service_authorization_prevents_execution(): void
    {
        $this->verified();
        DB::table('network_provider_connection_services')->delete();
        $this->expectException(\DomainException::class);
        $this->fulfill();
    }

    public function test_incomplete_addresses_leave_no_partial_operation_or_usage(): void
    {
        $this->verified();
        $this->quote->update(['destinatario_num_ext' => null]);
        try {
            $this->fulfill();
            $this->fail('Missing direction');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_accepted_offer_expiry_and_tariff_changes_do_not_reprice_fulfillment(): void
    {
        $this->travel(31)->minutes();
        $this->f['tariff']->update(['amount' => 999]);
        $this->f['commercial']->update(['margin_percentage' => 90]);
        $this->verified();
        $bridge = $this->fulfill();
        $this->assertSame('100.00', $bridge->shipment->pricing_snapshot['provider_cost']);
        $this->assertSame('145.00', $this->quote->fresh()->precio);
        $this->assertArrayNotHasKey('profit_amount', $bridge->shipment->pricing_snapshot);
        $this->assertArrayNotHasKey('customer_price', $bridge->shipment->pricing_snapshot);
    }

    public function test_tenant_context_is_never_switched(): void
    {
        $this->verified();
        $before = app(TenantContext::class)->current();
        $this->fulfill();
        $this->assertSame($before, app(TenantContext::class)->current());
    }

    public function test_public_summary_hides_internal_ids_and_economics(): void
    {
        $this->verified();
        $this->fulfill();
        $html = app(ProviderFulfillmentController::class)->show($this->quote->fresh())->getContent();
        foreach (['provider_cost', 'provider_tenant_id', 'consumer_tenant_id', 'connection_id', 'profit_amount', 'Cancelar'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        foreach (['Consumer Store', 'Provider Delivery', 'Pickup Street', 'Delivery Street', 'Listo para recolectar'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_other_consumer_cannot_read_summary_or_guide(): void
    {
        $this->verified();
        $this->fulfill();
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']));
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->guide($this->quote->fresh());
    }

    public function test_other_session_cannot_read_customer_addresses(): void
    {
        $this->verified();
        $this->fulfill();
        session()->flush();
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->show($this->quote->fresh());
    }

    public function test_existing_local_pdf_renderer_produces_private_consumer_provider_guide(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $this->assertSame('Consumer Store', $bridge->shipment->guide_snapshot['branding']['brand_name']);
        $this->assertSame('Provider Delivery', $bridge->shipment->guide_snapshot['provider']);
        $this->assertSame('Envío local', $bridge->shipment->guide_snapshot['service_name']);
        $pdf = app(ProviderFulfillmentController::class)->guide($this->quote->fresh());
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertTrue($pdf->headers->hasCacheControlDirective('private'));
        $this->assertTrue($pdf->headers->hasCacheControlDirective('no-store'));
    }

    public function test_real_verified_payment_boundary_runs_only_after_commit(): void
    {
        DB::beginTransaction();
        app(PaymentVerificationService::class)->verifyPayload($this->quote->fresh(), ['id' => '123', 'status' => 'approved',
            'external_reference' => 'B2C-'.$this->quote->id, 'currency_id' => 'MXN', 'transaction_amount' => 145]);
        $this->assertDatabaseCount('network_tenant_operations', 0);
        DB::commit();
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_payment_transaction_rollback_creates_no_physical_operation(): void
    {
        DB::beginTransaction();
        $this->verified(true);
        DB::rollBack();
        $this->assertDatabaseCount('local_shipments', 0);
        $this->assertDatabaseCount('network_provider_fulfillments', 0);
    }

    public function test_national_verified_quote_is_ignored_by_observer(): void
    {
        B2cCotizacion::create(['provider' => 'xperta', 'estatus' => 'PAGADA', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now()]);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_provider_failure_is_isolated_and_no_crm_http_or_email_is_sent(): void
    {
        $this->f['provider']->update(['status' => 'suspended']);
        $this->verified(true);
        $national = B2cCotizacion::create(['provider' => 'xperta', 'estatus' => 'PAGADA']);
        $this->assertSame('PAGADA', $national->fresh()->estatus);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_rollback_removes_bridge_but_preserves_provider_operations(): void
    {
        $this->verified();
        $this->fulfill();
        $migration = require database_path('migrations/2026_10_13_120000_create_network_provider_fulfillments.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('network_provider_fulfillments'));
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $migration->up();
        $this->assertTrue(Schema::hasTable('network_provider_fulfillments'));
        try {
            $this->fulfill();
            $this->fail('Rollback must not duplicate fulfillment');
        } catch (\DomainException $e) {
            $this->assertSame('FULFILLMENT_LINK_REQUIRES_REPAIR', $e->getMessage());
        }
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_pending_review_can_retry_without_a_second_purchase(): void
    {
        $this->f['connection']->forceFill(['status' => 'inactive'])->save();
        $this->verified(true);
        $this->f['connection']->forceFill(['status' => 'active'])->save();
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_balance_payment_boundary_uses_applied_movement_and_verified_payment(): void
    {
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'test']);
        $this->quote->refresh()->update(['user_id' => $user->id, 'estatus' => 'PAGADA', 'payment_status' => 'saldo_prepago']);
        DB::table('b2c_movimientos_saldo')->insert(['user_id' => $user->id, 'tipo' => 'COMPRA_GUIA',
            'referencia' => 'COTIZACION-'.$this->quote->id, 'estatus' => 'APLICADO', 'monto' => 145]);
        DB::transaction(fn () => app(PaymentVerificationService::class)->markBalancePaymentVerified($this->quote->refresh(), 145));
        $this->assertDatabaseCount('network_provider_fulfillments', 1);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertNull(ProviderFulfillment::firstOrFail()->operation->customer_profile_id);
    }

    public function test_fake_approved_status_without_verified_payment_is_not_sufficient(): void
    {
        $this->quote->refresh()->update(['estatus' => 'PAGADA', 'payment_status' => 'approved']);
        try {
            $this->fulfill();
            $this->fail('Verification required');
        } catch (\DomainException) {
        }
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_sql_bridge_cannot_transfer_operation_ownership_to_consumer(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $this->expectException(QueryException::class);
        DB::table('network_provider_fulfillments')->where('id', $bridge->id)->update(['provider_tenant_id' => $this->f['consumer']->id]);
    }

    public function test_fulfillment_bridge_cannot_be_mutated_even_without_events(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $bridge->forceFill(['pickup_requested_at' => now()->addDay()]);
        $this->expectException(\LogicException::class);
        $bridge->saveQuietly();
    }

    public function test_failure_after_operation_creation_rolls_back_every_operational_write(): void
    {
        $this->verified();
        LocalShipment::creating(fn () => throw new \RuntimeException('Test shipment failure'));
        try {
            $this->fulfill();
            $this->fail('Fail closed');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('network_tenant_operations', 0);
        $this->assertDatabaseCount('network_usage_events', 0);
        $this->assertDatabaseCount('local_shipments', 0);
        $this->assertDatabaseCount('network_provider_fulfillments', 0);
        $this->assertSame('PAGADA', $this->quote->fresh()->estatus);
    }

    public function test_provider_subscription_and_capability_are_required(): void
    {
        $this->verified();
        DB::table('network_entitlements')->update(['is_enabled' => false]);
        try {
            $this->fulfill();
            $this->fail('Provider not enabled');
        } catch (\DomainException $e) {
            $this->assertSame('PROVIDER_NOT_OPERATIONAL', $e->getMessage());
        }
        $this->assertDatabaseCount('network_tenant_operations', 0);
    }

    public function test_local_checkout_saves_current_address_references_without_fulfilling(): void
    {
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'checkout@example.test', 'password' => 'test']);
        $this->actingAs($user);
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $data = ProviderFulfillmentFixture::addresses() + ['remitente_email' => 'buyer@example.test',
            'remitente_referencias' => 'Pickup checkout reference', 'destinatario_referencias' => 'Delivery checkout reference'];
        $this->withSession(['cotizacion_publica_id' => $this->quote->id])->post(route('b2c.checkout.procesar', $this->quote), $data)
            ->assertRedirect(route('b2c.confirmar', $this->quote));
        $this->assertSame('Pickup checkout reference', $this->quote->fresh()->remitente_referencias);
        $this->assertSame('Delivery checkout reference', $this->quote->fresh()->destinatario_referencias);
        $this->assertDatabaseCount('local_shipments', 0);
    }

    public function test_shared_guide_notification_sends_local_confirmation_once_and_no_national_recovery_link(): void
    {
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'uuid@example.test', 'password' => 'test']);
        $this->quote->refresh()->update(['remitente_email' => 'buyer@example.test', 'user_id' => $user->id]);
        DB::table('network_tenant_domains')->insert(['tenant_id' => $this->f['consumer']->id, 'domain' => 'consumer.example.test', 'status' => 'verified']);
        $this->verified(true);
        app(GuideRecoveryService::class)->guideAvailable($this->quote->fresh(), 'buyer@example.test');
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, 1);
        Mail::assertNotSent(GuideRecoveryMail::class);
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, function ($mail): bool {
            $html = $mail->render();
            $this->assertStringContainsString('Pickup Street', $html);
            $this->assertStringContainsString('Provider Delivery', $html);
            $this->assertStringContainsString('https://consumer.example.test/b2c/envio-local/', $html);
            $this->assertStringNotContainsString('provider_cost', $html);
            $this->assertStringNotContainsString('profit_amount', $html);
            $this->assertStringNotContainsString('/envio/recuperar/', $html);

            return true;
        });
    }

    public function test_later_saved_profile_address_changes_never_change_shipping_snapshot(): void
    {
        Schema::table('b2c_direcciones', fn ($t) => $t->string('calle')->nullable());
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'profile@example.test', 'password' => 'test']);
        $address = DB::table('b2c_direcciones')->insertGetId(['user_id' => $user->id, 'tipo' => 'ORIGEN', 'cp' => '64000', 'calle' => 'Pickup Street']);
        $this->quote->refresh()->update(['user_id' => $user->id]);
        $this->verified();
        $shipment = $this->fulfill()->shipment;
        DB::table('b2c_direcciones')->where('id', $address)->update(['calle' => 'New profile street']);
        $this->assertSame('Pickup Street', $shipment->fresh()->sender_snapshot['address']['street']);
    }

    public function test_fulfillment_does_not_provision_pod_options_or_gps_requirements(): void
    {
        Schema::create('tenant_delivery_proof_options', fn ($t) => $t->id());
        Schema::create('local_shipment_delivery_requirements', fn ($t) => $t->id());
        $this->verified();
        $this->fulfill();
        $this->assertDatabaseCount('tenant_delivery_proof_options', 0);
        $this->assertDatabaseCount('local_shipment_delivery_requirements', 0);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_mail_failure_does_not_rollback_purchase_and_can_retry_without_duplicate_pickup(): void
    {
        $this->quote->refresh()->update(['remitente_email' => 'buyer@example.test']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailer unavailable'));
        $this->verified(true);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertNull($this->quote->fresh()->guide_generated_email_sent_at);
        Mail::swap(new MailManager(app()));
        Mail::fake();
        $this->artisan('network:provider-fulfillment:retry', ['--quote' => $this->quote->id])->assertExitCode(0);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, 1);
    }

    public function test_paid_local_checkout_cannot_downgrade_purchase_or_replace_frozen_addresses(): void
    {
        $user = User::forceCreate(['name' => 'Buyer', 'email' => 'paid@example.test', 'password' => 'test']);
        $this->actingAs($user);
        $this->quote->refresh()->update(['user_id' => $user->id]);
        $this->verified();
        $this->fulfill();
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->post(route('b2c.checkout.procesar', $this->quote), ['remitente_direccion' => 'Tampered pickup'])
            ->assertRedirect(route('b2c.provider-fulfillment', $this->contract->uuid));
        $this->assertSame('GUIA_GENERADA', $this->quote->fresh()->estatus);
        $this->assertSame('Pickup Street', $this->quote->fresh()->remitente_direccion);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_public_summary_and_pdf_use_contract_uuid_and_reject_sequential_pk(): void
    {
        $this->verified();
        $this->fulfill();
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->withSession(['cotizacion_publica_id' => $this->quote->id])->get(route('b2c.provider-fulfillment', $this->contract->uuid))->assertOk();
        $this->get(route('b2c.provider-fulfillment.guide', $this->contract->uuid))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/b2c/envio-local/'.$this->quote->id)->assertNotFound();
    }

    private function guestToken(): string
    {
        $this->quote->refresh()->update(['remitente_email' => 'guest@example.test']);
        $this->verified(true);
        $mail = Mail::sent(ProviderFulfillmentConfirmedMail::class)->first();

        return basename(parse_url($mail->confirmation['url'], PHP_URL_PATH));
    }

    public function test_guest_recovery_after_session_loss_is_read_only_and_downloads_private_pdf(): void
    {
        $token = $this->guestToken();
        session()->flush();
        $recovery = app(GuideRecoveryService::class);
        $controller = app(GuestGuideRecoveryController::class);
        $response = $controller->show($token, $recovery);
        $this->assertStringContainsString('Pickup Street', $response->getContent());
        $this->assertStringContainsString(route('guide-recovery.download', $token), $response->getContent());
        $this->assertStringStartsWith('%PDF-', $controller->download($token, $recovery)->getContent());
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
        $this->assertNull($this->quote->fresh()->user_id);
        $this->assertFalse(session()->has('cotizacion_publica_id'));
    }

    public function test_guest_recovery_token_cannot_cross_consumer_tenants(): void
    {
        $token = $this->guestToken();
        session()->flush();
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other-recovery', 'status' => 'active']));
        $this->expectException(HttpException::class);
        app(GuestGuideRecoveryController::class)->download($token, app(GuideRecoveryService::class));
    }

    public function test_expired_guest_recovery_token_is_rejected(): void
    {
        $token = $this->guestToken();
        $this->travel(49)->hours();
        $this->expectException(HttpException::class);
        app(GuestGuideRecoveryController::class)->show($token, app(GuideRecoveryService::class));
    }

    public function test_crash_after_payment_commit_is_recovered_by_bulk_command(): void
    {
        $this->verified(); // Deliberately no observer, representing process death after commit.
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        $first = ProviderFulfillment::firstOrFail();
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        $this->assertDatabaseCount('local_shipments', 1);
        $this->assertSame($first->id, ProviderFulfillment::firstOrFail()->id);
    }

    public function test_crash_after_fulfillment_commit_before_mail_is_recovered_by_bulk_command(): void
    {
        $this->quote->refresh()->update(['remitente_email' => 'guest@example.test']);
        $this->verified();
        $this->fulfill();
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, 1);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_mail_claim_survives_crash_and_expires_without_affecting_fulfillment(): void
    {
        $this->quote->refresh()->update(['remitente_email' => 'guest@example.test']);
        $this->verified();
        $this->fulfill();
        DB::table('b2c_cotizaciones')->where('id', $this->quote->id)->update(['provider_confirmation_claimed_at' => now()]);
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->travel(11)->minutes();
        $this->artisan('network:provider-fulfillment:retry')->assertExitCode(0);
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
    }

    public function test_permanently_bad_order_does_not_starve_next_recovery_batch(): void
    {
        $this->verified();
        $this->quote->update(['remitente_num_ext' => null]);
        $next = B2cCotizacion::create(ProviderFulfillmentFixture::addresses() + ['cp_origen' => '64000',
            'cp_destino' => '64001', 'colonia_origen' => 'Origin', 'colonia_destino' => 'Destination', 'tipo_envio' => 'caja',
            'peso' => 2, 'medidas' => '20x10x10', 'referencia' => 'LANDING_PUBLICA', 'estatus' => 'COTIZADA',
            'requiere_seguro_envio' => false, 'seguro_monto' => 0]);
        session()->put('cotizacion_publica_id', $next->id);
        $option = app(ProviderQuotePublisher::class)->publish($next, app(TenantProviderQuoteSource::class)->options($next, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($next, $option['provider_snapshot_uuid']);
        B2cCotizacion::withoutEvents(fn () => $next->refresh()->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$next->id])->save());
        $this->artisan('network:provider-fulfillment:retry', ['--limit' => 1])->assertExitCode(1);
        $this->artisan('network:provider-fulfillment:retry', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame($next->id, ProviderContract::findOrFail(ProviderFulfillment::firstOrFail()->provider_contract_id)->b2c_cotizacion_id);
    }

    public function test_guest_can_request_new_access_by_public_folio_without_original_session(): void
    {
        $this->guestToken();
        session()->flush();
        Mail::fake();
        $folio = ProviderFulfillment::firstOrFail()->shipment->tracking_number;
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->post(route('guide-recovery.request'), ['folio' => $folio, 'correo' => 'guest@example.test'])->assertRedirect();
        Mail::assertSent(ProviderFulfillmentConfirmedMail::class, 1);
        Mail::assertNotSent(GuideRecoveryMail::class);
        $this->assertDatabaseCount('network_tenant_operations', 1);
        $this->assertDatabaseCount('local_tracking_events', 2);
    }

    public function test_guest_cannot_request_access_from_another_consumer_host(): void
    {
        $this->guestToken();
        session()->flush();
        Mail::fake();
        $folio = ProviderFulfillment::firstOrFail()->shipment->tracking_number;
        app(TenantContext::class)->set(Tenant::create(['name' => 'Other', 'slug' => 'other-request', 'status' => 'active']));
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->post(route('guide-recovery.request'), ['folio' => $folio, 'correo' => 'guest@example.test'])->assertRedirect();
        Mail::assertNothingSent();
    }

    public function test_revoked_guest_access_is_rejected(): void
    {
        $token = $this->guestToken();
        DB::table('b2c_guide_recovery_tokens')->where('token_hash', hash('sha256', $token))->update(['revoked_at' => now()]);
        $this->expectException(HttpException::class);
        app(GuestGuideRecoveryController::class)->show($token, app(GuideRecoveryService::class));
    }

    public function test_browser_provider_and_price_parameters_cannot_alter_fulfillment(): void
    {
        $this->verified();
        $bridge = $this->fulfill();
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $url = route('b2c.provider-fulfillment', $this->contract->uuid).'?provider_tenant_id='.$this->f['consumer']->id.'&precio=1';
        $this->withSession(['cotizacion_publica_id' => $this->quote->id])->get($url)->assertOk();
        $this->assertSame($this->f['provider']->id, $bridge->shipment->fresh()->tenant_id);
        $this->assertSame('145.00', $this->quote->fresh()->precio);
        $this->assertDatabaseCount('local_shipments', 1);
    }

    public function test_real_guest_envelope_checkout_payment_and_sessionless_recovery(): void
    {
        LocalShippingPackageRule::create(['tenant_id' => $this->f['provider']->id,
            'service_id' => $this->f['service']->id, 'package_type' => 'sobre', 'max_weight_kg' => 2, 'active' => true]);
        $quote = B2cCotizacion::create(['cp_origen' => '64000', 'cp_destino' => '64001', 'colonia_origen' => 'Origin',
            'colonia_destino' => 'Destination', 'tipo_envio' => 'sobre', 'peso' => 1, 'referencia' => 'LANDING_PUBLICA',
            'estatus' => 'COTIZADA', 'requiere_seguro_envio' => false, 'seguro_monto' => 0]);
        session()->put('cotizacion_publica_id', $quote->id);
        $option = app(ProviderQuotePublisher::class)->publish($quote, app(TenantProviderQuoteSource::class)->options($quote, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($quote, $option['provider_snapshot_uuid']);
        $quote->refresh();
        $this->app->instance(TenantContext::class, app(TenantContext::class));
        $this->withSession(['cotizacion_publica_id' => $quote->id, 'b2c_public_checkout' => ['id' => $quote->id,
            'tipo_envio' => 'sobre', 'fingerprint' => $quote->quote_request_fingerprint]])
            ->post(route('b2c.checkout.procesar', $quote), ProviderFulfillmentFixture::addresses() + ['remitente_email' => 'envelope@example.test'])
            ->assertRedirect(route('b2c.pago', $quote));
        $this->assertDatabaseCount('local_shipments', 0);
        app(PaymentVerificationService::class)->verifyPayload($quote->refresh(), ['id' => '456', 'status' => 'approved',
            'external_reference' => 'B2C-'.$quote->id, 'currency_id' => 'MXN', 'transaction_amount' => 145]);
        $shipment = ProviderFulfillment::firstOrFail()->shipment;
        $this->assertSame('sobre', $shipment->package_snapshot['type']);
        $this->assertArrayNotHasKey('length', $shipment->package_snapshot);
        $this->assertNull($quote->fresh()->user_id);
        $mail = Mail::sent(ProviderFulfillmentConfirmedMail::class)->first();
        $token = basename(parse_url($mail->confirmation['url'], PHP_URL_PATH));
        session()->flush();
        $this->assertStringStartsWith('%PDF-', app(GuestGuideRecoveryController::class)
            ->download($token, app(GuideRecoveryService::class))->getContent());
        $this->assertDatabaseCount('local_shipments', 1);
    }
}
