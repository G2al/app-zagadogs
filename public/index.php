<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$request = Request::capture();

$requestUri = $request->server->get('REQUEST_URI', '');
$requestPath = parse_url($requestUri, PHP_URL_PATH);

if ($requestPath === '/' || $requestPath === null || $requestPath === '') {
    $request->server->set('REQUEST_URI', '/admin');
    $request->server->set('PATH_INFO', '/admin');
    $request->server->set('ORIG_PATH_INFO', '/admin');
}

$app->handleRequest($request);
