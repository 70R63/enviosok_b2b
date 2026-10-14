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
use Tests\Support\ProviderTrackingFixture;
use App\Domain\Network\Providers\ProviderDriverExecutionService;
use App\Domain\Shipping\LastMile\DriverAssignmentService;
use App\Domain\Shipping\LastMile\DriverDispatchService;
use App\Domain\Shipping\LastMile\Models\DriverAssignment;
use App\Domain\Shipping\LastMile\Models\DriverProfile;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProviderApiFixture;
use Tests\Support\FakeProviderCrmAdapter;
use App\Domain\ApiHub\Models\{TenantApiClient,TenantApiKey};
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Providers\{ProviderApiPrincipal,ProviderApiOperations,ProviderCrmOutbox};

$worker = ($argv[1] ?? '') === 'worker';
if ($worker) {
    $statePath = $argv[2];
    $state = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    $port = $state['port'];
    $directory = $state['directory'];
    $fulfillment = $state['fulfillment'] ?? false;
    $decisionMode = $state['decision_mode'] ?? '';
    $executionMode = $state['execution_mode'] ?? '';
    $apiMode=$state['api_mode']??'';
    $financialMode=$state['financial_mode']??'';
} else {
    $financialMode=in_array($argv[3]??'',['payable-create','settlement-repeat','settlement-partials','settlement-overpay'],true)?$argv[3]:'';
    $apiMode=in_array($argv[3]??'',['api-accept','api-tracking','api-pod','api-outbox'],true)?$argv[3]:'';
    $executionMode = in_array($argv[3] ?? '', ['dispatch', 'auto-dispatch', 'tracking', 'driver-conflict', 'pod', 'failed'], true) ? $argv[3] : '';
    $port = (int) ($argv[1] ?? 0);
    $directory = $argv[2] ?? '';
    $decisionMode = in_array($argv[3] ?? '', ['decision-accept', 'decision-conflict'], true) ? $argv[3] : '';
    $fulfillment = ($argv[3] ?? '') === 'fulfillment' || $decisionMode !== '' || $executionMode !== '' || $apiMode!=='';
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
if ($executionMode||$apiMode) config(['filesystems.disks.local.root' => $expected.'/evidence-'.$database]);

if (!$worker && $financialMode) {
    config(['filesystems.disks.local.root'=>$expected.'/evidence-'.$database]); Mail::fake();
    ProviderApiFixture::schema();
    $financialMigration=require database_path('migrations/2026_10_13_150000_create_provider_payable_ledger.php');$financialMigration->down();$financialMigration->up();
    $fixture=ProviderContractFixture::seed();ProviderFulfillmentFixture::operational($fixture);ProviderTrackingFixture::grantDriver($fixture);
    app(TenantContext::class)->set($fixture['consumer']);session()->put('cotizacion_publica_id',$fixture['quote']->id);
    $actor=User::create(['name'=>'Finance one','email'=>'finance-one@example.test','password'=>'unused']);
    $secondActor=User::create(['name'=>'Finance two','email'=>'finance-two@example.test','password'=>'unused']);
    $role=\App\Models\Roles\Roles::create(['slug'=>'sysadmin']);$actor->roles()->attach($role->id);$secondActor->roles()->attach($role->id);
    TenantMembership::create(['tenant_id'=>$fixture['provider']->id,'user_id'=>$actor->id,'role'=>'operator','status'=>'active']);
    $offer=app(ProviderQuotePublisher::class)->publish($fixture['quote'],app(TenantProviderQuoteSource::class)->options($fixture['quote'],'anonymous'))[0];
    app(ProviderContractSelectionService::class)->select($fixture['quote'],$offer['provider_snapshot_uuid']);
    B2cCotizacion::withoutEvents(fn()=>$fixture['quote']->refresh()->forceFill(['estatus'=>'PAGADA','payment_status'=>'approved','payment_verification_status'=>'VERIFIED',
        'payment_verified_at'=>now(),'payment_verified_amount'=>145,'payment_verified_currency'=>'MXN','payment_verified_external_reference'=>'B2C-'.$fixture['quote']->id])->save());
    $bridge=app(ProviderFulfillmentService::class)->fulfill($fixture['quote']);
    if($financialMode==='payable-create'){DB::table('network_provider_payable_events')->delete();DB::table('network_provider_payables')->delete();}
    else {
        app(ProviderFulfillmentDecisionService::class)->decide($fixture['provider'],$bridge->uuid,$actor,'ACCEPTED');
        $driver=ProviderTrackingFixture::driver($fixture['provider']);$assignment=app(DriverAssignmentService::class)->assign($fixture['provider'],$bridge->shipment,$driver,$actor->id);
        foreach(['PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY']as$status)app(ProviderDriverExecutionService::class)->transition($assignment,$driver->user_id,$status);
        app(ProviderDriverExecutionService::class)->deliver($assignment,$driver->user_id,ProviderTrackingFixture::proofData(),UploadedFile::fake()->image('pod.jpg'),ProviderTrackingFixture::signature());
    }
    $statePath=$expected.'/finance-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($statePath,json_encode(['financial_mode'=>$financialMode,'port'=>$port,'directory'=>$expected,'database'=>$database,'username'=>$username,'password'=>$password,
        'consumer_id'=>$fixture['consumer']->id,'quote_id'=>$fixture['quote']->id,'nonce'=>session('tenant_provider_quote_nonce'),
        'fulfillment_uuid'=>$bridge->uuid,'payable_uuid'=>\App\Domain\Network\Providers\Models\ProviderPayable::value('uuid'),
        'actor_id'=>$actor->id,'second_actor_id'=>$secondActor->id,'paid_at'=>now()->subMinute()->format('Y-m-d H:i:s')],JSON_THROW_ON_ERROR));
    $descriptors=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$first=proc_open([PHP_BINARY,__FILE__,'worker',$statePath,'hold'],$descriptors,$firstPipes);
    $deadline=microtime(true)+10;while(!file_exists($statePath.'.locked')&&microtime(true)<$deadline)usleep(10000);
    if(!file_exists($statePath.'.locked')){proc_terminate($first);throw new RuntimeException('Financial lock not acquired.');}
    $second=proc_open([PHP_BINARY,__FILE__,'worker',$statePath],$descriptors,$secondPipes);$results=[];
    foreach([[$first,$firstPipes],[$second,$secondPipes]]as[$process,$pipes]){fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Financial worker failed: '.$error.$output);$results[]=json_decode($output,true,flags:JSON_THROW_ON_ERROR);}
    $payable=\App\Domain\Network\Providers\Models\ProviderPayable::firstOrFail();
    $count=DB::table('network_provider_settlement_entries')->count();
    if($results[0]['status']!=='OK'||$results[1]['seconds']<0.2||\App\Domain\Network\Providers\Models\ProviderPayable::count()!==1)throw new RuntimeException('Financial serialization failed.');
    if($financialMode==='payable-create'&&($count!==0||$results[0]['uuid']!==$results[1]['uuid']))throw new RuntimeException('Duplicate payable.');
    if($financialMode==='settlement-repeat'&&($count!==1||$results[0]['uuid']!==$results[1]['uuid']||$payable->remainingAmount()!=='50.00'))throw new RuntimeException('Repeat payment duplicated.');
    if($financialMode==='settlement-partials'&&($count!==2||$payable->remainingAmount()!=='0.00'||$payable->financialStatus()!=='SETTLED'))throw new RuntimeException('Partial payment totals failed.');
    if($financialMode==='settlement-overpay'&&($count!==1||$results[1]['status']!=='CONFLICT'||$payable->remainingAmount()!=='30.00'))throw new RuntimeException('Overpayment accepted.');
    try{$financialMigration->down();throw new LogicException('Financial history erased.');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'Export/migrate'))throw $e;}
    if(\Illuminate\Support\Facades\Artisan::call('network:provider-payables:reconcile')!==0)throw new RuntimeException('Native financial reconciliation failed.');
    if($fixture['quote']->fresh()->precio!=='145.00'||$payable->provider_net_amount!=='100.00'||$payable->provider_tax_amount!==null)throw new RuntimeException('Economic history changed.');
    echo 'SQL '.DB::selectOne('SELECT VERSION() AS version')->version.': '.$financialMode.' concurrent locks, immutable source, no overpayment, reconciliation and up/down/up with protected populated rollback OK.'.PHP_EOL;
    exit;
}

