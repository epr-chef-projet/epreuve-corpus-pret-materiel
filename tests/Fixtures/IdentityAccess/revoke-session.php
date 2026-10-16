<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('SELECT current_user, current_database() AS database');
if (! $app->environment('testing') || $identity === null || $identity->current_user !== 'epr_app' || $identity->database !== 'testing') {
    exit(3);
}
$hmac = trim(stream_get_contents(STDIN));
if (preg_match('/\A[0-9a-f]{64}\z/D', $hmac) !== 1) {
    exit(3);
}
$lookup = DB::connection('auth_lookup');
fwrite(STDOUT, $lookup->selectOne('SELECT pg_backend_pid() AS pid')->pid."\n");
fflush(STDOUT);
$lookup->select('SELECT epr.identity_access_revoke_session(?)', [$hmac]);
fwrite(STDOUT, "committed\n");
