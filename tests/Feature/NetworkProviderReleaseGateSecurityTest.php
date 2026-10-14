<?php

namespace Tests\Feature;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ProviderApiFixture;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\TestCase;

/** Extra security gates for the surfaces implicated by baseline host assertions. */
final class NetworkProviderReleaseGateSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Isolated SQLite required');
        }
        Http::preventStrayRequests(); Mail::fake();
        ProviderApiFixture::schema();
        $f = ProviderContractFixture::seed(); ProviderFulfillmentFixture::operational($f);
        app(TenantContext::class)->set($f['consumer']);
    }

    protected function tearDown(): void
    {
        app()->detectEnvironment(fn () => 'testing');
        parent::tearDown();
    }

    public function test_provider_administration_is_accessible_on_configured_nonlocal_host_but_untrusted_host_is_denied(): void
    {
        app()->detectEnvironment(fn () => 'staging');
        config(['zigo_domains.routing_enabled' => true]);
        $admin = User::create(['name' => 'Gate Admin', 'email' => 'gate-admin@example.test', 'password' => 'unused']);
        $role = Roles::create(['slug' => 'sysadmin']); $admin->roles()->attach($role->id);
        $host = 'https://'.config('zigo_surfaces.network.host');
        $this->actingAs($admin)->withSession(['network.2fa_user_id' => $admin->id]);
        $this->get($host.'/network/provider-connections')->assertOk()->assertSee('Provider Express');
        $this->get($host.'/network/provider-settlements')->assertOk();
        // The original smoke still expects 404. Here the security property is explicit:
        // untrusted hosts must be rejected before any provider information is rendered.
        foreach (['/network/provider-connections', '/network/provider-settlements'] as $path) {
            $this->get('https://untrusted.invalid'.$path)->assertStatus(400)->assertDontSee('Provider Express');
        }
    }

    public function test_nonlocal_provider_network_requires_two_factor_and_sysadmin(): void
    {
        app()->detectEnvironment(fn () => 'staging');
        config(['zigo_domains.routing_enabled' => true]);
        $host = 'https://'.config('zigo_surfaces.network.host');
        $admin = User::create(['name' => 'Gate Admin', 'email' => 'gate-admin@example.test', 'password' => 'unused']);
        $role = Roles::create(['slug' => 'sysadmin']); $admin->roles()->attach($role->id);
        $this->actingAs($admin)->withSession(['network.2fa_user_id' => 0]);
        $this->get($host.'/network/provider-connections')->assertRedirect();
        $this->get($host.'/network/provider-settlements')->assertRedirect();
        $operator = User::create(['name' => 'Provider Operator', 'email' => 'gate-operator@example.test', 'password' => 'unused']);
        $tenantId = DB::table('network_tenants')->where('slug', 'provider')->value('id');
        TenantMembership::create(['tenant_id' => $tenantId, 'user_id' => $operator->id, 'role' => 'owner', 'status' => 'active']);
        $this->actingAs($operator)->withSession(['network.2fa_user_id' => $operator->id]);
        $this->get($host.'/network/provider-connections')->assertForbidden();
        $this->get($host.'/network/provider-settlements')->assertForbidden();
    }
}
