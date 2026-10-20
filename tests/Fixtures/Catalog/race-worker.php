<?php

declare(strict_types=1);

use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Shared\Errors\DomainError;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\IdentityAccess\Accounts;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('SELECT current_database() AS database,current_user AS role,session_user');
if (! $app->environment('testing') || $identity->database !== 'testing' || $identity->role !== 'epr_app' || $identity->session_user !== 'epr_app' || getenv('EPR_CATALOG_RACE') !== '1') {
    throw new RuntimeException('Catalogue race fixture target refused.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$directory = $input['directory'];
$root = storage_path('catalog-races').DIRECTORY_SEPARATOR;
if (! is_string($directory) || ! str_starts_with($directory, $root) || str_contains($directory, '..') || ! is_dir($directory)) {
    throw new RuntimeException('Invalid race barrier directory.');
}
Queue::fake([PublishCatalogInvalidation::class]);
$pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
file_put_contents($directory.'/'.$input['worker'].'.pid', (string) $pid);
DB::listen(static function (QueryExecuted $query) use ($input, $directory): void {
    $match = ($input['pause'] === 'intention' && str_contains($query->sql, 'pg_advisory_xact_lock')) || ($input['pause'] === 'equipment' && str_contains(strtolower($query->sql), 'for update'));
    if (! $match) {
        return;
    }
    file_put_contents($directory.'/held', 'held');
    $deadline = microtime(true) + 10;
    while (! is_file($directory.'/release')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Race release timed out.');
        }
        usleep(10_000);
    }
});
$user = Accounts::user('atelier-1-manager');
$actor = new ActorContext($user['id'], $user['workshopId'], $user['role']);
try {
    $text = new EquipmentInput($input['name'], 'A03 race fixture', null);
    $receipt = $input['action'] === 'create'
        ? app(CreateEquipmentAction::class)->handle($actor, $text, 1, $input['operationKey'])
        : app(EditEquipmentTextAction::class)->handle($actor, $input['equipmentId'], $text, 1, $input['operationKey']);
    echo json_encode(['receipt' => $receipt], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['errorCode' => $error instanceof DomainError ? $error->errorCode() : null, 'errorClass' => $error::class], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(2);
}