if (! $worker && ($argv[3] ?? '') === 'network-readiness') {
    ProviderApiFixture::schema();
    $fixture = ProviderContractFixture::seed();
    ProviderFulfillmentFixture::operational($fixture);
    ProviderTrackingFixture::grantDriver($fixture);
    $actor = User::create(['name'=>'Network validation','email'=>'network-validation@example.test','password'=>'unused']);
    $role = \App\Models\Roles\Roles::create(['slug'=>'sysadmin']); $actor->roles()->attach($role->id);
    TenantMembership::create(['tenant_id'=>$fixture['provider']->id,'user_id'=>$actor->id,'role'=>'operator','status'=>'active']);
    app(TenantContext::class)->set($fixture['consumer']);
    $readiness = app(\App\Domain\Network\Providers\ProviderNetworkReadinessService::class);
    $service = app(\App\Domain\Network\Providers\ProviderConnectionService::class);
    $input = ['consumer_uuid'=>$fixture['consumer']->uuid,'provider_uuid'=>$fixture['provider']->uuid,'capability'=>'LOCAL_SHIPPING',
        'status'=>'active','services'=>[$fixture['service']->id]];
    $fixture['connection']->forceFill(['status'=>'inactive'])->save();
    if (!$readiness->evaluate($fixture['consumer'],$fixture['provider'],$fixture['connection'],true)['ready']) throw new RuntimeException('Valid activation candidate failed readiness.');
    $service->saveConfiguration($actor,$fixture['connection'],$input);
    $service->saveConfiguration($actor,$fixture['connection'],$input);
    if (\App\Domain\Network\Providers\Models\ProviderConnection::count()!==1 || DB::table('network_provider_connection_services')->count()!==1) throw new RuntimeException('Repeated admin activation duplicated configuration.');
    $fixture['connection'] = $fixture['connection']->fresh();
    $fixture['connection']->forceFill(['status'=>'inactive'])->save();
    $fixture['tariff']->update(['tenant_id'=>$fixture['consumer']->id]);
    try { $service->saveConfiguration($actor,$fixture['connection'],$input); throw new RuntimeException('Foreign tariff allowed activation.'); }
    catch (ValidationException) {}
    if ($fixture['connection']->fresh()->status!=='inactive') throw new RuntimeException('Failed activation changed state.');
    $fixture['tariff']->update(['tenant_id'=>$fixture['provider']->id]);
    $service->saveConfiguration($actor,$fixture['connection'],$input);
    if (\Illuminate\Support\Facades\Artisan::call('network:provider-network:check',['--consumer'=>'slug:consumer','--provider'=>'uuid:'.$fixture['provider']->uuid])!==0
        || DB::table('network_tenant_operations')->count()!==0 || DB::table('local_shipments')->count()!==0
        || DB::table('tenant_api_webhook_deliveries')->count()!==0 || app(TenantContext::class)->id()!==$fixture['consumer']->id) throw new RuntimeException('Preflight mutated operations/context or failed.');
    echo 'SQL '.DB::selectOne('SELECT VERSION() AS version')->version.': provider readiness, repeated activation, foreign tariff exclusion, rollback of failed activation and read-only internal mode OK.'.PHP_EOL;
    exit;
}

