<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host) ?? '';

$productionRoot = '/home/customer/www/zigo-envios.com';
$stageRoot = '/home/customer/www/stage.zigo-envios.com';
$laravelRoot = preg_match('/^[a-z0-9-]+-stage\.zigo-envios\.com$/', $host) === 1
    ? $stageRoot
    : $productionRoot;

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture())->send();
$kernel->terminate($request, $response);
