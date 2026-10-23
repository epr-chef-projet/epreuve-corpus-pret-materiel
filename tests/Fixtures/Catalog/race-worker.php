<?php

declare(strict_types=1);

use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\PublicApi\LockEquipmentBatch;
use App\Modules\Catalog\PublicApi\ReplaceTotalWithinUnit;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
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
$hold = static function () use ($directory): void {
    file_put_contents($directory.'/held', 'held');
    $deadline = microtime(true) + 10;
    while (! is_file($directory.'/release')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Race release timed out.');
        }
        usleep(10_000);
    }
};
DB::listen(static function (QueryExecuted $query) use ($input, $hold): void {
    $match = ($input['pause'] === 'intention' && str_contains($query->sql, 'pg_advisory_xact_lock')) || ($input['pause'] === 'equipment' && str_contains(strtolower($query->sql), 'for update'));
    if ($match) {
        $hold();
    }
});
$user = Accounts::user('atelier-1-manager');
$actor = new ActorContext($user['id'], $user['workshopId'], $user['role']);
try {
    if (in_array($input['action'], ['lock', 'lockRelease', 'lockRollback'], true)) {
        $states = [];
        app(SqlUnit::class)->forActor($actor, function () use ($actor, $input, $hold, &$states): void {
            $savepoint = $input['action'] !== 'lock';
            if ($savepoint) {
                DB::connection('pgsql')->beginTransaction();
            }
            $states = app(LockEquipmentBatch::class)->handle($actor, $input['equipmentIds'] ?? [$input['equipmentId']]);
            if ($savepoint) {
                if ($input['action'] === 'lockRollback') {
                    DB::connection('pgsql')->rollBack();
                    try {
                        app(ReplaceTotalWithinUnit::class)->handle($actor, $input['equipmentId'], 1, 1, 0);
                        throw new RuntimeException('Rolled-back protection remained usable.');
                    } catch (LogicException) {
                        // The public participant must reject the rolled-back protection.
                    }
                } else {
                    DB::connection('pgsql')->commit();
                }
                $hold();
            }
        });
        echo json_encode(['states' => $states], JSON_THROW_ON_ERROR).PHP_EOL;
    } else {
        $receipt = match ($input['action']) {
            'create' => app(CreateEquipmentAction::class)->handle($actor, new EquipmentInput($input['name'], 'A03 race fixture', null), 1, $input['operationKey']),
            'edit' => app(EditEquipmentTextAction::class)->handle($actor, $input['equipmentId'], new EquipmentInput($input['name'], 'A03 race fixture', null), 1, $input['operationKey']),
            'archive' => app(ChangeEquipmentStateAction::class)->handle($actor, $input['equipmentId'], ChangeEquipmentStateAction::ARCHIVE, 1, $input['operationKey']),
        };
        echo json_encode(['receipt' => $receipt], JSON_THROW_ON_ERROR).PHP_EOL;
    }
} catch (Throwable $error) {
    echo json_encode(['errorCode' => $error instanceof DomainError ? $error->errorCode() : null, 'errorClass' => $error::class], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(2);
}
