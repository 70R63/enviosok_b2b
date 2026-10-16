<?php

namespace Tests\Feature;

use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderConsumerResolver;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderPickupConfirmationService;
use App\Domain\Network\Providers\ProviderPickupPreference;
use App\Domain\Network\Providers\ProviderPresentation;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\ProviderTrackingProjection;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Http\Controllers\B2C\CotizacionPublicaController;
use App\Http\Controllers\B2C\ProviderFulfillmentController;
use App\Models\B2cCotizacion;
use App\Models\User;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProviderAcceptanceFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

final class EnfaPostPurchaseTest extends TestCase
{
    private array $f;
    private B2cCotizacion $quote;
    private User $buyer;
    private User $operator;
    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Mail::fake();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        ProviderAcceptanceFixture::schema();
        $this->f = ProviderContractFixture::seed();
        ProviderFulfillmentFixture::operational($this->f);
        $this->f['consumer']->update(['name' => 'ZIGO', 'slug' => 'zigo']);
        $this->f['consumer']->branding()->update(['brand_name' => 'ZIGO']);
        $this->f['provider']->update(['name' => 'entregasenfa', 'slug' => 'entregasenfa']);
        $this->f['provider']->branding()->update(['brand_name' => 'ENFA ENTREGAS URBANAS']);
        $this->f['service']->update(['name' => 'Directo']);
        $this->host = config('zigo_domains.portals.b2c.host');
        config(['provider_network.corporate_consumer_slug' => 'zigo', 'provider_network.corporate_consumer_uuid' => null]);
        request()->headers->set('host', $this->host);
        app(TenantContext::class)->clear();
        $this->buyer = User::create(['name' => 'Buyer', 'email' => 'enfa11-buyer@example.test', 'password' => 'unused']);
        $this->operator = User::create(['name' => 'PRIVATE_OPERATOR', 'email' => 'enfa11-operator@example.test', 'password' => 'unused']);
        TenantMembership::create(['tenant_id' => $this->f['provider']->id, 'user_id' => $this->operator->id, 'role' => 'operator', 'status' => 'active']);
        $this->actingAs($this->buyer);
        $this->quote = $this->f['quote'];
        $this->quote->update(['user_id' => $this->buyer->id]);
        $offer = app(ProviderQuotePublisher::class)->publish($this->quote, app(TenantProviderQuoteSource::class)->options($this->quote, 'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($this->quote, $offer['provider_snapshot_uuid']);
        $this->quote->refresh();
    }

    private function url(string $path): string { return 'https://'.$this->host.$path; }

    private function paid(): void
    {
        B2cCotizacion::withoutEvents(fn () => $this->quote->forceFill(['estatus' => 'PAGADA', 'payment_status' => 'approved',
            'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(), 'payment_verified_amount' => 145,
            'payment_verified_currency' => 'MXN', 'payment_verified_external_reference' => 'B2C-'.$this->quote->id])->save());
    }

    private function fulfill(): ProviderFulfillment
    {
        $this->paid();
        return app(ProviderFulfillmentService::class)->fulfill($this->quote);
    }

    private function accepted(): ProviderFulfillment
    {
        $bridge = $this->fulfill();
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $bridge->uuid, $this->operator, 'ACCEPTED');
        return $bridge->fresh();
    }

    private function window(string $key = 'requested_date'): array
    {
        return ['mode' => 'WINDOW', $key => now(config('app.timezone'))->addDay()->toDateString(), 'window_start' => '09:00', 'window_end' => '13:00'];
    }

    public function test_authenticated_navigation_and_local_download_without_document(): void
    {
        $bridge = $this->fulfill();
        $this->get($this->url('/b2c/envio-local/'.$this->quote->providerContract->uuid))->assertOk()
            ->assertSee('Inicio')->assertSee('Nuevo envío')->assertSee('Mis envíos')->assertSee('Rastrear')->assertSee('Descargar guía')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull($this->quote->fresh()->documento);
        $this->get($this->url('/b2c/mis-envios'))->assertOk()->assertSee(route('b2c.provider-fulfillment.guide', $this->quote->providerContract->uuid), false);
    }

    public function test_recovery_has_no_private_account_navigation_and_expired_or_revoked_is_denied(): void
    {
        $bridge = $this->fulfill();
        $this->quote->forceFill(['user_id' => null])->saveQuietly();
        app(GuideRecoveryService::class)->guideAvailable($this->quote->fresh(), 'enfa11-buyer@example.test');
        $mail = Mail::sent(\App\Mail\ProviderFulfillmentConfirmedMail::class)->first();
        $plain = basename(parse_url($mail->confirmation['url'], PHP_URL_PATH));
        $this->app['auth']->forgetGuards();
        $this->get($this->url('/envio/recuperar/'.$plain))->assertOk()->assertDontSee('Mis envíos')->assertDontSee('Nuevo envío')->assertSee('Descargar guía');
        DB::table('b2c_guide_recovery_tokens')->update(['revoked_at' => now()]);
        $this->get($this->url('/envio/recuperar/'.$plain))->assertNotFound();
        DB::table('b2c_guide_recovery_tokens')->update(['revoked_at' => null, 'expires_at' => now()->subMinute()]);
        $this->get($this->url('/envio/recuperar/'.$plain))->assertNotFound();
    }

