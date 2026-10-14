<?php

// Opt-in SQL integration check. Must target a disposable instance whose datadir
// is inside the system temp directory and contains the enfa03-sql- marker.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\ProviderContractSelectionService;
use App\Domain\Network\Providers\ProviderFulfillmentService;
use App\Domain\Network\Providers\ProviderQuotePublisher;
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Network\Tenancy\TenantContext;
use App\Models\B2cCotizacion;
use App\Services\Shipping\GuideRecoveryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ProviderContractFixture;
use Tests\Support\ProviderFulfillmentFixture;
use Tests\Support\ProviderAcceptanceFixture;
use App\Domain\Network\Providers\ProviderFulfillmentDecisionService;
use App\Domain\Network\Providers\Models\ProviderFulfillmentDecision;
use App\Domain\Network\Tenancy\Models\TenantMembership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

$worker = ($argv[1] ?? '') === 'worker';
if ($worker) {
    $statePath = $argv[2];
    $state = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    $port = $state['port'];
    $directory = $state['directory'];
    $fulfillment = $state['fulfillment'] ?? false;
    $decisionMode = $state['decision_mode'] ?? '';
} else {
    $port = (int) ($argv[1] ?? 0);
    $directory = $argv[2] ?? '';
    $decisionMode = in_array($argv[3] ?? '', ['decision-accept', 'decision-conflict'], true) ? $argv[3] : '';
    $fulfillment = ($argv[3] ?? '') === 'fulfillment' || $decisionMode !== '';
}
$expected = realpath($directory);
$temp = realpath(sys_get_temp_dir());
if ($port < 30000 || ! $expected || ! $temp || ! str_starts_with(strtolower($expected), strtolower($temp).DIRECTORY_SEPARATOR)
    || ! str_contains(basename($expected), 'enfa03-sql-')) {
    throw new RuntimeException('Disposable instance required.');
}
$pdo = new PDO("mysql:host=127.0.0.1;port={$port}", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Datadir mismatch; refusing SQL access.');
}
if (! $worker) {
    $database = 'enfa03_validation_'.bin2hex(random_bytes(6));
    $pdo->exec('CREATE DATABASE '.$database);
    $username = $database;
    $password = bin2hex(random_bytes(16));
    foreach (['localhost', '127.0.0.1'] as $host) {
        $account = $pdo->quote($username).'@'.$pdo->quote($host);
        $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password));
        $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES ON '.$database.'.* TO '.$account);
    }
} else {
    $database = $state['database'];
    $username = $state['username'];
    $password = $state['password'];
}
foreach (['APP_ENV=testing', 'DB_CONNECTION=sqlite', 'DB_DATABASE=:memory:', 'SESSION_DRIVER=array',
    'CACHE_STORE=array', 'CACHE_DRIVER=array', 'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='] as $value) {
    putenv($value);
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
config(['database.connections.enfa03_validation' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port,
    'database' => $database, 'username' => $username, 'password' => $password, 'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB']]);
DB::setDefaultConnection('enfa03_validation');

if ($worker) {
    app(TenantContext::class)->set(Tenant::findOrFail($state['consumer_id']));
    session()->put('cotizacion_publica_id', $state['quote_id']);
    session()->put('tenant_provider_quote_nonce', $state['nonce']);
    if (($argv[3] ?? '') === 'hold') {
        $held = false;
        DB::listen(function ($query) use (&$held, $statePath, $decisionMode) {
            if (! $held && str_contains($query->sql, $decisionMode ? 'from `network_tenants`' : 'from `b2c_cotizaciones`') && str_contains($query->sql, 'for update')) {
                $held = true;
                file_put_contents($statePath.'.locked', 'locked');
                usleep(1000000);
            }
        });
    }
    $started = microtime(true);
    if ($decisionMode) {
        $status = $decisionMode === 'decision-conflict' && ($argv[3] ?? '') !== 'hold' ? 'REJECTED' : 'ACCEPTED';
        try {
            $decision = app(ProviderFulfillmentDecisionService::class)->decide(Tenant::findOrFail($state['provider_id']),
                $state['fulfillment_uuid'], User::findOrFail(($argv[3] ?? '') === 'hold' ? $state['actor_id'] : $state['second_actor_id']), $status);
            echo json_encode(['contract_uuid' => $decision->uuid, 'seconds' => microtime(true) - $started, 'status' => $decision->status], JSON_THROW_ON_ERROR);
        } catch (ValidationException) {
            echo json_encode(['contract_uuid' => 'CONFLICT', 'seconds' => microtime(true) - $started, 'status' => 'CONFLICT'], JSON_THROW_ON_ERROR);
        }
        exit;
    }
    $contract = $fulfillment
        ? app(ProviderFulfillmentService::class)->fulfill(B2cCotizacion::findOrFail($state['quote_id']))
        : app(ProviderContractSelectionService::class)->select(B2cCotizacion::findOrFail($state['quote_id']), $state['offer_uuid']);
    echo json_encode(['contract_uuid' => $contract->uuid, 'seconds' => microtime(true) - $started], JSON_THROW_ON_ERROR);
    exit;
}

