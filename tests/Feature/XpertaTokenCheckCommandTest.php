<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class XpertaTokenCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'services.xperta.enabled' => true,
            'services.xperta.environment' => 'production',
            'services.xperta.base_url' => 'https://prd-xperta.test',
            'services.xperta.empresa' => 'empresa-test',
            'services.xperta.corporativo' => 'corporativo-test',
            'services.xperta.email' => 'secret@example.test',
            'services.xperta.password' => 'secret-password',
            'services.xperta.api_key' => 'secret-api-key',
            'services.xperta.token_path' => '/api/v1/{corporativo}/login',
            'services.xperta.prd_token_check_enabled' => true,
            'services.xperta.connect_timeout' => 1,
            'services.xperta.timeout' => 2,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('zigo_provider_api_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('operation');
            $table->string('environment');
            $table->uuid('correlation_id');
            $table->string('status');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('b2c_cotizaciones', fn (Blueprint $table) => $table->id());
        Storage::fake('local');
        Cache::flush();
    }

    public function test_flag_off_blocks_active_check(): void
    {
        config(['services.xperta.prd_token_check_enabled' => false]);
        $this->artisan('zigo:xperta-token-check', $this->activeArgs())
            ->expectsOutputToContain('XPERTA_TOKEN_CHECK_DISABLED')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_wrong_confirmation_blocks_active_check(): void
    {
        $this->artisan('zigo:xperta-token-check', array_merge($this->activeArgs(), ['--confirm' => 'wrong']))
            ->expectsOutputToContain('XPERTA_TOKEN_CONFIRMATION_INVALID')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_default_is_dry_run_without_traffic(): void
    {
        $this->artisan('zigo:xperta-token-check')->expectsOutputToContain('DRY_RUN=true')->assertExitCode(0);
        Http::assertNothingSent();
        $this->assertCount(1, Storage::disk('local')->allFiles('private/xperta-token-checks'));
    }

    public function test_success_uses_one_login_call_same_headers_and_does_not_use_or_mutate_cache(): void
    {
        $cacheKey = $this->cacheKey();
        Cache::put($cacheKey, 'previous-cached-token', 60);
        Http::fake(['*' => Http::response(['success' => true, 'message' => ['token' => 'new-raw-token', 'expires_at' => now()->addHour()->toIso8601String()]], 200)]);
        $before = DB::table('b2c_cotizaciones')->count();

        $this->artisan('zigo:xperta-token-check', $this->activeArgs())
            ->expectsOutputToContain('success=true')
            ->expectsOutputToContain('x_api_key_sent=true')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://prd-xperta.test/api/v1/corporativo-test/login?')
                && $request->hasHeader('x-api-key', 'secret-api-key')
                && $request->hasHeader('Corporativo', 'corporativo-test')
                && $request->hasHeader('minutos', '1440')
                && $request->hasHeader('Accept', 'application/json')
                && $request->body() === ''
                && $request['email'] === 'secret@example.test'
                && $request['password'] === 'secret-password'
                && isset($request['minutos']);
        });
        $this->assertSame('previous-cached-token', Cache::get($cacheKey));
        $this->assertSame($before, DB::table('b2c_cotizaciones')->count());

        $report = $this->report();
        $this->assertTrue($report['token_received']);
        $this->assertSame(strlen('new-raw-token'), $report['token_length']);
        $this->assertSame(substr(hash('sha256', 'new-raw-token'), 0, 12), $report['token_fingerprint']);
        $this->assertTrue($report['corporativo_header_sent']);
        $this->assertTrue($report['api_key_header_sent']);
        $this->assertTrue($report['minutos_header_sent']);
        $this->assertTrue($report['accept_header_sent']);
        $this->assertSame('query', $report['login_parameters_location']);
        $this->assertNull($report['content_type']);
        $this->assertSame(1, DB::table('zigo_provider_api_events')->count());
        $this->assertSecretsAbsent();
    }

    public function test_missing_token_is_classified(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => []], 200)]);
        $this->artisan('zigo:xperta-token-check', $this->activeArgs())
            ->expectsOutputToContain('XPERTA_TOKEN_MISSING')->assertExitCode(1);
        $this->assertSame('XPERTA_TOKEN_MISSING', $this->report()['error_code']);
    }

    public function test_invalid_response_is_classified(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'unexpected'], 200)]);
        $this->artisan('zigo:xperta-token-check', $this->activeArgs())->assertExitCode(1);
        $this->assertSame('XPERTA_TOKEN_INVALID_RESPONSE', $this->report()['error_code']);
    }

    public function test_401_is_classified(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $this->artisan('zigo:xperta-token-check', $this->activeArgs())->assertExitCode(1);
        $this->assertSame('XPERTA_TOKEN_HTTP_401', $this->report()['error_code']);
    }

    public function test_timeout_is_classified_with_one_login_attempt(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts): void {
            $attempts++;

            throw new ConnectionException('Connection timed out');
        });

        $this->artisan('zigo:xperta-token-check', $this->activeArgs())->assertExitCode(1);

        $this->assertSame('XPERTA_TOKEN_NETWORK_ERROR', $this->report()['error_code']);
        $this->assertSame('network_error', $this->report()['provider_message_code']);
        $this->assertSame(1, $attempts);
        $this->assertSecretsAbsent();
    }

    /** @dataProvider forbiddenProvider */
    public function test_403_is_safely_classified(string $message, string $expected): void
    {
        Http::fake(['*' => Http::response(['message' => $message, 'token' => 'leaked-token'], 403)]);
        $this->artisan('zigo:xperta-token-check', $this->activeArgs())->assertExitCode(1);
        $this->assertSame($expected, $this->report()['error_code']);
        $this->assertSecretsAbsent(['leaked-token']);
    }

    public static function forbiddenProvider(): array
    {
        return [
            ['API Key no autorizada', 'XPERTA_TOKEN_API_KEY_UNAUTHORIZED'],
            ['Credenciales no autorizadas', 'XPERTA_TOKEN_CREDENTIALS_UNAUTHORIZED'],
            ['Corporativo no Autorizado', 'XPERTA_TOKEN_HTTP_403'],
        ];
    }

    private function activeArgs(): array
    {
        return ['--environment' => 'production', '--confirm' => 'XPERTA-TOKEN-PRD', '--execute' => true];
    }

    private function report(): array
    {
        $file = collect(Storage::disk('local')->allFiles('private/xperta-token-checks'))->last();
        return json_decode(Storage::disk('local')->get($file), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertSecretsAbsent(array $extra = []): void
    {
        $content = Storage::disk('local')->get(collect(Storage::disk('local')->allFiles('private/xperta-token-checks'))->last());
        $event = json_encode(DB::table('zigo_provider_api_events')->get(), JSON_THROW_ON_ERROR);
        foreach (array_merge(['new-raw-token', 'secret@example.test', 'secret-password', 'secret-api-key', 'Authorization', '"headers"', '"request"', '"response"'], $extra) as $secret) {
            $this->assertStringNotContainsString($secret, $content . $event);
        }
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash('sha256', implode('|', [
            config('services.xperta.base_url'), config('services.xperta.corporativo'),
            config('services.xperta.email'),
        ]));
    }
}
