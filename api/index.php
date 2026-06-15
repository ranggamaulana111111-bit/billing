<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

try {
    $app->make(Kernel::class)->call('migrate', ['--force' => true]);
} catch (Throwable $e) {
    // Log migration failure but don't crash the app
    // Vercel cold-start may retry with partial state
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
