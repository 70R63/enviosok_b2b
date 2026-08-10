<?php

namespace Tests\Feature;

use App\Domain\Network\Security\Models\NetworkTwoFactorAuthentication;
use App\Domain\Network\Security\Models\NetworkTwoFactorEvent;
use App\Domain\Network\Security\NetworkTwoFactorService;
use App\Models\Roles\Roles;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class NetworkTwoFactorAuthenticationTest extends TestCase
{
    private User $sysadmin;
    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->markTestSkipped('Requires SQLite :memory:.');
        }
        $this->schema();
        $this->host = 'https://'.config('zigo_surfaces.network.host');
        $this->sysadmin = $this->user('network@zigo.test', true);
    }

    public function test_password_login_requires_enrollment_and_does_not_authenticate_network_yet(): void
    {
        $this->post($this->host.'/network/login', ['email' => $this->sysadmin->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('network.two-factor.enroll'));
        $this->assertGuest();
        $this->assertSame($this->sysadmin->id, session('network.2fa_pending_user_id'));
        $this->get($this->host.'/network')->assertRedirect(route('network.login'));
    }

    public function test_unenrolled_user_enrolls_with_valid_totp_and_receives_recovery_codes_once(): void
    {
        $this->passwordStep();
        $configuration = app(NetworkTwoFactorService::class)->beginEnrollment($this->sysadmin);
        $code = app(Google2FA::class)->getCurrentOtp($configuration->secret);
        $oldSession = session()->getId();
        $response = $this->post($this->host.'/network/security/two-factor/enroll', ['code' => $code]);

        $response->assertOk()->assertSee('Guarda estos códigos ahora');
        $this->assertAuthenticatedAs($this->sysadmin);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame($this->sysadmin->id, session('network.2fa_user_id'));
        $this->assertNotNull($configuration->fresh()->enabled_at);
        $this->assertCount(8, $configuration->fresh()->recovery_code_hashes);
        $this->assertDatabaseHas('network_two_factor_events', ['user_id' => $this->sysadmin->id, 'event' => '2FA_ENROLLED']);
    }

    public function test_invalid_and_replayed_totp_are_rejected(): void
    {
        $this->passwordStep();
        $configuration = app(NetworkTwoFactorService::class)->beginEnrollment($this->sysadmin);
        $this->post($this->host.'/network/security/two-factor/enroll', ['code' => '000000'])->assertSessionHasErrors('code');
        $valid = app(Google2FA::class)->getCurrentOtp($configuration->secret);
        $this->post($this->host.'/network/security/two-factor/enroll', ['code' => $valid])->assertOk();
        $this->post($this->host.'/network/logout');
        $this->passwordStep();
        $this->post($this->host.'/network/security/two-factor/challenge', ['method' => 'totp', 'code' => $valid])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertGreaterThanOrEqual(1, NetworkTwoFactorEvent::query()->where('event', '2FA_CHALLENGE_FAILED')->count());
    }

    public function test_recovery_code_is_one_time(): void
    {
        $service = app(NetworkTwoFactorService::class);
        $configuration = $service->beginEnrollment($this->sysadmin);
        $request = Request::create('/network/security/two-factor/enroll', 'POST');
        $codes = $service->enroll($this->sysadmin, app(Google2FA::class)->getCurrentOtp($configuration->secret), $request);
        $this->assertIsArray($codes);
        $this->assertTrue($service->consumeRecoveryCode($this->sysadmin, $codes[0], $request));
        $this->assertFalse($service->consumeRecoveryCode($this->sysadmin, $codes[0], $request));
        $this->assertSame(1, NetworkTwoFactorEvent::query()->where('event', '2FA_RECOVERY_USED')->count());
    }

    public function test_only_another_sysadmin_can_reset_two_factor(): void
    {
        $service = app(NetworkTwoFactorService::class);
        $target = $this->user('target@zigo.test', true);
        $service->beginEnrollment($target);
        $request = Request::create('/network/security/users/'.$target->id.'/two-factor', 'DELETE');
        $service->reset($target, $this->sysadmin, $request);
        $this->assertDatabaseMissing('network_two_factor_authentications', ['user_id' => $target->id]);
        $this->assertDatabaseHas('network_two_factor_events', ['user_id' => $target->id, 'actor_user_id' => $this->sysadmin->id, 'event' => '2FA_RESET']);
    }

    public function test_self_reset_is_forbidden(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NetworkTwoFactorService::class)->reset($this->sysadmin, $this->sysadmin, Request::create('/', 'DELETE'));
    }

    public function test_logout_clears_second_factor_session(): void
    {
        $this->actingAs($this->sysadmin);
        session(['network.2fa_user_id' => $this->sysadmin->id, 'network.2fa_verified_at' => now()->timestamp]);
        $this->post($this->host.'/network/logout')->assertRedirect(route('network.login'));
        $this->assertGuest();
        $this->assertNull(session('network.2fa_user_id'));
        $this->assertNull(session('network.2fa_verified_at'));
    }

    public function test_challenge_and_reset_routes_are_rate_limited(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach (['network.two-factor.verify', 'network.two-factor.reset', 'network.two-factor.recovery.regenerate'] as $name) {
            $route = $routes->first(fn ($route) => $route->getName() === $name);
            $this->assertNotNull($route);
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'throttle:')));
        }
    }

    public function test_two_factor_migration_is_reversible(): void
    {
        (require database_path('migrations/2026_08_18_100000_create_network_two_factor_tables.php'))->down();

        $this->assertFalse(Schema::hasTable('network_two_factor_events'));
        $this->assertFalse(Schema::hasTable('network_two_factor_authentications'));
    }

    private function passwordStep(): void
    {
        $this->post($this->host.'/network/login', ['email' => $this->sysadmin->email, 'password' => 'secret-pass']);
    }

    private function user(string $email, bool $sysadmin): User
    {
        $user = User::query()->create(['name' => 'Network User', 'email' => $email, 'password' => Hash::make('secret-pass')]);
        if ($sysadmin) {
            $role = Roles::query()->firstOrCreate(['slug' => 'sysadmin'], ['name' => 'Sysadmin']);
            $user->roles()->attach($role->id);
        }
        return $user;
    }

    private function schema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->string('password');
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->timestamps();
        });
        Schema::create('users_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('roles_id'); $table->primary(['user_id', 'roles_id']);
        });
        (require database_path('migrations/2026_08_18_100000_create_network_two_factor_tables.php'))->up();
    }
}