if ($worker) {
    app(TenantContext::class)->set(Tenant::findOrFail($state['consumer_id']));
    session()->put('cotizacion_publica_id', $state['quote_id']);
    session()->put('tenant_provider_quote_nonce', $state['nonce']);
    if (($argv[3] ?? '') === 'hold') {
        $held = false;
        DB::listen(function ($query) use (&$held, $statePath, $decisionMode, $executionMode,$apiMode,$financialMode) {
            $target=$financialMode?($financialMode==='payable-create'?'from `network_provider_fulfillments`':'from `network_tenants`'):($apiMode?($apiMode==='api-outbox'?'from `tenant_api_webhook_deliveries`':'from `network_provider_integrations`'):($executionMode?'from `local_shipments`':($decisionMode?'from `network_tenants`':'from `b2c_cotizaciones`')));
            if (! $held && str_contains($query->sql,$target) && str_contains($query->sql, 'for update')) {
                $held = true;
                file_put_contents($statePath.'.locked', 'locked');
                usleep(1000000);
            }
        });
    }
    $started = microtime(true);
    if($financialMode){
        $hold=($argv[3]??'')==='hold';
        try{
            if($financialMode==='payable-create')$result=app(\App\Domain\Network\Providers\ProviderPayableService::class)->sync(ProviderFulfillment::where('uuid',$state['fulfillment_uuid'])->firstOrFail());
            else {
                $same=$financialMode==='settlement-repeat';$amount=match($financialMode){'settlement-repeat'=>'50.00','settlement-partials'=>$hold?'40.00':'60.00',default=>'70.00'};
                $suffix=$same?'one':($hold?'one':'two');
                $result=app(\App\Domain\Network\Providers\ProviderSettlementService::class)->settle(User::findOrFail($hold?$state['actor_id']:$state['second_actor_id']),$state['payable_uuid'],
                    ['amount'=>$amount,'currency'=>'MXN','payment_reference'=>'PAY-'.$suffix,'idempotency_key'=>'financial-key-'.$suffix,'paid_at'=>$state['paid_at']]);
            }
            $resultData=['status'=>'OK','uuid'=>$result->uuid];
        }catch(ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException){$resultData=['status'=>'CONFLICT'];}
        echo json_encode($resultData+['seconds'=>microtime(true)-$started],JSON_THROW_ON_ERROR);exit;
    }
    if($apiMode){
        if($apiMode==='api-outbox'){
            config(['provider_crm.adapters.fake'=>FakeProviderCrmAdapter::class]);app(ProviderCrmOutbox::class)->deliver($state['outbox_id']);
            $body=['event_uuid'=>$state['fulfillment_uuid']];
        }else{
            $p=new ProviderApiPrincipal($state['integration_id'],$state['key_id']);$action=match($apiMode){'api-accept'=>'accept','api-tracking'=>'tracking','api-pod'=>'pod'};
            $data=match($apiMode){'api-tracking'=>['status'=>'PICKED_UP'],'api-pod'=>ProviderTrackingFixture::proofData(),default=>[]};
            $response=app(ProviderApiOperations::class)->command($p,$state['fulfillment_uuid'],$action,'concurrent-api-key-123',$data,
                $apiMode==='api-pod'?UploadedFile::fake()->image('proof.jpg'):null,$apiMode==='api-pod'?ProviderTrackingFixture::signature():null);
            $body=$response['body'];
        }
        echo json_encode(['contract_uuid'=>hash('sha256',json_encode($body)),'seconds'=>microtime(true)-$started],JSON_THROW_ON_ERROR);exit;
    }
    if ($executionMode) {
        $provider = Tenant::findOrFail($state['provider_id']);
        app(TenantContext::class)->set($provider);
        $originalContext = app(TenantContext::class)->current();
        $bridge = ProviderFulfillment::where('uuid', $state['fulfillment_uuid'])->firstOrFail();
        $driver = DriverProfile::findOrFail($state['driver_id']);
        $actorId = ($argv[3] ?? '') === 'hold' ? $state['actor_id'] : $state['second_actor_id'];
        try {
            if ($executionMode === 'dispatch') $result = app(DriverAssignmentService::class)->assign($provider, $bridge->shipment, $driver, $actorId);
            elseif ($executionMode === 'auto-dispatch') $result = app(DriverDispatchService::class)->autoAssign($provider, $bridge->shipment, $actorId);
            else {
                $assignment = DriverAssignment::findOrFail($state['assignment_id']);
                $driverUserId = $executionMode === 'driver-conflict' && ($argv[3] ?? '') !== 'hold' ? $state['other_driver_user_id'] : $driver->user_id;
                $service = app(ProviderDriverExecutionService::class);
                $result = match ($executionMode) {
                    'tracking', 'driver-conflict' => $service->transition($assignment, $driverUserId, 'PICKED_UP'),
                    'pod' => $service->deliver($assignment, $driverUserId, ProviderTrackingFixture::proofData(), UploadedFile::fake()->image('delivery.jpg', 240, 160), ProviderTrackingFixture::signature()),
                    'failed' => $service->fail($assignment, $driverUserId, ['reason' => 'NO_ANSWER']),
                };
            }
            echo json_encode(['contract_uuid' => (string) ($result?->uuid ?? $result?->id ?? 'NONE'), 'seconds' => microtime(true) - $started,
                'status' => 'OK', 'context_unchanged' => $originalContext === app(TenantContext::class)->current()], JSON_THROW_ON_ERROR);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException|ValidationException) {
            echo json_encode(['contract_uuid' => 'CONFLICT', 'seconds' => microtime(true) - $started, 'status' => 'CONFLICT',
                'context_unchanged' => $originalContext === app(TenantContext::class)->current()], JSON_THROW_ON_ERROR);
        }
        exit;
    }
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

if($apiMode){
    ProviderApiFixture::schema();$migration=require database_path('migrations/2026_10_13_140000_add_provider_api_boundary.php');$migration->down();$migration->up();
} elseif ($executionMode) {
    ProviderTrackingFixture::schema();
} elseif ($decisionMode) {
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
if ($decisionMode || $executionMode || $apiMode) {
    $bridge = app(ProviderFulfillmentService::class)->fulfill($fixture['quote']->fresh());
    $actor = User::create(['name' => 'Native Operator', 'email' => 'operator@example.test', 'password' => 'unused']);
    TenantMembership::create(['tenant_id' => $fixture['provider']->id, 'user_id' => $actor->id, 'role' => 'operator', 'status' => 'active']);
    $secondActor = User::create(['name' => 'Second Native Operator', 'email' => 'second@example.test', 'password' => 'unused']);
    TenantMembership::create(['tenant_id' => $fixture['provider']->id, 'user_id' => $secondActor->id, 'role' => 'admin', 'status' => 'active']);
    $decisionState = ['provider_id' => $fixture['provider']->id, 'actor_id' => $actor->id, 'second_actor_id' => $secondActor->id, 'fulfillment_uuid' => $bridge->uuid];
}
if($apiMode){
    ProviderTrackingFixture::grantDriver($fixture);
    $client=TenantApiClient::create(['tenant_id'=>$fixture['provider']->id,'name'=>'Disposable CRM','environment'=>'SANDBOX','status'=>'ACTIVE','created_by_user_id'=>$actor->id]);
    $key=TenantApiKey::create(['tenant_id'=>$client->tenant_id,'api_client_id'=>$client->id,'name'=>'Disposable','key_prefix'=>'test','last_four'=>'test','key_hash'=>hash('sha256','disposable-provider-key'),
        'scopes'=>['provider:fulfillment:read','provider:fulfillment:decide','provider:dispatch:write','provider:tracking:write','provider:pod:write']]);
    $integration=ProviderIntegration::forceCreate(['provider_tenant_id'=>$client->tenant_id,'api_client_id'=>$client->id,'provider_connection_id'=>$fixture['connection']->id,'status'=>'active','adapter'=>$apiMode==='api-outbox'?'fake':'disabled']);
    app(ProviderCrmOutbox::class)->publish($bridge);$p=new ProviderApiPrincipal($integration->id,$key->id);
    $decisionState+=['integration_id'=>$integration->id,'key_id'=>$key->id,'outbox_id'=>DB::table('tenant_api_webhook_deliveries')->value('id')];
    if(in_array($apiMode,['api-tracking','api-pod'],true)){
        $operations=app(ProviderApiOperations::class);$operations->command($p,$bridge->uuid,'accept','parent-accept-key',[]);
        $driver=ProviderTrackingFixture::driver($fixture['provider']);$operations->command($p,$bridge->uuid,'dispatch','parent-dispatch-key',['driver_uuid'=>$driver->uuid]);
        if($apiMode==='api-pod')foreach(['PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY'] as $status)$operations->command($p,$bridge->uuid,'tracking','parent-'.$status,['status'=>$status]);
    }
}
if ($executionMode) {
    ProviderTrackingFixture::grantDriver($fixture);
    app(ProviderFulfillmentDecisionService::class)->decide($fixture['provider'], $bridge->uuid, $actor, 'ACCEPTED');
    $driver = ProviderTrackingFixture::driver($fixture['provider']);
    $otherDriver = ProviderTrackingFixture::driver($fixture['provider'], 'two');
    // Auto race has one online candidate; otherwise a second driver could validly win.
    $otherDriver->update(['availability_status' => 'UNAVAILABLE']);
    $decisionState += ['driver_id' => $driver->id, 'other_driver_user_id' => $otherDriver->user_id];
    if (! in_array($executionMode, ['dispatch', 'auto-dispatch'], true)) {
        $assignment = app(DriverAssignmentService::class)->assign($fixture['provider'], $bridge->shipment, $driver, $actor->id);
        $decisionState['assignment_id'] = $assignment->id;
        if (in_array($executionMode, ['pod', 'failed'], true)) foreach (['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'] as $status) {
            app(ProviderDriverExecutionService::class)->transition($assignment, $driver->user_id, $status);
        }
    }
    $frozenContract = ProviderContract::firstOrFail();
    $economicFingerprint = hash('sha256', json_encode([$frozenContract->getAttributes(), $frozenContract->snapshot->getAttributes()], JSON_THROW_ON_ERROR));
}
$statePath = $expected.'/concurrency-'.bin2hex(random_bytes(4)).'.json';
file_put_contents($statePath, json_encode(array_merge($decisionState, ['port' => $port, 'directory' => $expected, 'database' => $database, 'decision_mode' => $decisionMode, 'execution_mode' => $executionMode,'api_mode'=>$apiMode,
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
if (($executionMode === 'driver-conflict' ? ($results[0]['status'] !== 'OK' || $results[1]['status'] !== 'CONFLICT')
    : ($decisionMode === 'decision-conflict' ? ($results[0]['status'] !== 'ACCEPTED' || $results[1]['status'] !== 'CONFLICT') : $results[0]['contract_uuid'] !== $results[1]['contract_uuid'])) || ProviderContract::count() !== 1
    || $results[1]['seconds'] < 0.2) {
    throw new RuntimeException('Concurrent idempotency/lock validation failed.');
}
if ($executionMode) {
    $expectedStatus = match ($executionMode) { 'dispatch', 'auto-dispatch' => 'READY_FOR_PICKUP', 'tracking', 'driver-conflict' => 'PICKED_UP', 'pod' => 'DELIVERED', 'failed' => 'DELIVERY_FAILED' };
    $expectedEvents = match ($executionMode) { 'dispatch', 'auto-dispatch' => 2, 'tracking', 'driver-conflict' => 3, default => 6 };
    if ($results[0]['status'] !== 'OK' || ($executionMode !== 'driver-conflict' && $results[1]['status'] !== 'OK')
        || ! $results[0]['context_unchanged'] || ! $results[1]['context_unchanged']
        || DB::table('local_driver_assignments')->count() !== 1 || DB::table('local_tracking_events')->count() !== $expectedEvents
        || DB::table('local_delivery_proofs')->count() !== ($executionMode === 'pod' ? 1 : 0)
        || DB::table('local_delivery_failed_attempts')->count() !== ($executionMode === 'failed' ? 1 : 0)
        || ProviderFulfillment::count() !== 1 || DB::table('local_shipments')->count() !== 1 || DB::table('network_tenant_operations')->count() !== 1
        || DB::table('network_usage_events')->count() !== 1 || $bridge->shipment->fresh()->status !== $expectedStatus
        || $fixture['quote']->fresh()->precio !== '145.00'
        || $economicFingerprint !== hash('sha256', json_encode([$frozenContract->fresh()->getAttributes(), $frozenContract->snapshot->fresh()->getAttributes()], JSON_THROW_ON_ERROR))) {
        throw new RuntimeException('Concurrent last-mile execution changed economy, context or duplicated execution.');
    }
    if (DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->n != 0) throw new RuntimeException('Unexpected triggers.');
    foreach (['2026_10_13_130000_create_network_provider_fulfillment_decisions.php', '2026_10_13_120000_create_network_provider_fulfillments.php', '2026_10_13_110000_create_network_provider_contract_snapshots.php'] as $migrationFile) {
        $migration = require database_path('migrations/'.$migrationFile);
        $migration->down(); $migration->up(); $migration->down();
    }
    echo 'SQL '.DB::selectOne('SELECT VERSION() AS version')->version.': '.$executionMode.' race OK; one assignment, expected unique tracking/POD/attempts; frozen economy, tenant context, no TRIGGER and existing provider rollback OK.'.PHP_EOL;
    exit;
}
if($apiMode){
    if(ProviderFulfillment::count()!==1||DB::table('local_shipments')->count()!==1||DB::table('network_tenant_operations')->count()!==1
        ||$fixture['quote']->fresh()->precio!=='145.00'||DB::table('tenant_api_webhook_deliveries')->count()!==1)throw new RuntimeException('API/outbox changed business ownership/economy.');
    $expectedEvents=match($apiMode){'api-tracking'=>3,'api-pod'=>6,default=>2};
    if(DB::table('local_tracking_events')->count()!==$expectedEvents||DB::table('local_delivery_proofs')->count()!==($apiMode==='api-pod'?1:0))throw new RuntimeException('API duplicate tracking/POD.');
    if($apiMode==='api-outbox'){ $out=DB::table('tenant_api_webhook_deliveries')->first();if($out->status!=='PR_DELIVERED'||$out->attempt!==1)throw new RuntimeException('Outbox lease failed.'); }
    else { $d=DB::table('network_provider_fulfillment_decisions')->first();if(!$d||$d->actor_user_id!==null||$d->channel!=='CRM_API'||(int)$d->actor_api_client_id!==(int)$client->id)throw new RuntimeException('API actor impersonated human.');}
    if($apiMode!=='api-outbox'&&DB::table('tenant_api_idempotency_records')->where('endpoint_code','like','provider.%')->where('expires_at','<',now()->addHours(12))->exists())throw new RuntimeException('Legacy TIMESTAMP auto-update shortened idempotency retention.');
    $migration=require database_path('migrations/2026_10_13_140000_add_provider_api_boundary.php');
    try{$migration->down();throw new LogicException('Rollback erased API history.');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'Export/migrate'))throw $e;}
    echo 'SQL '.DB::selectOne('SELECT VERSION() AS version')->version.': '.$apiMode.' concurrent idempotency/lease, nonhuman audit, migration up/down/up and protected populated rollback OK.'.PHP_EOL;exit;
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
