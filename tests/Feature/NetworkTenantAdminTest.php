<?php

namespace Tests\Feature;

use App\Domain\Network\Catalog\Models\Module;
use App\Domain\Network\Catalog\Models\Plan;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\WhiteLabelTestSchema;
use Tests\TestCase;

final class NetworkTenantAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
    }

    public function test_membership_relations_are_unique_and_user_can_belong_to_multiple_tenants(): void
    {
        $user = $this->user('member@test.local');
        $a = $this->tenant('tenant-a');
        $b = $this->tenant('tenant-b');
        $first = $a->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);
        $b->memberships()->create(['user_id' => $user->id, 'role' => 'viewer', 'status' => 'active']);
        $this->assertTrue($first->tenant->is($a));
        $this->assertTrue($first->user->is($user));
        $this->assertCount(2, TenantMembership::where('user_id', $user->id)->get());
        $this->expectException(QueryException::class);
        $a->memberships()->create(['user_id' => $user->id, 'role' => 'admin', 'status' => 'active']);
    }

    public function test_guest_admin_redirects_to_login_on_same_valid_tenant_host(): void
    {
        $tenant = $this->tenantWithDomain('pilot');
        $this->get($this->url($tenant, '/admin'))->assertRedirect('/admin/login');
        $this->get($this->url($tenant, '/admin/login'))->assertOk()->assertSee('Acceso administrativo');
        $this->get('http://unknown.zigo.local/admin/login')->assertNotFound();
    }

    public function test_login_requires_active_membership_and_isolated_tenant_context(): void
    {
        $user = $this->user('owner@test.local');
        $a = $this->tenantWithDomain('tenant-a');
        $b = $this->tenantWithDomain('tenant-b');
        $a->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

        $this->post($this->url($b, '/admin/login'), ['email' => $user->email, 'password' => 'tenant-secret'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post($this->url($a, '/admin/login'), ['email' => $user->email, 'password' => 'tenant-secret'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->get($this->url($a, '/admin'))->assertOk()->assertSee($a->name);
        $this->get($this->url($b, '/admin'))->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_suspended_membership_and_suspended_tenant_cannot_enter(): void
    {
        $user = $this->user('suspended@test.local');
        $tenant = $this->tenantWithDomain('suspended-membership');
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'suspended']);
        $this->post($this->url($tenant, '/admin/login'), ['email' => $user->email, 'password' => 'tenant-secret'])->assertSessionHasErrors('email');
        $tenant->update(['status' => 'suspended']);
        $this->get($this->url($tenant, '/admin/login'))->assertNotFound();
    }

    public function test_dashboard_branding_and_modules_are_plan_scoped_and_plan_is_read_only(): void
    {
        [$tenant, $user] = $this->tenantOwner('dashboard');
        $tenant->branding()->create(['brand_name' => 'RapidGo Local', 'primary_color' => '#3B66F6']);
        $plan = Plan::create(['code' => 'LOCAL300', 'name' => 'Local 300', 'status' => 'active', 'currency' => 'MXN', 'included_operations' => 300]);
        $tracking = $this->module('TRACKING', 'Tracking');
        $commerce = $this->module('COMMERCE', 'Commerce');
        $plan->modules()->attach($tracking, ['is_included' => true]);
        $plan->modules()->attach($commerce, ['is_included' => false]);
        $tenant->update(['current_plan_id' => $plan->id]);
        $this->actingAs($user);
        $this->get($this->url($tenant, '/admin'))->assertOk()->assertSee('RapidGo Local')->assertSee('Tracking')->assertDontSee('Commerce');
        $this->get($this->url($tenant, '/admin/plan'))->assertOk()->assertSee('SÓLO LECTURA')->assertSee('Tracking')->assertSee('compra y activación inmediata')->assertDontSee('Commerce');
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutesByName())->has('tenant.admin.plan.update'));
    }

    public function test_configuration_page_is_tenant_scoped_and_navigation_has_no_dead_links(): void
    {
        [$tenant, $owner] = $this->tenantOwner('configuration');
        $response = $this->actingAs($owner)->get($this->url($tenant, '/admin/configuracion'))
            ->assertOk()->assertSee('configuration.zigo.local')->assertSee('PRODUCTION')->assertSee('VERIFIED')
            ->assertSee('https://configuration.zigo.local/white-label', false)
            ->assertDontSee('href="#"', false);
        foreach (['tenant.admin.dashboard', 'tenant.admin.operations.index', 'tenant.admin.users.index', 'tenant.admin.plan', 'tenant.admin.configuration.edit'] as $route) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($route));
        }
        $response->assertSee('class="active" href="'.route('tenant.admin.configuration.edit').'"', false);
    }

    public function test_owner_and_admin_can_update_only_current_tenant_branding_with_safe_uploads(): void
    {
        Storage::fake('public');
        [$tenant, $owner] = $this->tenantOwner('branding-owner');
        $admin = $this->user('branding-admin@test.local');
        $tenant->memberships()->create(['user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);
        $this->actingAs($owner)->patch($this->url($tenant, '/admin/configuracion'), [
            'brand_name' => 'Owner Brand', 'primary_color' => '#112233', 'logo' => UploadedFile::fake()->image('customer-logo.png')->size(100),
            'hero_image' => UploadedFile::fake()->image('hero.webp', 1200, 750)->size(500),
        ])->assertRedirect();
        $branding = $tenant->fresh()->branding;
        $this->assertSame('Owner Brand', $branding->brand_name);
        $this->assertStringStartsWith('tenant-branding/'.$tenant->uuid.'/', $branding->logo_path);
        $this->assertStringNotContainsString('customer-logo', $branding->logo_path);
        Storage::disk('public')->assertExists($branding->logo_path);
        Storage::disk('public')->assertExists($branding->hero_image_path);
        $this->assertStringStartsWith('tenant-branding/'.$tenant->uuid.'/', $branding->hero_image_path);
        $this->actingAs($owner)->get($this->url($tenant, '/admin'))->assertOk()
            ->assertSee('/branding/logo', false)
            ->assertDontSee(Storage::disk('public')->url($branding->logo_path), false);
        $this->get($this->url($tenant, '/branding/logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->url($tenant, '/branding/hero'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($owner)->get($this->url($tenant, '/admin/configuracion'))->assertOk()
            ->assertSee('Cambiar logo')->assertSee('Cambiar imagen principal de la landing')
            ->assertSee('1600 × 900 px')->assertSee('/branding/hero', false);

        Storage::disk('public')->delete($branding->logo_path);
        $this->get($this->url($tenant, '/admin'))->assertOk()
            ->assertSee('Owner Brand')->assertDontSee('<img', false);

        $this->actingAs($admin)->patch($this->url($tenant, '/admin/configuracion'), [
            'brand_name' => 'Admin Brand', 'secondary_color' => '#445566', 'accent_color' => '#778899', 'support_email' => 'support@example.test', 'support_phone' => '8112345678',
            'favicon' => UploadedFile::fake()->image('favicon.webp')->size(50),
        ])->assertRedirect();
        $this->assertSame('Admin Brand', $tenant->fresh()->branding->brand_name);
        Storage::disk('public')->assertExists($tenant->fresh()->branding->favicon_path);
    }

    public function test_operator_and_viewer_cannot_update_configuration(): void
    {
        $tenant = $this->tenantWithDomain('branding-readonly');
        foreach (['operator', 'viewer'] as $role) {
            $user = $this->user($role.'-branding@test.local');
            $tenant->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get($this->url($tenant, '/admin/configuracion'))->assertOk()->assertSee('Sólo owners y admins activos');
            $this->patch($this->url($tenant, '/admin/configuracion'), ['brand_name' => 'Forbidden'])->assertForbidden();
        }
        $this->assertNull($tenant->fresh()->branding);
    }

    public function test_tenant_admin_configures_tagline_and_exactly_three_allowlisted_landing_cards(): void
    {
        [$tenant,$owner]=$this->tenantOwner('landing-cards');
        $cards=[['title'=>'Uno','description'=>'Primera','action'=>'QUOTE'],['title'=>'Dos','description'=>'Segunda','action'=>'REGISTER'],['title'=>'Tres','description'=>'Tercera','action'=>'TRACKING']];
        $this->actingAs($owner)->patch($this->url($tenant,'/admin/configuracion'),['tagline'=>'Servicio cercano','landing_cards'=>$cards])->assertRedirect();
        $this->assertSame('Servicio cercano',$tenant->fresh()->branding->tagline);
        $this->assertSame($cards,$tenant->fresh()->branding->landing_cards);
        $this->patch($this->url($tenant,'/admin/configuracion'),['landing_cards'=>array_replace($cards,[0=>['title'=>'Ataque','description'=>'No permitido','action'=>'https://externo.test']])])->assertSessionHasErrors('landing_cards.0.action');
        $this->patch($this->url($tenant,'/admin/configuracion'),['landing_cards'=>array_slice($cards,0,2)])->assertSessionHasErrors('landing_cards');
    }

    public function test_configuration_rejects_invalid_colors_svg_and_non_images(): void
    {
        Storage::fake('public');
        [$tenant, $owner] = $this->tenantOwner('branding-validation');
        $this->actingAs($owner)->patch($this->url($tenant, '/admin/configuracion'), [
            'primary_color' => 'red', 'secondary_color' => '#12345G', 'accent_color' => 'javascript:red',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            'favicon' => UploadedFile::fake()->create('favicon.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors(['primary_color', 'secondary_color', 'accent_color', 'logo', 'favicon']);
        $this->assertNull($tenant->fresh()->branding);
    }

    public function test_configuration_cannot_change_domain_or_another_tenant_branding(): void
    {
        [$a, $owner] = $this->tenantOwner('scope-a');
        $b = $this->tenantWithDomain('scope-b');
        $originalDomain = $a->primaryDomain()->first()->domain;
        $this->actingAs($owner)->patch($this->url($a, '/admin/configuracion'), [
            'brand_name' => 'Scoped A', 'tenant_id' => $b->id, 'domain' => 'attacker.test', 'environment' => 'sandbox', 'status' => 'disabled', 'is_primary' => false,
        ])->assertRedirect();
        $this->assertSame('Scoped A', $a->fresh()->branding->brand_name);
        $this->assertNull($b->fresh()->branding);
        $this->assertSame($originalDomain, $a->fresh()->primaryDomain()->first()->domain);
        $this->assertSame('production', $a->fresh()->primaryDomain()->first()->environment);
        $this->assertSame('verified', $a->fresh()->primaryDomain()->first()->status);
    }

    public function test_owner_manages_members_but_last_active_owner_is_preserved(): void
    {
        [$tenant, $owner] = $this->tenantOwner('manage');
        $operator = $this->user('operator@test.local');
        $membership = $tenant->memberships()->create(['user_id' => $operator->id, 'role' => 'operator', 'status' => 'active']);
        $this->actingAs($owner);
        $this->patch($this->url($tenant, '/admin/users/'.$membership->id), ['role' => 'support', 'status' => 'active'])->assertRedirect();
        $this->assertSame('support', $membership->fresh()->role);
        $ownerMembership = $tenant->memberships()->where('user_id', $owner->id)->first();
        $this->patch($this->url($tenant, '/admin/users/'.$ownerMembership->id), ['role' => 'admin', 'status' => 'active'])->assertSessionHasErrors('role');
        $this->assertSame('owner', $ownerMembership->fresh()->role);
    }

    public function test_admin_cannot_change_owner_and_operator_cannot_manage_users(): void
    {
        [$tenant, $owner] = $this->tenantOwner('roles');
        $admin = $this->user('admin@test.local');
        $operator = $this->user('operator2@test.local');
        $tenant->memberships()->create(['user_id' => $admin->id, 'role' => 'admin', 'status' => 'active']);
        $tenant->memberships()->create(['user_id' => $operator->id, 'role' => 'operator', 'status' => 'active']);
        $ownerMembership = $tenant->memberships()->where('user_id', $owner->id)->first();
        $this->actingAs($admin);
        $this->patch($this->url($tenant, '/admin/users/'.$ownerMembership->id), ['role' => 'viewer', 'status' => 'active'])->assertForbidden();
        $this->actingAs($operator);
        $this->get($this->url($tenant, '/admin/users'))->assertForbidden();
    }

    public function test_logout_invalidates_tenant_session(): void
    {
        [$tenant, $owner] = $this->tenantOwner('logout');
        $this->actingAs($owner);
        $this->post($this->url($tenant, '/admin/logout'))->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_network_sysadmin_can_add_existing_user_and_tenant_owner_does_not_gain_network_access(): void
    {
        [$tenant, $owner] = $this->tenantOwner('network');
        $sysadmin = $this->user('sysadmin@test.local', 'sysadmin');
        $newUser = $this->user('new@test.local');
        $this->actingAs($sysadmin)->post("/network/tenants/{$tenant->id}/members", ['email' => $newUser->email, 'role' => 'viewer', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $newUser->id, 'role' => 'viewer']);
        $this->actingAs($sysadmin)->post("/network/tenants/{$tenant->id}/members", ['email' => 'missing@test.local', 'role' => 'viewer', 'status' => 'active'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->get('/network')->assertForbidden();
    }

    public function test_network_requires_first_membership_to_be_an_active_owner(): void
    {
        $tenant = $this->tenant('first-owner');
        $sysadmin = $this->user('network-admin@test.local', 'sysadmin');
        $user = $this->user('first@test.local');
        $this->actingAs($sysadmin)->post("/network/tenants/{$tenant->id}/members", ['email' => $user->email, 'role' => 'admin', 'status' => 'active'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('network_tenant_memberships', ['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    }

    public function test_tenant_host_cannot_reach_legacy_admin_routes_even_with_a_valid_session(): void
    {
        [$tenant, $owner] = $this->tenantOwner('legacy-boundary');
        DB::table('b2c_cotizaciones')->insert(['id' => 1]);
        $this->configureLegacyB2cHost();
        $this->actingAs($owner);
        $this->get($this->url($tenant, '/admin/incidencias'))->assertNotFound();
        $this->get($this->url($tenant, '/admin/conciliacion-saldo/1'))->assertNotFound();
    }

    public function test_legacy_admin_is_available_only_on_b2c_host_and_keeps_auth_and_roles(): void
    {
        $this->configureLegacyB2cHost();
        $this->get('http://internal-b2c.test/admin/incidencias')->assertRedirect('/login');
        $this->actingAs($this->user('no-global-role@test.local'))
            ->get('http://internal-b2c.test/admin/incidencias')->assertForbidden();
        $this->actingAs($this->user('legacy-admin@test.local', 'sysadmin'))
            ->get('http://wrong-internal.test/admin/incidencias')->assertNotFound();
    }

    private function url(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->primaryDomain()->first()->domain.$path;
    }

    private function configureLegacyB2cHost(): void
    {
        config([
            'zigo_domains.portals.b2c.host' => 'internal-b2c.test',
            'zigo_domains.portals.b2c.url' => 'http://internal-b2c.test',
        ]);
    }

    private function tenantOwner(string $slug): array
    {
        $tenant = $this->tenantWithDomain($slug);
        $user = $this->user($slug.'@test.local');
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

        return [$tenant, $user];
    }

    private function tenantWithDomain(string $slug): Tenant
    {
        $tenant = $this->tenant($slug);
        $tenant->domains()->create(['domain' => $slug.'.zigo.local', 'type' => 'subdomain', 'environment' => 'production', 'is_primary' => true, 'status' => 'verified', 'verified_at' => now()]);

        return $tenant;
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
    }

    private function module(string $code, string $name): Module
    {
        return Module::create(['code' => $code, 'name' => $name, 'type' => 'addon', 'is_active' => true, 'sort_order' => 1]);
    }

    private function user(string $email, ?string $globalRole = null): User
    {
        $user = User::forceCreate(['name' => 'Test User', 'email' => $email, 'password' => Hash::make('tenant-secret'), 'empresa_id' => 1]);
        if ($globalRole) {
            $role = Roles::create(['name' => $globalRole, 'slug' => $globalRole]);
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function schema(): void
    {
        foreach (['b2c_cotizaciones', 'network_tenant_memberships', 'network_tenant_brandings', 'network_tenant_domains', 'network_plan_modules', 'network_tenants', 'network_plans', 'network_modules', 'users_roles', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->unsignedBigInteger('empresa_id');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->timestamps();
        });
        Schema::create('users_roles', function (Blueprint $t): void {
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('roles_id');
        });
        Schema::create('network_modules', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('type');
            $t->boolean('is_active');
            $t->unsignedSmallInteger('sort_order');
            $t->timestamps();
        });
        Schema::create('network_plans', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status');
            $t->decimal('monthly_price', 12, 2)->nullable();
            $t->decimal('annual_price', 12, 2)->nullable();
            $t->char('currency', 3);
            $t->unsignedInteger('included_operations')->nullable();
            $t->timestamps();
        });
        Schema::create('network_tenants', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('status');
            $t->unsignedBigInteger('current_plan_id')->nullable();
            $t->timestamps();
        });
        Schema::create('network_plan_modules', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('plan_id');
            $t->unsignedBigInteger('module_id');
            $t->boolean('is_included');
            $t->unsignedInteger('limit_value')->nullable();
            $t->timestamps();
            $t->unique(['plan_id', 'module_id']);
        });
        WhiteLabelTestSchema::create();
        Schema::create('network_tenant_memberships', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role');
            $t->string('status');
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id']);
        });
        Schema::create('b2c_cotizaciones', function (Blueprint $t): void {
            $t->id();
        });
    }
}