if ($decisionMode) {
    ProviderAcceptanceFixture::schema();
} elseif ($fulfillment) {
    ProviderFulfillmentFixture::schema();
} else {
    ProviderContractFixture::schema();
}
$fixture = ProviderContractFixture::seed();
if ($fulfillment) {
    ProviderFulfillmentFixture::operational($fixture);
}
app(TenantContext::class)->set($fixture['consumer']);
session()->put('cotizacion_publica_id', $fixture['quote']->id);
$options = app(TenantProviderQuoteSource::class)->options($fixture['quote'], 'anonymous');
$offer = app(ProviderQuotePublisher::class)->publish($fixture['quote'], $options)[0];
if ($fulfillment) {
    app(ProviderContractSelectionService::class)->select($fixture['quote'], $offer['provider_snapshot_uuid']);
    B2cCotizacion::withoutEvents(fn () => $fixture['quote']->refresh()->forceFill(['estatus' => 'PAGADA',
        'payment_status' => 'approved', 'payment_verification_status' => 'VERIFIED', 'payment_verified_at' => now(),
        'payment_verified_amount' => 145, 'payment_verified_currency' => 'MXN',
        'payment_verified_external_reference' => 'B2C-'.$fixture['quote']->id])->save());
}
$decisionState = [];
if ($decisionMode) {
    $bridge = app(ProviderFulfillmentService::class)->fulfill($fixture['quote']->fresh());
    $actor = User::create(['name' => 'Native Operator', 'email' => 'operator@example.test', 'password' => 'unused']);
    TenantMembership::create(['tenant_id' => $fixture['provider']->id, 'user_id' => $actor->id, 'role' => 'operator', 'status' => 'active']);
    $secondActor = User::create(['name' => 'Second Native Operator', 'email' => 'second@example.test', 'password' => 'unused']);
    TenantMembership::create(['tenant_id' => $fixture['provider']->id, 'user_id' => $secondActor->id, 'role' => 'admin', 'status' => 'active']);
    $decisionState = ['provider_id' => $fixture['provider']->id, 'actor_id' => $actor->id, 'second_actor_id' => $secondActor->id, 'fulfillment_uuid' => $bridge->uuid];
}
$statePath = $expected.'/concurrency-'.bin2hex(random_bytes(4)).'.json';
file_put_contents($statePath, json_encode(array_merge($decisionState, ['port' => $port, 'directory' => $expected, 'database' => $database, 'decision_mode' => $decisionMode,
    'fulfillment' => $fulfillment, 'username' => $username, 'password' => $password, 'consumer_id' => $fixture['consumer']->id, 'quote_id' => $fixture['quote']->id,
    'offer_uuid' => $offer['provider_snapshot_uuid'], 'nonce' => session('tenant_provider_quote_nonce')]), JSON_THROW_ON_ERROR));
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$first = proc_open([PHP_BINARY, __FILE__, 'worker', $statePath, 'hold'], $descriptors, $firstPipes);
if (! is_resource($first)) {
    throw new RuntimeException('Cannot start first concurrent selector.');
}
$deadline = microtime(true) + 10;
while (! file_exists($statePath.'.locked') && microtime(true) < $deadline) {
    usleep(10000);
}
if (! file_exists($statePath.'.locked')) {
    proc_terminate($first);
    throw new RuntimeException('First selector did not acquire quote lock.');
}
$second = proc_open([PHP_BINARY, __FILE__, 'worker', $statePath], $descriptors, $secondPipes);
if (! is_resource($second)) {
    proc_terminate($first);
    throw new RuntimeException('Cannot start second selector.');
}
$results = [];
foreach ([[$first, $firstPipes], [$second, $secondPipes]] as [$process, $pipes]) {
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Concurrent selector failed: '.$error.$output);
    }
    $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}