    #[DataProvider('processedStates')]
    public function test_processed_payment_never_offers_payment(string $state): void
    {
        if ($state === 'bridge') $this->fulfill();
        elseif ($state === 'guide') $this->quote->forceFill(['guia_estatus' => 'GENERADA'])->saveQuietly();
        else $this->paid();
        foreach (['/b2c/pago/', '/b2c/confirmar/', '/b2c/checkout/'] as $path) {
            $this->get($this->url($path.$this->quote->id))->assertRedirect(route('b2c.provider-fulfillment', $this->quote->providerContract->uuid))
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $page = $this->get($this->url('/b2c/envio-local/'.$this->quote->providerContract->uuid))->assertOk()->assertDontSee('Pagar con');
        if ($state !== 'bridge') $page->assertSee('Estamos preparando tu envío');
        Http::assertNothingSent();
    }
    public static function processedStates(): array { return [['bridge'], ['verified'], ['guide']]; }

    public function test_stale_return_callbacks_preserve_verified_purchase_and_pickup_without_fulfillment(): void
    {
        $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>$this->window()]]);
        $this->paid();
        $request = $this->quote->fresh()->guia_request_snapshot;
        foreach (['success','failure','pending'] as $result) {
            $this->get($this->url('/b2c/pago/'.$this->quote->id.'/'.$result))->assertRedirect(route('b2c.provider-fulfillment',$this->quote->providerContract->uuid));
            $this->assertSame('VERIFIED',$this->quote->fresh()->payment_verification_status);
            $this->assertSame($request,$this->quote->fresh()->guia_request_snapshot);
        }
        $this->assertDatabaseCount('network_provider_fulfillments',0);
        Http::assertNothingSent();
    }

    public function test_get_payment_rechecks_payment_accredited_during_identity_gate_before_reserving(): void
    {
        $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>['mode'=>'ASAP']]]);
        config(['services.mercadopago.access_token'=>'test-only-unused']);
        $this->mock(\App\Services\Identity\IdentityGuideAccessService::class)->shouldReceive('assertCanProceed')->once()->andReturnUsing(function () { $this->paid(); return []; });
        $this->mock(\App\Services\Billing\B2cCheckoutDebtService::class)->shouldNotReceive('reserveForCotizacion');
        $this->get($this->url('/b2c/pago/'.$this->quote->id))->assertRedirect(route('b2c.provider-fulfillment',$this->quote->providerContract->uuid));
        $this->assertSame('PAGADA',$this->quote->fresh()->estatus);
        Http::assertNothingSent();
    }

    public function test_payment_ownership_and_host_are_required(): void
    {
        $this->fulfill();
        $other = User::create(['name' => 'Other', 'email' => 'other11@example.test', 'password' => 'unused']);
        $this->actingAs($other)->get($this->url('/b2c/pago/'.$this->quote->id))->assertForbidden();
        $this->actingAs($this->buyer)->get('https://unknown.example.test/b2c/pago/'.$this->quote->id)->assertForbidden();
    }

    #[DataProvider('trackingPaths')]
    public function test_exact_b2c_routes_and_query_use_public_projection(string $path, string $method): void
    {
        $bridge = $this->accepted();
        $tracking = $bridge->shipment->tracking_number;
        auth()->logout();
        $url = $this->url($path);
        $response = $method === 'POST' ? $this->post($url, ['tracking_number' => $tracking]) : $this->get($url.'?tracking_number='.$tracking);
        $response->assertOk()->assertViewIs('b2c.provider-tracking-public')->assertSee($tracking)->assertSee('ENFA ENTREGAS URBANAS')
            ->assertSee('Compra confirmada')->assertSee('Solicitud aceptada')->assertDontSee('Recolección confirmada');
        foreach (['Pickup Street', 'Delivery Contact', '8112345678', 'PRIVATE_OPERATOR', 'provider_cost', 'tenant_id', 'driver_profile_id', 'photo', 'signature', '/b2c/envio-local/', $bridge->uuid] as $secret) $response->assertDontSee($secret);
        $this->assertSame(['tracking_number', 'operator', 'service', 'status_label', 'timeline'], array_keys($response->viewData('publicTracking')));
    }
    public static function trackingPaths(): array { return [['/rastreo','GET'], ['/rastreo','POST'], ['/rastrear','GET'], ['/tracking','GET']]; }

    #[DataProvider('initialTrackingPaths')]
    public function test_b2c_tracking_initial_state_uses_new_shell_without_error(string $path): void
    {
        auth()->logout();
        $this->get($this->url($path))->assertOk()->assertViewIs('b2c.provider-tracking-public')
            ->assertSee('method="GET"', false)->assertSee('Rastrear')->assertDontSee('No encontramos información')
            ->assertDontSee('search-box')->assertDontSee('#facc15');
    }
    public static function initialTrackingPaths(): array { return [['/rastreo'], ['/rastrear'], ['/tracking']]; }

    #[DataProvider('trackingPaths')]
    public function test_missing_b2c_tracking_uses_new_shell_and_retains_search(string $path, string $method): void
    {
        auth()->logout();
        $tracking = 'PRUEBA-NO-EXISTE-123';
        $response = $method === 'POST'
            ? $this->post($this->url($path), ['tracking_number' => $tracking])
            : $this->get($this->url($path).'?tracking_number='.$tracking);
        $response->assertOk()->assertViewIs('b2c.provider-tracking-public')
            ->assertSee('No encontramos información para el número de rastreo: '.$tracking)
            ->assertSee('value="'.$tracking.'"', false)->assertSee('method="GET"', false)
            ->assertDontSee('search-box')->assertDontSee('#facc15');
        $this->assertNull($response->viewData('publicTracking'));
        $this->assertNull($response->viewData('nationalTracking'));
        $this->assertArrayNotHasKey('cotizacion', $response->original->getData());
    }

    public function test_missing_tracking_is_escaped_in_the_new_shell(): void
    {
        $tracking = '<script>alert(1)</script>';
        $this->get($this->url('/rastreo?tracking_number='.rawurlencode($tracking)))
            ->assertOk()->assertViewIs('b2c.provider-tracking-public')
            ->assertSee($tracking)->assertDontSee($tracking, false);
    }

    public function test_existing_provider_tracking_reads_historical_snapshot_without_rewriting_it(): void
    {
        $bridge = $this->fulfill();
        $snapshot = $bridge->shipment->guide_snapshot;
        unset($snapshot['provider_identity'], $snapshot['service_name']);
        $snapshot['provider'] = 'entregasenfa';
        DB::table('local_shipments')->where('id', $bridge->local_shipment_id)
            ->update(['guide_snapshot' => json_encode($snapshot)]);
        auth()->logout();
        $this->get($this->url('/rastreo?tracking_number='.$bridge->shipment->tracking_number))
            ->assertOk()->assertViewIs('b2c.provider-tracking-public')
            ->assertSee($bridge->shipment->tracking_number)->assertDontSee('search-box');
        $this->assertSame($snapshot, $bridge->shipment->fresh()->guide_snapshot);
    }

    public function test_white_label_routes_remain_and_unknown_host_has_no_zigo_consumer(): void
    {
        foreach (['/rastreo', '/rastrear', '/tracking'] as $path) {
            $route = Route::getRoutes()->match(Request::create('https://white-label.example.test'.$path));
            $this->assertStringStartsWith('tenant.b2c.', $route->getName());
            $this->assertContains('tenant.resolve', $route->gatherMiddleware());
        }
        $this->get('https://unknown.example.test/rastreo')->assertNotFound();
        app(TenantContext::class)->clear();
        request()->headers->set('host', 'unknown.example.test');
        $this->assertNull(app(ProviderConsumerResolver::class)->current());
    }

    public function test_other_consumer_cannot_read_local_public_tracking(): void
    {
        $bridge = $this->fulfill();
        config(['provider_network.corporate_consumer_uuid' => $this->f['provider']->uuid]);
        $this->get($this->url('/rastreo?tracking_number='.$bridge->shipment->tracking_number))->assertOk()->assertSee('No encontramos información')
            ->assertDontSee('ENFA ENTREGAS URBANAS');
    }

    #[DataProvider('nationalProviders')]
    public function test_national_tracking_keeps_results_in_the_new_b2c_shell(string $provider): void
    {
        B2cCotizacion::create(['tracking_number' => 'NATIONAL123', 'provider' => $provider, 'logistico' => 'Estafeta', 'guia_estatus' => 'GENERADA']);
        $this->get($this->url('/rastreo?tracking_number=NATIONAL123'))->assertOk()->assertViewIs('b2c.provider-tracking-public')->assertSee('Estafeta')->assertSee('Guía generada');
        $this->post($this->url('/rastreo'), ['tracking_number' => 'NATIONAL123'])->assertOk()->assertViewIs('b2c.provider-tracking-public');
        B2cCotizacion::create(['documento' => 'DOCUMENT123', 'provider' => $provider, 'logistico' => 'Estafeta', 'guia_estatus' => 'GENERADA']);
        $response = $this->get($this->url('/rastreo?tracking_number=DOCUMENT123'))->assertOk()
            ->assertViewIs('b2c.provider-tracking-public')->assertSee('DOCUMENT123')->assertSee('Guía generada');
        $this->assertSame(['tracking_number', 'operator', 'status_label'], array_keys($response->viewData('nationalTracking')));
        $this->assertArrayNotHasKey('cotizacion', $response->original->getData());
    }
    public static function nationalProviders(): array { return [['estafeta'], ['xperta']]; }

    public function test_branding_is_snapshotted_and_provider_logo_survives_change(): void
    {
        Storage::fake('public');
        $path = 'tenant-branding/'.$this->f['provider']->uuid.'/logo.png';
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Storage::disk('public')->put($path, $bytes);
        $this->f['provider']->branding()->update(['logo_path' => $path]);
        $bridge = $this->fulfill();
        $snapshot = $bridge->shipment->guide_snapshot;
        $this->assertSame('ZIGO', $snapshot['branding']['brand_name']);
        $this->assertSame('ENFA ENTREGAS URBANAS', $snapshot['provider_identity']['display_name']);
        $this->assertSame('Directo', $snapshot['service_name']);
        $asset = $snapshot['provider_identity']['logo_path'];
        $this->assertStringContainsString('/guide-assets/', $asset);
        $this->f['provider']->branding()->update(['brand_name' => 'NEW PUBLIC BRAND', 'logo_path' => null]);
        Storage::disk('public')->delete($path);
        $this->assertSame($bytes, Storage::disk('public')->get($asset));
        $this->get($this->url('/b2c/envio-local/'.$this->quote->providerContract->uuid))->assertOk()->assertSee('ENFA ENTREGAS URBANAS')->assertDontSee('NEW PUBLIC BRAND')->assertDontSee('entregasenfa');
        $this->assertSame($snapshot, $bridge->shipment->fresh()->guide_snapshot);
    }

    public function test_slug_and_uuid_are_not_public_identity_and_legacy_snapshot_is_not_rewritten(): void
    {
        $this->f['provider']->branding()->update(['brand_name' => 'entregasenfa']);
        $this->assertSame('Operador logístico', app(ProviderPresentation::class)->identity($this->f['provider']->fresh())['name']);
        $legacy = ['provider' => 'entregasenfa', 'branding' => ['brand_name' => 'ZIGO']];
        $this->assertSame('Operador logístico', app(ProviderPresentation::class)->snapshotIdentity($legacy)['display_name']);
        $this->assertSame('entregasenfa', $legacy['provider']);
    }

    public function test_pdf_points_to_public_tracking_without_contract_or_recovery_token(): void
    {
        $bridge = $this->fulfill();
        $pdf = app(ProviderFulfillmentController::class)->publicGuide($this->quote->providerContract)->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $decoded = '';
        foreach ($streams[1] as $stream) $decoded .= @gzuncompress($stream) ?: $stream;
        $this->assertStringContainsString('https://'.$this->host.'/rastreo?tracking_number='.$bridge->shipment->tracking_number, $decoded);
        $this->assertStringNotContainsString('/b2c/envio-local/', $decoded);
        $this->assertStringNotContainsString($this->quote->providerContract->uuid, $decoded);
    }

    public function test_asap_discards_date_and_times(): void
    {
        $data = app(ProviderPickupPreference::class)->normalize(['mode' => 'ASAP', 'requested_date' => '2000-01-01', 'window_start' => '12:00', 'window_end' => '13:00']);
        $this->assertSame(['mode' => 'ASAP', 'requested_date' => null, 'window_start' => null, 'window_end' => null, 'timezone' => config('app.timezone')], $data);
    }

    #[DataProvider('invalidPreferences')]
    public function test_invalid_pickup_is_rejected(array $input): void
    {
        $this->expectException(ValidationException::class);
        app(ProviderPickupPreference::class)->normalize($input);
    }
    public static function invalidPreferences(): array
    {
        return [[[]], [['mode'=>'LATER']], [['mode'=>'WINDOW']], [['mode'=>'WINDOW','requested_date'=>'2000-01-01','window_start'=>'09:00','window_end'=>'13:00']],
            [['mode'=>'WINDOW','requested_date'=>'2099-01-01','window_start'=>'13:00','window_end'=>'13:00']],
            [['mode'=>'WINDOW','requested_date'=>'2099-01-01','window_start'=>'14:00','window_end'=>'13:00']]];
    }

    public function test_checkout_merges_pickup_and_fulfillment_retry_preserves_frozen_preference(): void
    {
        $this->quote->update(['guia_request_snapshot' => ['existing' => ['keep' => true]]]);
        $input = $this->window();
        $this->post($this->url('/b2c/checkout/'.$this->quote->id), ProviderFulfillmentFixture::addresses() + ['remitente_email'=>'buyer@example.test','pickup_request'=>$input])->assertRedirect();
        $request = data_get($this->quote->fresh()->guia_request_snapshot, 'pickup_request');
        $this->assertSame($input['requested_date'], $request['requested_date']);
        $this->assertNotEmpty($request['requested_at']);
        $this->assertTrue(data_get($this->quote->fresh()->guia_request_snapshot, 'existing.keep'));
        $this->quote->refresh();
        $bridge = $this->fulfill();
        $this->assertSame($request, $bridge->operation->metadata['pickup_request']);
        $this->post($this->url('/b2c/checkout/'.$this->quote->id), ['pickup_request'=>['mode'=>'ASAP']])->assertRedirect();
        $this->assertSame($request, data_get($this->quote->fresh()->guia_request_snapshot, 'pickup_request'));
        $this->assertSame($bridge->id, app(ProviderFulfillmentService::class)->fulfill($this->quote)->id);
    }

    public function test_duplicate_has_no_old_pickup_or_paid_provider_state(): void
    {
        $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>$this->window()]]);
        $this->fulfill();
        app(CotizacionPublicaController::class)->duplicarEnvioB2c($this->quote->fresh());
        $copy = B2cCotizacion::latest('id')->first();
        $this->assertNotSame($this->quote->id, $copy->id);
        foreach (['provider','provider_quote_reference','guia_request_snapshot','payment_verified_at','payment_verification_status'] as $field) $this->assertNull($copy->$field);
        $this->assertNull($copy->providerContract);
        $this->assertNotNull(data_get($this->quote->fresh()->guia_request_snapshot, 'pickup_request'));
    }

    public function test_accepted_does_not_confirm_and_confirmation_is_append_only_idempotent_without_physical_change(): void
    {
        $bridge = $this->accepted();
        $this->assertSame(0, $bridge->shipment->events()->where('event_code','PICKUP_CONFIRMED')->count());
        $projection = app(ProviderTrackingProjection::class)->publicProject($bridge, $this->f['consumer']);
        $this->assertStringContainsString('recolección por confirmar', $projection['status_label']);
        $this->assertStringNotContainsString('Recolección confirmada', json_encode($projection, JSON_UNESCAPED_UNICODE));
        $data = $this->window('confirmed_date');
        $event = app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, $data);
        $this->assertSame($event->id, app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, $data)->id);
        $this->assertSame('READY_FOR_PICKUP', $bridge->shipment->fresh()->status);
        $this->assertSame($event->metadata, $bridge->operation->fresh()->metadata['pickup_confirmation']);
        $this->assertArrayHasKey('consumer_tenant_id', $bridge->operation->fresh()->metadata);
        $this->assertCount(1, $bridge->shipment->events()->where('event_code','PICKUP_CONFIRMED')->get());
        $this->expectException(\LogicException::class);
        $event->update(['metadata'=>[]]);
    }

    public function test_asap_confirmation_and_real_timeline_only(): void
    {
        $bridge = $this->accepted();
        app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP']);
        $projection = app(ProviderTrackingProjection::class)->publicProject($bridge->fresh(), $this->f['consumer']);
        $this->assertStringContainsString('confirmó atención lo antes posible', $projection['status_label']);
        $this->assertCount(4, $projection['timeline']);
        $this->assertStringNotContainsString('PICKED_UP', json_encode($projection));
        foreach (['created_by_user_id','PRIVATE_OPERATOR','metadata','pod','received_by_name'] as $secret) $this->assertStringNotContainsString($secret,json_encode($projection));
    }

    public function test_confirmation_retry_tolerates_database_json_key_order(): void
    {
        $bridge = $this->accepted();
        $service = app(ProviderPickupConfirmationService::class);
        $event = $service->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP']);
        // Simulate JSON object key order returned by another database driver.
        DB::table('local_tracking_events')->where('id',$event->id)->update(['metadata'=>json_encode(array_reverse($event->metadata,true))]);
        $this->assertSame($event->id,$service->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP'])->id);
        $this->assertSame(1,$bridge->shipment->events()->where('event_code','PICKUP_CONFIRMED')->count());
    }

    public function test_conflicting_confirmation_is_rejected_without_overwrite(): void
    {
        $bridge = $this->accepted();
        app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP']);
        $this->expectException(ValidationException::class);
        app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, $this->window('confirmed_date'));
    }

    public function test_confirmation_requires_acceptance(): void
    {
        $bridge = $this->fulfill();
        $this->expectException(HttpException::class);
        app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP']);
    }

    public function test_national_checkout_preserves_its_json_without_pickup_input(): void
    {
        $national = $this->quote->replicate();
        $national->unsetRelations();
        $national->provider = 'xperta';
        $national->guia_request_snapshot = ['national_payload' => ['keep' => 'unchanged']];
        $national->save();
        $this->post($this->url('/b2c/checkout/'.$national->id), ProviderFulfillmentFixture::addresses() + ['remitente_email'=>'buyer@example.test'])->assertRedirect();
        $this->assertSame(['national_payload' => ['keep' => 'unchanged']], $national->fresh()->guia_request_snapshot);
    }

    public function test_paid_pickup_cannot_be_replaced_and_cleanup_detaches_old_checkout(): void
    {
        $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>['mode'=>'ASAP']]]);
        $this->paid();
        try {
            $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>$this->window()]]);
            $this->fail('Paid preference must be frozen');
        } catch (ValidationException) {
            $this->assertSame('ASAP', data_get($this->quote->fresh()->guia_request_snapshot, 'pickup_request.mode'));
        }
        session(['cotizacion_publica_id'=>$this->quote->id, 'b2c_public_checkout'=>['id'=>$this->quote->id], 'b2c_pending_checkout_id'=>$this->quote->id]);
        app(CotizacionPublicaController::class)->limpiarCotizacion();
        foreach (['cotizacion_publica_id','b2c_public_checkout','b2c_pending_checkout_id'] as $key) $this->assertFalse(session()->has($key));
    }

    public function test_confirmation_endpoint_reuses_provider_boundary_and_ui_separates_acceptance(): void
    {
        $bridge = $this->fulfill();
        $controller = app(\App\Http\Controllers\Tenant\ProviderFulfillmentInboxController::class);
        $context = app(TenantContext::class);
        $context->set($this->f['provider']);
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $this->operator);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = $controller->show($request, $bridge->uuid, $context, app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('Preferencia cliente', $html);
        $this->assertStringNotContainsString('Confirmar recolección', $html);
        app(ProviderFulfillmentDecisionService::class)->decide($this->f['provider'], $bridge->uuid, $this->operator, 'ACCEPTED');
        $html = $controller->show($request, $bridge->uuid, $context, app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('Solicitud aceptada', $html);
        $this->assertStringContainsString('Confirmar recolección', $html);
        $request = Request::create('/', 'POST', ['pickup_confirmation'=>$this->window('confirmed_date')]);
        $request->setUserResolver(fn () => $this->operator);
        $controller->confirmPickup($request, $bridge->uuid, $context);
        $html = $controller->show($request, $bridge->uuid, $context, app(ProviderFulfillmentDecisionService::class))->getContent();
        $this->assertStringContainsString('Recolección confirmada', $html);
        $this->assertStringContainsString('09:00–13:00', $html);
        $route = Route::getRoutes()->getByName('tenant.admin.provider-inbox.pickup-confirmation');
        $this->assertSame(['POST'], $route->methods());
        foreach (['web','tenant.resolve','tenant.auth','tenant.admin.access','tenant.entitlement:LOCAL_SHIPPING'] as $middleware) $this->assertContains($middleware,$route->gatherMiddleware());
    }

    public function test_confirmation_requires_provider_actor_and_does_not_send_unclaimed_email(): void
    {
        $bridge = $this->accepted();
        $before = Mail::sent(\App\Mail\ProviderFulfillmentConfirmedMail::class)->count();
        try {
            app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->buyer, ['mode'=>'ASAP']);
            $this->fail('Consumer is not provider staff');
        } catch (HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'], $bridge, $this->operator, ['mode'=>'ASAP']);
        $this->assertSame($before, Mail::sent(\App\Mail\ProviderFulfillmentConfirmedMail::class)->count());
    }

    public function test_public_delivered_tracking_does_not_query_pod_or_return_arbitrary_event_metadata(): void
    {
        $bridge = $this->accepted();
        // Use the existing state machine to arrange physical history; dispatch/POD enforcement is covered by NetworkProviderTrackingTest.
        foreach (['PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED'] as $state) app(LocalTrackingService::class)->transition($bridge->shipment->fresh(), $state);
        DB::table('local_tracking_events')->update(['description'=>'PRIVATE_NOTE', 'metadata'=>json_encode(['received_by_name'=>'PRIVATE_RECEIVER','driver_profile_id'=>999,'photo_path'=>'pod/photos/PRIVATE.jpg'])]);
        DB::enableQueryLog();
        $projection = app(ProviderTrackingProjection::class)->publicProject($bridge->fresh(), $this->f['consumer']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame('Entregado',$projection['status_label']);
        $this->assertCount(7,$projection['timeline']);
        foreach (['PRIVATE_NOTE','PRIVATE_RECEIVER','driver_profile_id','photo_path','pod'] as $secret) $this->assertStringNotContainsString($secret,json_encode($projection));
        foreach ($queries as $query) $this->assertStringNotContainsString('local_delivery_proofs',$query['query']);
    }

    public function test_unpaid_local_payment_requires_explicit_preference_and_bfcache_revalidation_is_present(): void
    {
        $this->get($this->url('/b2c/pago/'.$this->quote->id))->assertRedirect(route('b2c.checkout',$this->quote));
        $this->get($this->url('/b2c/checkout/'.$this->quote->id))->assertOk()->assertSee('Lo antes posible')->assertSee('Programar ventana')->assertSee('event.persisted',false)
            ->assertHeader('Cache-Control','no-store, private');
        Http::assertNothingSent();
    }

    public function test_other_provider_cannot_confirm(): void
    {
        $bridge = $this->accepted();
        TenantMembership::create(['tenant_id'=>$this->f['consumer']->id,'user_id'=>$this->operator->id,'role'=>'operator','status'=>'active']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ProviderPickupConfirmationService::class)->confirm($this->f['consumer'], $bridge, $this->operator, ['mode'=>'ASAP']);
    }

    #[DataProvider('expiredPaymentPaths')]
    public function test_expired_stored_window_is_rejected_before_payment(string $path, string $method, bool $nextDay = false): void
    {
        $start = now(config('app.timezone'))->addDay()->startOfDay()->addHours(8);
        $this->travelTo($start);
        $input = ['mode'=>'WINDOW','requested_date'=>$start->toDateString(),'window_start'=>'09:00','window_end'=>'13:00'];
        $this->post($this->url('/b2c/checkout/'.$this->quote->id), ProviderFulfillmentFixture::addresses()+['remitente_email'=>'buyer@example.test','pickup_request'=>$input])->assertRedirect();
        \App\Models\B2cSaldo::create(['user_id'=>$this->buyer->id,'saldo'=>500]);
        $this->mock(\App\Services\Billing\B2cCheckoutDebtService::class)->shouldNotReceive('reserveForCotizacion');
        $this->mock(\App\Services\Identity\IdentityGuideAccessService::class)->shouldNotReceive('assertCanProceed');
        $this->travelTo($start->copy()->addDays($nextDay ? 1 : 0)->addHours(6));
        $url = $this->url(str_replace('{id}',(string)$this->quote->id,$path));
        $response = $method === 'GET' ? $this->get($url) : $this->post($url,['metodo_pago'=>'mercado_pago']);
        $response->assertRedirect(route('b2c.checkout',$this->quote))->assertSessionHasErrors($nextDay ? 'requested_date' : 'window_end');
        $this->assertEquals(500,\App\Models\B2cSaldo::where('user_id',$this->buyer->id)->value('saldo'));
        $this->assertDatabaseCount('b2c_movimientos_saldo',0);
        $this->assertDatabaseCount('network_provider_fulfillments',0);
        Http::assertNothingSent();
    }
    public static function expiredPaymentPaths(): array
    {
        return [['/b2c/pago/{id}','GET'],['/b2c/confirmar/{id}','GET'],['/b2c/pago/{id}/saldo','POST'],['/b2c/confirmar/{id}','POST'],['/b2c/pago/{id}','GET',true]];
    }

    public function test_verified_purchase_survives_expired_window_during_callback_and_fulfillment(): void
    {
        $start = now(config('app.timezone'))->addDay()->startOfDay()->addHours(8);
        $this->travelTo($start);
        $request = app(ProviderPickupPreference::class)->normalize(['mode'=>'WINDOW','requested_date'=>$start->toDateString(),'window_start'=>'09:00','window_end'=>'13:00']);
        $this->quote->update(['guia_request_snapshot'=>['pickup_request'=>$request]]);
        $this->paid();
        $this->travelTo($start->copy()->addDay());
        app(ProviderPickupPreference::class)->validateForPayment($this->quote->fresh());
        $this->get($this->url('/b2c/pago/'.$this->quote->id.'/success'))->assertRedirect();
        $bridge = app(ProviderFulfillmentService::class)->fulfill($this->quote->fresh());
        $this->assertSame($request,$bridge->operation->metadata['pickup_request']);
        $this->assertSame('VERIFIED',$this->quote->fresh()->payment_verification_status);
    }

    #[DataProvider('technicalNames')]
    public function test_slug_casing_is_never_a_public_identity(string $name): void
    {
        $this->f['provider']->branding()->update(['brand_name'=>$name]);
        $this->assertSame('Operador logístico',app(ProviderPresentation::class)->identity($this->f['provider']->fresh())['name']);
        $legacy = ['provider'=>$name];
        $this->assertSame('Operador logístico',app(ProviderPresentation::class)->snapshotIdentity($legacy)['display_name']);
        $this->assertSame(['provider'=>$name],$legacy);
    }
    public static function technicalNames(): array { return [['entregasenfa'],['Entregasenfa'],['ENTREGASENFA'],[' entregasenfa ']]; }

    public function test_non_corporate_consumer_keeps_its_authorized_guide_url(): void
    {
        $bridge = $this->fulfill();
        config(['provider_network.corporate_consumer_uuid'=>$this->f['provider']->uuid]);
        app(TenantContext::class)->set($this->f['consumer']);
        $pdf = app(ProviderFulfillmentController::class)->publicGuide($this->quote->providerContract)->getContent();
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s',$pdf,$streams);
        $decoded = '';
        foreach($streams[1] as $stream) $decoded .= @gzuncompress($stream) ?: $stream;
        $this->assertStringContainsString('/b2c/envio-local/'.$this->quote->providerContract->uuid,$decoded);
        $this->assertStringNotContainsString('https://'.$this->host.'/rastreo?',$decoded);
        app(TenantContext::class)->set($this->f['provider']);
        $this->expectException(HttpException::class);
        app(ProviderFulfillmentController::class)->publicGuide($this->quote->providerContract);
    }

    public function test_operational_confirmation_never_replaces_a_physical_transition_or_tenant_label(): void
    {
        $this->freezeTime();
        $bridge = $this->accepted();
        $physical = $bridge->shipment->events()->where('event_code','READY_FOR_PICKUP')->firstOrFail();
        $confirmation = app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'],$bridge,$this->operator,['mode'=>'ASAP']);
        $tracking = app(LocalTrackingService::class);
        $repeat = $tracking->transition($bridge->shipment,'READY_FOR_PICKUP');
        $this->assertSame($physical->id,$repeat->id);
        $this->assertNotSame($confirmation->id,$repeat->id);
        $this->assertSame(1,$bridge->shipment->events()->where('event_code','READY_FOR_PICKUP')->count());
        $read = $tracking->read($this->f['provider'],$bridge->shipment->uuid);
        $this->assertCount(2,$read['events']);
        $this->assertSame('Recolección confirmada',\App\Support\Presentation\ShipmentStatusPresenter::eventLabel($confirmation));
        $this->assertSame('Recolección solicitada',\App\Support\Presentation\ShipmentStatusPresenter::eventLabel($physical));
        view()->share('errors',new \Illuminate\Support\ViewErrorBag);
        $html = view('tenant.tracking._timeline',['trackingEvents'=>[$physical,$confirmation]])->render();
        $this->assertSame(1,substr_count($html,'Recolección solicitada'));
        $this->assertSame(1,substr_count($html,'Recolección confirmada'));
        $projection = app(ProviderTrackingProjection::class)->publicProject($bridge->fresh(),$this->f['consumer']);
        $this->assertCount(4,$projection['timeline']);
    }

    public function test_duplicate_clears_all_purchase_artifacts_and_can_notify_its_own_guide(): void
    {
        $original = $this->fulfill();
        $fields = [
            'guide_processing_email_sent_at'=>now(),'guide_generated_email_sent_at'=>now(),'guide_pending_email_sent_at'=>now(),
            'guide_pdf_recovered_email_sent_at'=>now(),'provider_confirmation_claimed_at'=>now(),'provider_fulfillment_attempted_at'=>now(),
            'guia_generation_attempts'=>3,'guia_last_error_code'=>'OLD_ERROR','guia_last_error_message'=>'old failure',
            'guia_provider_reference'=>'old-ref','guia_request_fingerprint'=>str_repeat('a',64),'guia_request_snapshot'=>['pickup_request'=>null],
        ];
        // Historical derived fields are setup directly, not changes to the original checkout.
        DB::table('b2c_cotizaciones')->where('id',$this->quote->id)->update(array_merge($fields,['guia_request_snapshot'=>json_encode($fields['guia_request_snapshot'])]));
        $originalAttributes = $this->quote->fresh()->getAttributes();
        app(CotizacionPublicaController::class)->duplicarEnvioB2c($this->quote->fresh());
        $copy = B2cCotizacion::latest('id')->firstOrFail();
        foreach(B2cCotizacion::LOCAL_PURCHASE_RESET_FIELDS as $field) {
            if (in_array($field,['guia_estatus','guia_generation_attempts','precio'],true)) continue;
            if(array_key_exists($field,$copy->getAttributes())) $this->assertNull($copy->$field,$field);
        }
        $this->assertSame(0,$copy->guia_generation_attempts);
        $this->assertNull($copy->providerContract);
        $this->assertFalse($copy->hasGeneratedGuide());
        $this->assertFalse($copy->hasAccreditedPayment());
        $this->assertSame($originalAttributes,$this->quote->fresh()->getAttributes());
        $this->assertSame('Pickup Contact',$copy->remitente_nombre);
        $copy->update(['estatus'=>'COTIZADA']);
        $offer=app(ProviderQuotePublisher::class)->publish($copy,app(TenantProviderQuoteSource::class)->options($copy,'anonymous'))[0];
        app(ProviderContractSelectionService::class)->select($copy,$offer['provider_snapshot_uuid']);
        $copy->refresh();
        B2cCotizacion::withoutEvents(fn()=>$copy->forceFill(['estatus'=>'PAGADA','payment_status'=>'approved','payment_verification_status'=>'VERIFIED','payment_verified_at'=>now(),
            'payment_verified_amount'=>145,'payment_verified_currency'=>'MXN','payment_verified_external_reference'=>'B2C-'.$copy->id])->save());
        $newBridge=app(ProviderFulfillmentService::class)->fulfill($copy);
        app(GuideRecoveryService::class)->guideAvailable($copy->fresh(),'enfa11-buyer@example.test');
        Mail::assertSent(\App\Mail\ProviderFulfillmentConfirmedMail::class,1);
        $this->assertNotSame($original->id,$newBridge->id);
    }

    public function test_contract_without_shipment_has_no_download_and_ready_guide_returns_pdf(): void
    {
        $this->get($this->url('/b2c/mis-envios'))->assertOk()->assertDontSee('Descargar guía');
        $this->fulfill();
        $url=route('b2c.provider-fulfillment.guide',$this->quote->providerContract->uuid);
        $this->get($this->url('/b2c/mis-envios'))->assertOk()->assertSee($url,false)->assertSee('Descargar guía');
        $this->get($url)->assertOk()->assertHeader('Content-Type','application/pdf');
    }

    public function test_national_scope_keeps_payload_duplication_and_does_not_add_no_store(): void
    {
        $national=$this->quote->replicate();$national->unsetRelations();$national->provider='xperta';
        $national->guia_request_snapshot=['national_payload'=>true];$national->save();
        $response=$this->get($this->url('/b2c/checkout/'.$national->id))->assertOk();
        $this->assertStringNotContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        app(CotizacionPublicaController::class)->duplicarEnvioB2c($national);
        $this->assertSame(['national_payload'=>true],B2cCotizacion::latest('id')->first()->guia_request_snapshot);
    }

    public function test_confirmation_does_not_publish_a_duplicate_physical_webhook_or_api_timeline_event(): void
    {
        $bridge=$this->accepted();
        \Illuminate\Support\Facades\Schema::create('tenant_api_operation_links',function(\Illuminate\Database\Schema\Blueprint $t){
            $t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('api_client_id');$t->unsignedBigInteger('local_shipment_id');$t->string('external_reference')->nullable();
        });
        \Illuminate\Support\Facades\Schema::create('tenant_api_webhook_endpoints',function(\Illuminate\Database\Schema\Blueprint $t){
            $t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('api_client_id');$t->string('status');$t->json('events');
        });
        \Illuminate\Support\Facades\Schema::create('tenant_api_webhook_deliveries',function(\Illuminate\Database\Schema\Blueprint $t){
            $t->id();foreach(['event_id','event_type','resource_type','resource_uuid','status'] as $field)$t->string($field);
            foreach(['webhook_endpoint_id','tenant_id','api_client_id','attempt'] as $field)$t->unsignedBigInteger($field);
            $t->json('payload');$t->timestamp('next_retry_at')->nullable();$t->timestamps();
        });
        DB::table('tenant_api_operation_links')->insert(['tenant_id'=>$this->f['provider']->id,'api_client_id'=>1,'local_shipment_id'=>$bridge->local_shipment_id]);
        DB::table('tenant_api_webhook_endpoints')->insert(['tenant_id'=>$this->f['provider']->id,'api_client_id'=>1,'status'=>'ACTIVE','events'=>json_encode(['shipment.ready_for_pickup'])]);
        $publisher=app(\App\Domain\ApiHub\ApiWebhookPublisher::class);
        $publisher->tracking($bridge->shipment->events()->where('event_code','READY_FOR_PICKUP')->firstOrFail());
        $this->assertDatabaseCount('tenant_api_webhook_deliveries',1);
        $event=app(ProviderPickupConfirmationService::class)->confirm($this->f['provider'],$bridge,$this->operator,['mode'=>'ASAP']);
        $publisher->tracking($event);
        $this->assertDatabaseCount('tenant_api_webhook_deliveries',1);
        $request=Request::create('/');$request->attributes->set('tenant',$this->f['provider']);$request->attributes->set('api_client',(object)['id'=>1]);
        $response=app(\App\Http\Controllers\API\Hub\V1\TrackingController::class)->show($request,$bridge->shipment->tracking_number);
        $this->assertCount(2,$response->getData(true)['data']['timeline']);
        Http::assertNothingSent();
    }
}
