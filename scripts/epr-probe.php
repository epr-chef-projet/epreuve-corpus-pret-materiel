<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

try {
    $root = dirname(__DIR__);
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $identity = DB::selectOne('SELECT session_user, current_user, current_database() AS database, current_schema() AS schema');
    if ($identity === null || $identity->session_user !== 'epr_app' || $identity->current_user !== 'epr_app' || $identity->database !== 'laravel' || $identity->schema !== 'epr') {
        throw new RuntimeException('Application SQL identity refused.');
    }
    Redis::connection()->ping();
    echo json_encode(['database' => $identity, 'redis' => 'available', 'environment' => $app->environment()], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['phase' => 'availability', 'errorClass' => $error::class, 'messageOmitted' => true], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(6);
}
