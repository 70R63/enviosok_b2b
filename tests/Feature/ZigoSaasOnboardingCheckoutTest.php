<?php

namespace Tests\Feature;

use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\{NetworkCommercialProduct, PlatformPaymentAttempt};
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Database\Seeders\ZigoOnboardingUatCatalogSeeder;
use Tests\TestCase;

final class ZigoSaasOnboardingCheckoutTest extends TestCase
{
    private string $base = 'http://zigo.local:8000';

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires isolated SQLite :memory:.');
        }
        config([
            'zigo_surfaces.corporate.host' => 'zigo.local',
            'zigo_onboarding.subdomain_base' => 'zigo-envios.com',
            'zigo_onboarding.tax_rate' => '0.16',
            'zigo_payments.platform.enabled' => true,
            'zigo_payments.platform.access_token' => 'platform-access-token',
            'zigo_payments.platform.webhook_url' => 'https://payments.zigo-envios.com/api/payments/mercado-pago/webhook',
            'zigo_payments.providers.mercado_pago.environment' => 'sandbox',
            'zigo_payments.providers.mercado_pago.api_url' => 'https://api.mercadopago.com',
        ]);
        $this->schema();
    }

    public function test_public_corporate_routes_render_and_wrong_host_is_not_found(): void
    {
        $offer = $this->catalog();
        $rapidPlan = Plan::create(['code'=>'RAPIDGO','name'=>'RapidGo','status'=>'active','monthly_price'=>'1.00','currency'=>'MXN']);
        NetworkCommercialProduct::create(['code'=>'RAPIDGO-DEMO','name'=>'RapidGo Demo','type'=>'PLAN','billing_type'=>'MONTHLY','price'=>'1.00','currency'=>'MXN','plan_id'=>$rapidPlan->id,'is_active'=>true]);
        $this->get($this->base.'/zigo-platform')->assertOk()
            ->assertSee('Opera tu propia plataforma de envíos con ZIGO')->assertDontSee('RapidGo');
        $this->get($this->base.'/zigo-platform/precios')->assertOk()
            ->assertSee($offer->name)->assertDontSee('RapidGo');
        $this->get($this->base.'/zigo-platform/comenzar')->assertOk()->assertSee('Tu empresa');
        $this->get('http://rapidgo-stage.zigo-envios.com/zigo-platform')->assertNotFound();

        config(['zigo_surfaces.corporate.host' => 'stage.zigo-envios.com']);
        $this->get('https://stage.zigo-envios.com/zigo-platform')->assertOk();
        $this->get('https://rapidgo-stage.zigo-envios.com/zigo-platform')->assertNotFound();
    }

    public function test_uat_catalog_is_idempotent_and_renders_real_pricing_and_solution_ctas(): void
    {
        $seeder = new ZigoOnboardingUatCatalogSeeder();
        $seeder->run();
        $seeder->run();

        $this->assertSame(3, Plan::where('code', 'like', 'UAT-ZIGO-%')->count());
        $this->assertSame(6, NetworkCommercialProduct::where('type', 'PLAN')->count());
        $pricing = $this->get($this->base.'/zigo-platform/precios')->assertOk()
            ->assertSee('ZIGO Esencial')->assertSee('ZIGO Operación')->assertSee('ZIGO Platform')
            ->assertSee('/zigo-platform/comenzar?offer=', false)->assertDontSee('RapidGo');
        $this->assertStringNotContainsString('href="#"', $pricing->getContent());

        $selected = NetworkCommercialProduct::where('code', 'UAT-UAT-ZIGO-ESENCIAL-MONTHLY')->firstOrFail();
        $application = $this->start($selected);
        $response = $this->get($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/solucion')
            ->assertOk()->assertSee('ZIGO Esencial')->assertSee('type="submit">Continuar', false)
            ->assertSee('Cambiar plan')->assertDontSee('ZIGO Operación')->assertDontSee('Volumen adicional')
            ->assertDontSee('name="billing_period"', false)->assertDontSee('API Hub mensual');
        $this->assertStringContainsString('/zigo-platform/precios', $response->getContent());
    }

    public function test_solution_without_catalog_has_no_functional_continue_button(): void
    {
        $application = $this->start();
        $response = $this->get($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/solucion')
            ->assertOk()->assertSee('Estamos preparando nuestras opciones comerciales')
            ->assertSee('Volver a precios')->assertDontSee('type="submit">Continuar', false);
        $this->assertStringNotContainsString('href="#"', $response->getContent());
    }

    public function test_wizard_creates_only_draft_and_repeated_purchase_key_is_idempotent(): void
    {
        $this->catalog();
        $payload = $this->companyPayload();
        $first = $this->post($this->base.'/zigo-platform/comenzar', $payload)->assertRedirect();
        $application = SaasOnboardingApplication::firstOrFail();
        $this->assertSame('DRAFT', $application->status);
        $this->assertStringContainsString($application->public_token, $first->headers->get('Location'));
        $this->post($this->base.'/zigo-platform/comenzar', $payload)->assertRedirect();
        $this->assertSame(1, SaasOnboardingApplication::count());
        $this->assertPreTenantIsolation();
    }

    public function test_inactive_plan_is_rejected_without_disclosing_or_creating_resources(): void
    {
        $offer = $this->catalog();
        $application = $this->start($offer);
        $offer->plan->update(['status' => 'inactive']);

        $this->from($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/solucion')
            ->patch($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/solucion', [
                'plan_offer' => (string) \Illuminate\Support\Str::uuid(),
                'billing_period' => 'annual',
            ])->assertSessionHasErrors('plan_offer');
        $this->assertSame('DRAFT', $application->fresh()->status);
        $this->assertPreTenantIsolation();
    }

    public function test_wizard_freezes_server_side_snapshot_reserves_subdomain_and_ignores_price_tampering(): void
    {
        $offer = $this->catalog();
        $application = $this->start($offer);
        $this->selectSolution($application, $offer, [
            'price' => '0.01', 'subtotal' => '0.01', 'total' => '0.01', 'currency' => 'USD',
        ]);

        $this->patch($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/plataforma', [
            'requested_subdomain' => 'Mi-Empresa', 'total' => '0.01',
        ])->assertRedirect($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/resumen');

        $application->refresh();
        $this->assertSame('PENDING_PAYMENT', $application->status);
        $this->assertSame('mi-empresa', $application->reserved_subdomain_key);
        $this->assertSame('100.00', $application->subtotal);
        $this->assertSame('16.00', $application->tax_amount);
        $this->assertSame('116.00', $application->total);
        $this->assertSame('MXN', $application->currency);
        $this->assertSame('PUBLIC-M', $application->commercial_snapshot_json['plan']['commercial_code']);

        $offer->update(['price' => '999.00']);
        $this->get($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/resumen')
            ->assertOk()->assertSee('$116.00')->assertDontSee('$999.00');
        $this->assertPreTenantIsolation();
    }

    public function test_offer_is_the_only_authority_for_monthly_and_annual_periods(): void
    {
        $monthly = $this->catalog();
        $annual = NetworkCommercialProduct::create([
            'code'=>'PUBLIC-A','name'=>'ZIGO Anual','description'=>'Oferta anual','type'=>'PLAN',
            'billing_type'=>'ANNUAL','price'=>'1000.00','currency'=>'MXN',
            'plan_id'=>$monthly->plan_id,'is_active'=>true,
        ]);

        $monthlyApplication = $this->start($monthly);
        $this->selectSolution($monthlyApplication, $monthly, ['billing_period'=>'annual']);
        $this->assertSame('monthly', $monthlyApplication->fresh()->billing_period);
        $this->assertSame($monthly->uuid, $monthlyApplication->fresh()->selected_modules_json['plan_offer_uuid']);

        $this->flushSession();
        $annualApplication = $this->start($annual, 'annual@example.test');
        $this->selectSolution($annualApplication, $annual, ['billing_period'=>'monthly']);
        $this->assertSame('annual', $annualApplication->fresh()->billing_period);
        $this->assertSame($annual->uuid, $annualApplication->fresh()->selected_modules_json['plan_offer_uuid']);
        $this->patch($this->base.'/zigo-platform/solicitud/'.$annualApplication->public_token.'/plataforma', [
            'requested_subdomain'=>'annual-company',
        ])->assertRedirect();
        $annualApplication->refresh();
        $this->assertSame('PUBLIC-A', $annualApplication->commercial_snapshot_json['plan']['commercial_code']);
        $this->assertSame('annual', $annualApplication->commercial_snapshot_json['plan']['billing_period']);
        $this->assertSame('1000.00', $annualApplication->subtotal);
    }

    public function test_checkout_links_attempt_exclusively_to_onboarding_and_uses_server_values(): void
    {
        $offer = $this->catalog();
        $application = $this->readyForCheckout($offer);
        Http::fake(['https://api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref-onboarding-1',
            'sandbox_init_point' => 'https://www.mercadopago.com.mx/checkout/v1/redirect?pref_id=1',
        ], 201)]);

        $this->post($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/checkout')
            ->assertRedirectContains('mercadopago.com.mx');
        $attempt = PlatformPaymentAttempt::firstOrFail();
        $this->assertSame($application->id, $attempt->onboarding_application_id);
        $this->assertNull($attempt->tenant_id);
        $this->assertNull($attempt->saas_order_id);
        $this->assertSame('116.00', $attempt->amount);
        $this->assertSame('MXN', $attempt->currency);
        $this->assertSame('PENDING', $attempt->status);
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            return $request->hasHeader('Authorization', 'Bearer platform-access-token')
                && $request->hasHeader('X-Idempotency-Key')
                && $body['items'][0]['unit_price'] === 116.0
                && $body['items'][0]['currency_id'] === 'MXN'
                && str_contains($body['notification_url'], 'onboarding_attempt=');
        });
        $this->assertPreTenantIsolation();
    }

    public function test_browser_success_return_never_marks_paid_or_creates_operational_records(): void
    {
        $application = $this->readyForCheckout($this->catalog());
        $url = $this->base.'/zigo-platform/solicitud/'.$application->public_token
            .'/retorno/success?payment_id=999&status=approved&collection_status=approved&merchant_order_id=1';
        $this->get($url)->assertOk()->assertSee('Estamos verificando tu pago');

        $this->assertSame('PENDING_PAYMENT', $application->fresh()->status);
        $this->assertNull($application->paid_at);
        $this->assertPreTenantIsolation();
    }

    private function start(?NetworkCommercialProduct $offer = null, string $email = 'ANA@EXAMPLE.TEST'): SaasOnboardingApplication
    {
        $payload = $this->companyPayload();
        $payload['contact_email'] = $email;
        if ($offer) $payload['offer'] = $offer->uuid;
        $this->post($this->base.'/zigo-platform/comenzar', $payload)->assertRedirect();
        return SaasOnboardingApplication::where('contact_email', strtolower($email))->firstOrFail();
    }

    private function selectSolution(SaasOnboardingApplication $application, NetworkCommercialProduct $offer, array $extra = []): void
    {
        $this->patch(
            $this->base.'/zigo-platform/solicitud/'.$application->public_token.'/solucion',
            array_merge(['plan_offer' => (string) \Illuminate\Support\Str::uuid(), 'billing_period' => 'annual'], $extra),
        )->assertRedirect($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/plataforma');
    }

    private function readyForCheckout(NetworkCommercialProduct $offer): SaasOnboardingApplication
    {
        $application = $this->start($offer);
        $this->selectSolution($application, $offer);
        $this->patch($this->base.'/zigo-platform/solicitud/'.$application->public_token.'/plataforma', [
            'requested_subdomain' => 'checkout-company',
        ])->assertRedirect();
        return $application->fresh();
    }

    private function companyPayload(): array
    {
        return [
            'contact_name' => 'Ana', 'contact_last_name' => 'López',
            'contact_email' => 'ANA@EXAMPLE.TEST', 'contact_phone' => '+52 555 555 5555',
            'company_name' => 'ACME', 'company_legal_name' => 'ACME SA de CV', 'tax_id' => 'ACM010101AA1',
        ];
    }

    private function catalog(): NetworkCommercialProduct
    {
        $plan = Plan::create([
            'code' => 'PUBLIC', 'name' => 'Plan público', 'status' => 'active',
            'monthly_price' => '777.00', 'annual_price' => '7000.00',
            'currency' => 'MXN', 'included_operations' => 100,
        ]);
        return NetworkCommercialProduct::create([
            'code' => 'PUBLIC-M', 'name' => 'ZIGO Mensual', 'description' => 'Oferta pública',
            'type' => 'PLAN', 'billing_type' => 'MONTHLY', 'price' => '100.00',
            'currency' => 'MXN', 'plan_id' => $plan->id, 'is_active' => true,
        ]);
    }

    private function assertPreTenantIsolation(): void
    {
        foreach ([
            'network_tenants', 'users', 'network_tenant_memberships', 'network_subscriptions',
            'network_entitlements', 'network_tenant_domains', 'network_tenant_brandings',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} must remain empty.");
        }
    }

    private function schema(): void
    {
        foreach ([
            'platform_payment_attempts', 'saas_onboarding_events', 'saas_onboarding_applications',
            'network_entitlements', 'network_subscriptions', 'network_tenant_memberships',
            'network_tenant_brandings', 'network_tenant_domains', 'network_commercial_products',
            'network_plan_modules', 'network_tenants', 'network_plans', 'network_modules', 'users',
        ] as $table) Schema::dropIfExists($table);

        Schema::create('users', function (Blueprint $t): void {$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->timestamps();});
        Schema::create('network_modules', function (Blueprint $t): void {$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps();});
        Schema::create('network_plans', function (Blueprint $t): void {$t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps();});
        Schema::create('network_tenants', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps();});
        Schema::create('network_plan_modules', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps();$t->unique(['plan_id','module_id']);});
        Schema::create('network_tenant_domains', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id');$t->string('domain')->unique();$t->string('type');$t->string('environment');$t->boolean('is_primary');$t->string('status');$t->timestamp('verified_at')->nullable();$t->timestamps();});
        Schema::create('network_tenant_brandings', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id')->unique();$t->timestamps();});
        Schema::create('network_tenant_memberships', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps();});
        Schema::create('network_subscriptions', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');$t->timestamps();});
        Schema::create('network_entitlements', function (Blueprint $t): void {$t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->string('code');$t->timestamps();});
        Schema::create('network_commercial_products', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->string('billing_type');$t->decimal('price',12,2);$t->char('currency',3);$t->unsignedBigInteger('module_id')->nullable();$t->unsignedBigInteger('plan_id')->nullable();$t->unsignedInteger('included_operations')->nullable();$t->boolean('is_active');$t->unsignedInteger('sort_order')->default(0);$t->json('metadata')->nullable();$t->timestamps();});
        (require database_path('migrations/2026_08_19_100000_create_saas_onboarding_applications.php'))->up();
        (require database_path('migrations/2026_08_19_100100_create_saas_onboarding_events.php'))->up();
        Schema::create('platform_payment_attempts', function (Blueprint $t): void {$t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id')->nullable();$t->unsignedBigInteger('saas_order_id')->nullable();$t->unsignedBigInteger('onboarding_application_id')->nullable();$t->string('provider');$t->string('status');$t->string('provider_preference_id')->nullable();$t->string('provider_payment_id')->nullable();$t->string('external_reference')->unique();$t->decimal('amount',12,2);$t->char('currency',3);$t->text('init_point')->nullable();$t->timestamp('approved_at')->nullable();$t->timestamp('rejected_at')->nullable();$t->timestamps();});
    }
}