if (($decisionMode === 'decision-conflict' ? ($results[0]['status'] !== 'ACCEPTED' || $results[1]['status'] !== 'CONFLICT') : $results[0]['contract_uuid'] !== $results[1]['contract_uuid']) || ProviderContract::count() !== 1
    || $results[1]['seconds'] < 0.2) {
    throw new RuntimeException('Concurrent idempotency/lock validation failed.');
}
if ($decisionMode) {
    $decision = ProviderFulfillmentDecision::firstOrFail();
    if (ProviderFulfillmentDecision::count() !== 1 || $decision->status !== 'ACCEPTED'
        || (int) $decision->actor_user_id !== (int) $actor->id || ! $decision->decided_at) {
        throw new RuntimeException('Decision concurrency/audit failed.');
    }
    try {
        DB::table('network_provider_fulfillments')->where('id', $bridge->id)->delete();
        throw new RuntimeException('Decision FK allowed deleting fulfillment.');
    } catch (\Illuminate\Database\QueryException) {}
    $decisionMigration = require database_path('migrations/2026_10_13_130000_create_network_provider_fulfillment_decisions.php');
    $decisionMigration->down();
    if (ProviderFulfillment::count() !== 1 || DB::table('local_shipments')->count() !== 1) throw new RuntimeException('Decision rollback damaged logistics.');
    $decisionMigration->up();
    $decisionMigration->down();
    echo 'Decision race '.$decisionMode.': one immutable audited decision; loser retries safely or receives conflict; FK and rollback OK. '.PHP_EOL;
}
if ($fixture['quote']->fresh()->precio !== '145.00' || DB::table('local_shipments')->count() !== ($fulfillment ? 1 : 0)
    || DB::table('local_tracking_events')->count() !== ($fulfillment ? 2 : 0) || DB::table('network_tenant_operations')->count() !== ($fulfillment ? 1 : 0)) {
    throw new RuntimeException('Frozen price or no-operations validation failed.');
}
if ($fulfillment) {
    $length = DB::selectOne("SELECT CHARACTER_MAXIMUM_LENGTH AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'b2c_cotizaciones' AND COLUMN_NAME = 'remitente_referencias'")->n;
    if ((int) $length !== 255) {
        throw new RuntimeException('Checkout reference length differs from validated contract.');
    }
    if (ProviderFulfillment::count() !== 1 || DB::table('network_usage_events')->count() !== 1
        || ProviderFulfillment::firstOrFail()->shipment->status !== 'READY_FOR_PICKUP'
        || (int) ProviderFulfillment::firstOrFail()->shipment->tenant_id !== (int) $fixture['provider']->id
        || (int) ProviderFulfillment::firstOrFail()->operation->tenant_id !== (int) $fixture['provider']->id) {
        throw new RuntimeException('Concurrent fulfillment duplicated pickup/usage or lost ownership.');
    }
    // Exercise the reused legacy token schema on real SQL, never a real transport.
    Mail::fake();
    $recovery = app(GuideRecoveryService::class);
    $plain = $recovery->issue($fixture['quote']->fresh(), 'native@example.test');
    $hash = hash('sha256', $plain);
    $expiry = DB::table('b2c_guide_recovery_tokens')->where('token_hash', $hash)->value('expires_at');
    if (! $recovery->resolve($plain) || ! $recovery->resolve($plain)
        || $expiry !== DB::table('b2c_guide_recovery_tokens')->where('token_hash', $hash)->value('expires_at')) {
        throw new RuntimeException('Legacy token expiry changed during guest recovery.');
    }
    $migration = require database_path('migrations/2026_10_13_120000_create_network_provider_fulfillments.php');
    $migration->down();
    $migration->up();
    $migration->down();
}
if (DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->n != 0) {
    throw new RuntimeException('Unexpected provider triggers.');
}
try {
    ProviderContract::firstOrFail()->deleteQuietly();
    throw new RuntimeException('Model allowed deletion.');
} catch (LogicException) {
}
$migration = require database_path('migrations/2026_10_13_110000_create_network_provider_contract_snapshots.php');
$migration->down();
$migration->up();
$migration->down();
echo 'SQL '.DB::selectOne('SELECT VERSION() AS version')->version.($fulfillment
    ? ': concurrent fulfillments returned one bridge, operation, shipment, pickup and usage event; '
    : ': concurrent selectors returned one contract, no operations; ')."row lock, frozen total, no TRIGGER grant and up/down/up/down OK.\n";
