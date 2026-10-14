<?php
// Explicit opt-in migration gate; refuses any non-temporary server datadir.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use Illuminate\Support\Facades\{DB, Http, Mail, Schema};
use Tests\Support\{ProviderApiFixture, ProviderContractFixture, ProviderFulfillmentFixture};
use App\Domain\Network\Providers\TenantProviderQuoteSource;
use App\Domain\Network\Tenancy\TenantContext;

$port = (int)($argv[1] ?? 0);
$directory = realpath($argv[2] ?? '');
$temp = realpath(sys_get_temp_dir());
if ($port < 30000 || !$directory || !$temp || !str_starts_with(strtolower($directory), strtolower($temp).DIRECTORY_SEPARATOR)
    || !str_contains(basename($directory), 'enfa03-sql-')) throw new RuntimeException('Disposable instance required');
$admin = new PDO('mysql:host=127.0.0.1;port='.$port, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if (realpath($admin->query('SELECT @@datadir')->fetchColumn()) !== $directory) throw new RuntimeException('Datadir mismatch');
$version = $admin->query('SELECT VERSION()')->fetchColumn();
if (!str_starts_with($version, '10.4.')) throw new RuntimeException('MariaDB 10.4 required');
$name = 'enfa_gate_'.bin2hex(random_bytes(5)); $password=bin2hex(random_bytes(16));
$admin->exec('CREATE DATABASE '.$name);
foreach(['localhost','127.0.0.1'] as $host){
    $account=$admin->quote($name).'@'.$admin->quote($host);
    $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
    $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES ON '.$name.'.* TO '.$account);
}
foreach(['APP_ENV=testing','DB_CONNECTION=sqlite','DB_DATABASE=:memory:','SESSION_DRIVER=array','CACHE_STORE=array','CACHE_DRIVER=array',
    'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','MAIL_MAILER=array'] as $entry) putenv($entry);
$app=require dirname(__DIR__,2).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.release_gate'=>['driver'=>'mysql','host'=>'127.0.0.1','port'=>$port,'database'=>$name,'username'=>$name,
    'password'=>$password,'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,'engine'=>'InnoDB']]);
DB::setDefaultConnection('release_gate'); Http::preventStrayRequests(); Mail::fake();
function check(bool $ok, string $message): void { if(!$ok) throw new RuntimeException($message); echo 'PASS '.$message.PHP_EOL; }
$grants=DB::select('SHOW GRANTS');
check(!str_contains(strtoupper(json_encode($grants)), 'TRIGGER') && !str_contains(strtoupper(json_encode($grants)), 'ALL PRIVILEGES'), 'migration user has no TRIGGER privilege');
$files=glob(database_path('migrations/2026_10_13_*.php')); sort($files);
check(count($files)===6, 'six ENFA migrations in chronological order');
foreach($files as $file){
    $code=php_strip_whitespace($file);
    check(!preg_match('/\b(CREATE\s+TRIGGER|INSERT\s+INTO|REPLACE\s+INTO)\b/i',$code)
        && !preg_match('/[\x22\x27](?:ENFA|ZIGO)[\x22\x27]/i',$code), basename($file).' has no trigger DDL or seeded tenant data');
}
// Fixtures create an empty equivalent parent schema and apply all six REAL ENFA migrations.
// No provisioning or production data is read. The application migration ledger is not needed.
ProviderApiFixture::schema();
$tables=array_map(fn($r)=>$r->TABLE_NAME,DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'network_provider_%' ORDER BY TABLE_NAME"));
check(count($tables)===11, 'fresh equivalent schema creates eleven provider tables');
foreach($tables as $table) check(DB::table($table)->count()===0, $table.' empty after migration');
foreach(array_reverse($files) as $file){(require $file)->down(); echo 'PASS empty rollback '.basename($file).PHP_EOL;}
check(!Schema::hasTable('network_provider_connections') && !Schema::hasTable('network_provider_payables'), 'all provider schema removed in empty rollback');
foreach($files as $file){(require $file)->up(); echo 'PASS ordered up '.basename($file).PHP_EOL;}
$inventory=[];
foreach($tables as $table){
    $fks=DB::select('SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME',[$table]);
    $indexes=DB::select('SHOW INDEX FROM `'.$table.'`');
    check(count($fks)>0 && count($indexes)>0, $table.' FK and indexes installed');
    $inventory[$table]=['foreign_keys'=>$fks,'indexes'=>array_values(array_unique(array_column($indexes,'Key_name')))];
}
check((int)DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->n===0, 'zero triggers');
$f=ProviderContractFixture::seed(); ProviderFulfillmentFixture::operational($f); app(TenantContext::class)->set($f['consumer']);
check(count(app(TenantProviderQuoteSource::class)->options($f['quote'],'anonymous'))===1,'covered quote visible');
$f['quote']->cp_destino='99999';
check(app(TenantProviderQuoteSource::class)->options($f['quote'],'anonymous')===[],'uncovered quote hidden');
DB::table('network_provider_connection_services')->where('connection_id',$f['connection']->id)->delete();
try{
 DB::table('network_provider_connection_services')->insert(['connection_id'=>$f['connection']->id,'provider_tenant_id'=>$f['consumer']->id,
     'capability'=>'LOCAL_SHIPPING','local_shipping_service_id'=>$f['service']->id]);
 throw new RuntimeException('Foreign ownership accepted');
}catch(Illuminate\Database\QueryException $e){check((int)($e->errorInfo[1] ?? 0)===1452, 'composite ownership FK rejects foreign service grant');}
check(app(TenantContext::class)->id()===$f['consumer']->id, 'consumer context unchanged');
Http::assertNothingSent();
echo json_encode(['version'=>$version,'schema'=>'fresh fixture-equivalent parents; real ENFA migrations','inventory'=>$inventory],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
echo 'MIGRATION_GATE=PASS'.PHP_EOL;
