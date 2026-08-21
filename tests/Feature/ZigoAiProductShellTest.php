<?php

namespace Tests\Feature;

use App\Domain\AI\Launchpad\Data\LaunchpadIntakeData;
use App\Domain\AI\Launchpad\Services\{ConvertLaunchpadSessionToAgentDraftService, GenerateLaunchpadRecommendationService};
use App\Domain\Network\Billing\Models\{Entitlement, Subscription};
use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\ProductShell\{TenantWorkspace, TenantWorkspaceResolver};
use App\Domain\Network\Tenancy\Models\{Tenant, TenantDomain, TenantMembership};
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\User;
use App\Support\Presentation\AiAgentPresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

final class ZigoAiProductShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'ai.enabled' => true]);
        $this->schema();
    }

    public function test_resolver_selects_platform_for_shipping_with_or_without_ai(): void
    {
        foreach ([['SHIPPING'], ['SHIPPING', 'AI_CORE']] as $index => $codes) {
            [$tenant] = $this->tenant("platform-{$index}", $codes);
            app(TenantContext::class)->set($tenant);
            $this->assertSame(TenantWorkspace::ZigoPlatform, app(TenantWorkspaceResolver::class)->resolve($tenant));
        }
    }

    public function test_resolver_selects_ai_only_and_fails_closed_without_valid_context(): void
    {
        [$tenant] = $this->tenant('ai-resolver', ['AI_CORE']);
        app(TenantContext::class)->set($tenant);
        $this->assertSame(TenantWorkspace::ZigoAi, app(TenantWorkspaceResolver::class)->resolve($tenant));
        app(TenantContext::class)->clear();
        $this->expectException(AuthorizationException::class);
        app(TenantWorkspaceResolver::class)->resolve($tenant);
    }

    public function test_ai_workspace_header_dashboard_and_navigation_are_ai_focused(): void
    {
        [$tenant, $owner] = $this->tenant('ai-shell', ['AI_CORE']);
        $response = $this->actingAs($owner)->get($this->url($tenant, '/admin'))->assertOk();
        $response->assertSee('ZIGO AI')->assertSee($tenant->name)->assertSee('AGENTES CREADOS')
            ->assertSee('Mis agentes')->assertSee('Crear agente')->assertSee('Facturación')
            ->assertDontSee('Motor logístico')->assertDontSee('Recolecciones y despacho')->assertDontSee('Drivers')
            ->assertDontSee('OPERACIONES USADAS')->assertDontSee('href="#"', false);
    }

    public function test_platform_header_keeps_operations_and_shows_ai_only_when_entitled(): void
    {
        [$plain, $plainOwner] = $this->tenant('plain-platform', ['SHIPPING']);
        $this->actingAs($plainOwner)->get($this->url($plain, '/admin'))->assertOk()
            ->assertSee('ZIGO')->assertSee($plain->name)->assertSee('Motor logístico')->assertDontSee('Mis agentes');
        [$ai, $aiOwner] = $this->tenant('ai-platform', ['SHIPPING', 'AI_CORE']);
        $this->actingAs($aiOwner)->get($this->url($ai, '/admin'))->assertOk()
            ->assertSee('Motor logístico')->assertSee('Mis agentes')->assertSee('Agentes IA');
    }

    public function test_ai_only_direct_logistics_urls_fail_closed_and_platform_keeps_access(): void
    {
        [$ai, $aiOwner] = $this->tenant('ai-direct', ['AI_CORE']);
        foreach (['/admin/operacion', '/admin/operations', '/admin/drivers', '/admin/dispatch/pickups'] as $path) {
            $this->actingAs($aiOwner)->get($this->url($ai, $path))->assertForbidden();
        }
        [$platform, $platformOwner] = $this->tenant('platform-direct', ['SHIPPING']);
        $this->actingAs($platformOwner)->get($this->url($platform, '/admin/operations'))->assertOk();
    }

    public function test_ai_views_use_zigo_and_commercial_labels_and_explain_draft_state(): void
    {
        [$tenant, $owner] = $this->tenant('ai-copy', ['AI_CORE']);
        app(TenantContext::class)->set($tenant);
        $intake = LaunchpadIntakeData::fromArray(['problem_description' => 'Necesito solicitudes operativas', 'objective_code' => 'custom_operational', 'requested_channels' => ['webchat', 'whatsapp'], 'knowledge_source_types' => ['faq']]);
        $session = app(GenerateLaunchpadRecommendationService::class)->generate($owner, $intake);
        app(TenantContext::class)->clear();
        $proposal = $this->actingAs($owner)->get($this->url($tenant, '/admin/ai-agents/launchpad/'.$session->uuid))->assertOk();
        $proposal->assertSee('Agente operativo personalizado')->assertSee('Confianza Alta')->assertSee('Solicitud operativa creada')
            ->assertSee('Chat web')->assertSee('WhatsApp')->assertSee('El equipo ZIGO')->assertDontSee('custom_operational')->assertDontSee('operational_request_created')->assertDontSee('INNOTECH');
        app(TenantContext::class)->set($tenant);
        $agent = app(ConvertLaunchpadSessionToAgentDraftService::class)->convert($owner, $session->fresh());
        app(TenantContext::class)->clear();
        $detail = $this->get($this->url($tenant, '/admin/ai-agents/'.$agent->id))->assertOk();
        $detail->assertSee('El borrador fue creado')->assertSee('Todavía no conversa con clientes')->assertSee('Conocimiento: pendiente')
            ->assertSee('Canal Chat web: pendiente')->assertSee('Contrato: pendiente')->assertSee('Publicación: pendiente')
            ->assertSee('Volver a Mis agentes')->assertDontSee('INNOTECH')->assertDontSee('custom_operational');
    }

    public function test_ai_resources_remain_tenant_isolated(): void
    {
        [$a, $ownerA] = $this->tenant('shell-a', ['AI_CORE']);
        app(TenantContext::class)->set($a);
        $session = app(GenerateLaunchpadRecommendationService::class)->generate($ownerA, LaunchpadIntakeData::fromArray(['problem_description' => 'Ventas', 'objective_code' => 'sales']));
        [$b, $ownerB] = $this->tenant('shell-b', ['AI_CORE']);
        app(TenantContext::class)->clear();
        $this->actingAs($ownerB)->get($this->url($b, '/admin/ai-agents/launchpad/'.$session->uuid))->assertNotFound();
    }

    public function test_resolver_complete_product_and_failure_matrix(): void
    {
        foreach ([
            [['AI_CORE'], TenantWorkspace::ZigoAi],
            [['AI_CORE', 'SHIPPING'], TenantWorkspace::ZigoPlatform],
            [['SHIPPING'], TenantWorkspace::ZigoPlatform],
            [['TRACKING'], TenantWorkspace::ZigoPlatform],
            [['DRIVER'], TenantWorkspace::ZigoPlatform],
        ] as $index => [$codes, $expected]) {
            [$tenant] = $this->tenant('matrix-'.$index, $codes);
            app(TenantContext::class)->set($tenant);
            $this->assertSame($expected, app(TenantWorkspaceResolver::class)->resolve($tenant));
        }

        [$expired] = $this->tenant('matrix-expired', ['AI_CORE']);
        Subscription::where('tenant_id', $expired->id)->update(['current_period_end' => now()->subMinute()]);
        app(TenantContext::class)->set($expired);
        $this->assertWorkspaceRejected($expired);

        [$inactive] = $this->tenant('matrix-inactive', ['AI_CORE']);
        $inactive->update(['status' => 'inactive']);
        app(TenantContext::class)->set($inactive->fresh());
        $this->assertWorkspaceRejected($inactive->fresh());

        [$withoutContext] = $this->tenant('matrix-no-context', ['AI_CORE']);
        app(TenantContext::class)->clear();
        $this->assertWorkspaceRejected($withoutContext);

        [$expectedTenant] = $this->tenant('matrix-context-a', ['AI_CORE']);
        [$otherTenant] = $this->tenant('matrix-context-b', ['AI_CORE']);
        app(TenantContext::class)->set($otherTenant);
        $this->assertWorkspaceRejected($expectedTenant);

        [$empty] = $this->tenant('matrix-empty', []);
        app(TenantContext::class)->set($empty);
        $this->assertWorkspaceRejected($empty);

        [$disabledOperational] = $this->tenant('matrix-disabled-operational', ['TRACKING']);
        Entitlement::where('tenant_id', $disabledOperational->id)->update(['is_enabled' => false]);
        app(TenantContext::class)->set($disabledOperational);
        $this->assertWorkspaceRejected($disabledOperational);

        [$disabledAi] = $this->tenant('matrix-disabled-ai', ['AI_CORE']);
        Entitlement::where('tenant_id', $disabledAi->id)->update(['is_enabled' => false]);
        app(TenantContext::class)->set($disabledAi);
        $this->assertWorkspaceRejected($disabledAi);

        [$aiWithDisabledOperational] = $this->tenant('matrix-ai-disabled-operational', ['AI_CORE', 'DRIVER']);
        Entitlement::where('tenant_id', $aiWithDisabledOperational->id)->where('code', 'DRIVER')->update(['is_enabled' => false]);
        app(TenantContext::class)->set($aiWithDisabledOperational);
        $this->assertSame(TenantWorkspace::ZigoAi, app(TenantWorkspaceResolver::class)->resolve($aiWithDisabledOperational));
    }

    public function test_ai_only_blocks_all_operational_http_surfaces_without_database_changes(): void
    {
        [$tenant, $owner] = $this->tenant('ai-all-surfaces', ['AI_CORE']);
        $fixtures = $this->operationalFixtures($tenant, $owner);
        $base = $this->url($tenant, '');
        $before = $this->operationalSnapshot();

        $requests = [
            fn () => $this->actingAs($owner)->get($base.'/admin/api'),
            fn () => $this->post($base.'/admin/api/clients', ['name' => 'Blocked', 'environment' => 'STAGE']),
            fn () => $this->post($base.'/admin/api/clients/'.$fixtures['client'].'/keys', ['name' => 'Blocked', 'scopes' => ['quotes:read']]),
            fn () => $this->delete($base.'/admin/api/clients/'.$fixtures['client'].'/keys/'.$fixtures['key']),
            fn () => $this->post($base.'/admin/api/clients/'.$fixtures['client'].'/webhooks', ['name' => 'Blocked', 'url' => 'https://example.test/hook', 'events' => ['operation.created']]),
            fn () => $this->post($base.'/admin/api/webhooks/'.$fixtures['webhook'].'/rotate'),
            fn () => $this->patch($base.'/admin/api/webhooks/'.$fixtures['webhook'], ['status' => 'DISABLED']),
            fn () => $this->get($base.'/admin/configuracion/entregas'),
            fn () => $this->post($base.'/admin/configuracion/entregas', ['code' => 'BLOCKED']),
            fn () => $this->put($base.'/admin/configuracion/entregas/'.$fixtures['option'], ['name' => 'Changed']),
            fn () => $this->get($base.'/admin/delivery-proofs/'.$fixtures['proof'].'/photo'),
            fn () => $this->get($base.'/admin/delivery-failures/'.$fixtures['failure'].'/photo'),
            fn () => $this->post($base.'/admin/operacion/servicios', ['name' => 'Blocked']),
            fn () => $this->put($base.'/admin/operacion/servicios/'.$fixtures['service'], ['name' => 'Changed']),
            fn () => $this->patch($base.'/admin/operacion/cobertura/'.$fixtures['coverage']),
            fn () => $this->delete($base.'/admin/operacion/cobertura/'.$fixtures['coverage']),
            fn () => $this->post($base.'/admin/operations/'.$fixtures['operation'].'/local-shipment'),
            fn () => $this->post($base.'/admin/local-shipments/'.$fixtures['shipment'].'/transition', ['status' => 'IN_TRANSIT']),
            fn () => $this->patch($base.'/admin/drivers/'.$fixtures['driver'].'/toggle'),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }

        $this->assertSame($before, $this->operationalSnapshot());
    }

    public function test_platform_workspace_preserves_existing_operational_get_access(): void
    {
        [$tenant, $owner] = $this->tenant('platform-surfaces', ['SHIPPING']);
        $this->operationalFixtures($tenant, $owner);
        $base = $this->url($tenant, '');

        $this->actingAs($owner)->get($base.'/admin/api')->assertOk();
        $this->get($base.'/admin/configuracion/entregas')->assertOk();
        $this->get($base.'/admin/operations')->assertOk();
    }

    public function test_presenter_neutralizes_unknown_and_malicious_codes(): void
    {
        $unknown = '<script>alert(1)</script>';
        $this->assertSame('Agente personalizado', AiAgentPresentation::agentType($unknown));
        $this->assertSame('Alternativa disponible', AiAgentPresentation::alternative($unknown));
        $this->assertSame('No determinada', AiAgentPresentation::confidence($unknown));
        $this->assertSame('Resultado configurado', AiAgentPresentation::outcome($unknown));
        $this->assertSame('Canal configurado', AiAgentPresentation::channel($unknown));
        foreach ([AiAgentPresentation::agentType($unknown), AiAgentPresentation::alternative($unknown), AiAgentPresentation::confidence($unknown), AiAgentPresentation::outcome($unknown), AiAgentPresentation::channel($unknown)] as $label) {
            $this->assertStringNotContainsString('<script>', $label);
        }
    }

    public function test_presentation_fallback_is_not_an_authorization_dependency(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/EnsureTenantOperationalWorkspace.php'));
        $routes = file_get_contents(base_path('routes/network.php'));
        $controllers = collect(glob(app_path('Http/Controllers/**/*.php')))->map(fn ($file) => file_get_contents($file))->implode("\n");
        $this->assertStringNotContainsString('resolveForPresentation', $middleware);
        $this->assertStringNotContainsString('resolveForPresentation', $routes);
        $this->assertSame(1, substr_count($controllers, 'resolveForPresentation('));
    }

    private function assertWorkspaceRejected(Tenant $tenant): void
    {
        try {
            app(TenantWorkspaceResolver::class)->resolve($tenant);
            $this->fail('Workspace resolution must fail closed.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('Tenant workspace unavailable.', $exception->getMessage());
        }
    }

    private function operationalFixtures(Tenant $tenant, User $owner): array
    {
        $client = (string) Str::uuid();
        $key = (string) Str::uuid();
        $webhook = (string) Str::uuid();
        $option = (string) Str::uuid();
        $proof = (string) Str::uuid();
        $failure = (string) Str::uuid();
        $operation = (string) Str::uuid();
        $shipment = (string) Str::uuid();
        $driver = (string) Str::uuid();

        $operationId = DB::table('network_tenant_operations')->insertGetId(['uuid' => $operation, 'tenant_id' => $tenant->id, 'status' => 'quoted', 'created_at' => now(), 'updated_at' => now()]);
        $shipmentId = DB::table('local_shipments')->insertGetId(['uuid' => $shipment, 'tenant_id' => $tenant->id, 'tenant_operation_id' => $operationId, 'status' => 'READY_FOR_PICKUP', 'created_at' => now(), 'updated_at' => now()]);
        $clientId = DB::table('tenant_api_clients')->insertGetId(['uuid' => $client, 'tenant_id' => $tenant->id, 'name' => 'Fixture', 'environment' => 'STAGE', 'status' => 'ACTIVE', 'created_by_user_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_api_keys')->insert(['uuid' => $key, 'tenant_id' => $tenant->id, 'api_client_id' => $clientId, 'key_prefix' => 'zigo', 'key_hash' => hash('sha256', $key), 'last_four' => '1234', 'name' => 'Fixture', 'scopes' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_api_webhook_endpoints')->insert(['uuid' => $webhook, 'tenant_id' => $tenant->id, 'api_client_id' => $clientId, 'name' => 'Fixture', 'url' => 'https://example.test/hook', 'events' => '[]', 'status' => 'ACTIVE', 'secret_encrypted' => 'unchanged-secret', 'secret_prefix' => 'unchanged', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_delivery_proof_options')->insert(['uuid' => $option, 'tenant_id' => $tenant->id, 'code' => 'FIXTURE', 'name' => 'Fixture', 'require_photo' => true, 'receiver_policy' => 'ANY_PERSON_AT_ADDRESS', 'max_delivery_attempts' => 2, 'currency' => 'MXN', 'is_default' => true, 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('local_delivery_proofs')->insert(['uuid' => $proof, 'tenant_id' => $tenant->id, 'local_shipment_id' => $shipmentId, 'photo_path' => 'fixture/photo.jpg', 'captured_at' => now(), 'created_at' => now()]);
        DB::table('local_delivery_failed_attempts')->insert(['uuid' => $failure, 'tenant_id' => $tenant->id, 'local_shipment_id' => $shipmentId, 'photo_path' => 'fixture/failure.jpg', 'status' => 'OPEN', 'occurred_at' => now(), 'created_at' => now()]);
        DB::table('tenant_driver_profiles')->insert(['uuid' => $driver, 'tenant_id' => $tenant->id, 'user_id' => $owner->id, 'code' => 'FIXTURE', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $service = (string) DB::table('local_shipping_services')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Fixture', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $coverage = (string) DB::table('local_shipping_zone_postal_codes')->insertGetId(['tenant_id' => $tenant->id, 'postal_code' => '01000', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return compact('client', 'key', 'webhook', 'option', 'proof', 'failure', 'operation', 'shipment', 'driver', 'service', 'coverage');
    }

    private function operationalSnapshot(): array
    {
        return [
            'clients' => DB::table('tenant_api_clients')->count(),
            'keys' => DB::table('tenant_api_keys')->count(),
            'key_revoked_at' => DB::table('tenant_api_keys')->value('revoked_at'),
            'webhooks' => DB::table('tenant_api_webhook_endpoints')->count(),
            'webhook_status' => DB::table('tenant_api_webhook_endpoints')->value('status'),
            'webhook_secret' => DB::table('tenant_api_webhook_endpoints')->value('secret_encrypted'),
            'options' => DB::table('tenant_delivery_proof_options')->count(),
            'option_name' => DB::table('tenant_delivery_proof_options')->value('name'),
            'operations' => DB::table('network_tenant_operations')->count(),
            'shipments' => DB::table('local_shipments')->count(),
            'shipment_status' => DB::table('local_shipments')->value('status'),
            'services' => DB::table('local_shipping_services')->count(),
            'service_name' => DB::table('local_shipping_services')->value('name'),
            'coverages' => DB::table('local_shipping_zone_postal_codes')->count(),
            'coverage_active' => DB::table('local_shipping_zone_postal_codes')->value('active'),
            'driver_status' => DB::table('tenant_driver_profiles')->value('status'),
        ];
    }

    private function tenant(string $slug, array $codes): array
    {
        $tenant = Tenant::create(['name' => 'Empresa '.$slug, 'slug' => $slug, 'status' => 'active']);
        TenantDomain::create(['tenant_id' => $tenant->id, 'domain' => $slug.'.test', 'type' => 'subdomain', 'environment' => 'sandbox', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);
        $owner = User::create(['name' => 'Owner', 'email' => $slug.'@test', 'password' => 'hashed', 'empresa_id' => 1]);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $plan = Plan::create(['code' => 'P-'.$slug, 'name' => 'Plan '.$slug, 'status' => 'active', 'currency' => 'MXN']);
        $subscription = Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        foreach ($codes as $code) {
            $module = Module::firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'core', 'is_active' => true, 'sort_order' => 1]);
            Entitlement::create(['subscription_id' => $subscription->id, 'tenant_id' => $tenant->id, 'module_id' => $module->id, 'code' => $code, 'is_enabled' => true, 'source' => 'plan']);
        }
        return [$tenant, $owner];
    }

    private function url(Tenant $tenant, string $path): string { return 'http://'.$tenant->slug.'.test'.$path; }

    private function schema(): void
    {
        DB::statement('PRAGMA foreign_keys=ON');
        Schema::create('users', function (Blueprint $t) { $t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->unsignedBigInteger('empresa_id');$t->rememberToken();$t->timestamps(); });
        Schema::create('network_plans', function (Blueprint $t) { $t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('status');$t->decimal('monthly_price',12,2)->nullable();$t->decimal('annual_price',12,2)->nullable();$t->char('currency',3);$t->unsignedInteger('included_operations')->nullable();$t->timestamps(); });
        Schema::create('network_tenants', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->string('name');$t->string('slug')->unique();$t->string('status');$t->unsignedBigInteger('current_plan_id')->nullable();$t->timestamps(); });
        Schema::create('network_tenant_domains', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->string('domain')->unique();$t->string('type');$t->string('environment');$t->boolean('is_primary');$t->string('status');$t->timestamp('verified_at')->nullable();$t->timestamps(); });
        Schema::create('network_tenant_brandings', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id')->unique();$t->string('brand_name')->nullable();$t->string('logo_path')->nullable();$t->string('favicon_path')->nullable();$t->string('primary_color')->nullable();$t->string('secondary_color')->nullable();$t->string('accent_color')->nullable();$t->timestamps(); });
        Schema::create('network_tenant_memberships', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('role');$t->string('status');$t->timestamps(); });
        Schema::create('network_modules', function (Blueprint $t) { $t->id();$t->string('code')->unique();$t->string('name');$t->text('description')->nullable();$t->string('type');$t->boolean('is_active');$t->unsignedSmallInteger('sort_order');$t->timestamps(); });
        Schema::create('network_plan_modules', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('plan_id');$t->unsignedBigInteger('module_id');$t->boolean('is_included');$t->unsignedInteger('limit_value')->nullable();$t->timestamps(); });
        Schema::create('network_subscriptions', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('plan_id');$t->string('status');$t->unsignedInteger('operations_limit')->nullable();foreach(['started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at'] as $c)$t->timestamp($c)->nullable();$t->timestamps(); });
        Schema::create('network_entitlements', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('subscription_id');$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('module_id');$t->string('code');$t->boolean('is_enabled');$t->unsignedInteger('limit_value')->nullable();$t->string('source');$t->timestamps(); });
        Schema::create('network_usage_events', function (Blueprint $t) { $t->id();$t->uuid('uuid')->nullable();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('subscription_id');$t->string('metric');$t->unsignedInteger('quantity');$t->string('idempotency_key')->nullable();$t->timestamp('occurred_at');$t->json('metadata')->nullable();$t->timestamps(); });
        Schema::create('network_tenant_operations', function (Blueprint $t) { $t->id();$t->uuid('uuid');$t->unsignedBigInteger('tenant_id');$t->string('external_reference')->nullable();$t->string('status')->default('quoted');$t->timestamps(); });
        Schema::create('local_shipments', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('tenant_operation_id');$t->string('status');$t->timestamps(); });
        Schema::create('tenant_saas_orders', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->timestamps(); });
        Schema::create('tenant_delivery_proof_options', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->string('code');$t->string('name');$t->string('description')->nullable();foreach(['require_receiver_name','require_receiver_type','require_signature','require_photo','require_gps'] as $c)$t->boolean($c)->default(false);$t->string('receiver_policy');$t->unsignedSmallInteger('max_delivery_attempts');$t->decimal('surcharge_amount',10,2)->nullable();$t->char('currency',3);$t->boolean('is_default');$t->boolean('is_active');$t->unsignedInteger('sort_order');$t->timestamps(); });
        Schema::create('local_delivery_proofs', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('local_shipment_id');$t->string('photo_path')->nullable();$t->string('signature_path')->nullable();$t->timestamp('captured_at');$t->timestamp('created_at')->nullable(); });
        Schema::create('local_delivery_failed_attempts', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('local_shipment_id');$t->string('photo_path')->nullable();$t->string('status');$t->timestamp('occurred_at');$t->timestamp('created_at')->nullable(); });
        Schema::create('tenant_driver_profiles', function (Blueprint $t) { $t->id();$t->uuid('uuid')->unique();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('user_id');$t->string('code');$t->string('status');$t->timestamps(); });
        Schema::create('local_shipping_services', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->string('name');$t->string('status');$t->timestamps(); });
        Schema::create('local_shipping_zone_postal_codes', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->string('postal_code');$t->boolean('active');$t->timestamps(); });
        (require database_path('migrations/2026_08_17_100000_create_tenant_api_hub_v1.php'))->up();
        (require database_path('migrations/2026_08_22_100000_create_ai_agent_domain_tables.php'))->up();
        (require database_path('migrations/2026_08_23_100000_add_ai_agent_lifecycle.php'))->up();
        (require database_path('migrations/2026_08_24_100000_create_ai_agent_launchpad_sessions.php'))->up();
    }
}
