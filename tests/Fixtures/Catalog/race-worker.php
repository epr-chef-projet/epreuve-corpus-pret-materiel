<?php

declare(strict_types=1);

use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CloseEquipmentOperation\CloseEquipmentOperationAction;
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
use Illuminate\Support\Facades\Redis;
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
$notificationRedis = null;
$notificationFake = null;
$notificationQueue = 'queues:testing-notifications';
if (($input['nativeNotifications'] ?? false) === true) {
    if ((int) config('database.redis.default.database') !== 14 || config('database.redis.default.url')) {
        throw new RuntimeException('Catalogue race notifications require the testing Redis database.');
    }
    $prefix = 'epr_testing_catalog_race_'.hash('sha256', $directory.'/'.$input['worker']).'_';
    config([
        'database.redis.catalog_race' => [...config('database.redis.default'), 'options' => ['prefix' => $prefix]],
        'queue.connections.redis.connection' => 'catalog_race',
    ]);
    $notificationRedis = Redis::connection('catalog_race');
    if ($notificationRedis->llen($notificationQueue) !== 0) {
        throw new RuntimeException('Catalogue race notification namespace is not empty.');
    }
} else {
    $notificationFake = Queue::fake([PublishCatalogInvalidation::class]);
}
$notificationCount = static function () use ($notificationRedis, $notificationQueue, $notificationFake): int {
    if ($notificationRedis !== null) {
        return $notificationRedis->llen($notificationQueue);
    }

    return $notificationFake->pushed(PublishCatalogInvalidation::class)->count();
};
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
    $match = ($input['pause'] === 'intention' && str_contains($query->sql, 'pg_advisory_xact_lock'))
        || ($input['pause'] === 'equipment' && str_contains(strtolower($query->sql), 'for update'))
        || ($input['pause'] === 'decision' && str_contains(strtolower($query->sql), 'insert into') && str_contains($query->sql, 'operation_receipts'));
    if ($match) {
        $hold();
    }
});
$user = Accounts::user('atelier-1-manager');
$actor = new ActorContext($user['id'], $user['workshopId'], $user['role']);
$exitCode = 0;
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
        /** @return array<string, mixed> */
        $run = static function () use ($actor, $input): array {
            if ($input['action'] === 'close') {
                return ['result' => app(CloseEquipmentOperationAction::class)->handle($actor, $input['operationKey'])];
            }

            return ['receipt' => match ($input['action']) {
                'create' => app(CreateEquipmentAction::class)->handle($actor, new EquipmentInput($input['name'], 'A03 race fixture', null), 1, $input['operationKey']),
                'edit' => app(EditEquipmentTextAction::class)->handle($actor, $input['equipmentId'], new EquipmentInput($input['name'], 'A03 race fixture', null), 1, $input['operationKey']),
                'archive' => app(ChangeEquipmentStateAction::class)->handle($actor, $input['equipmentId'], ChangeEquipmentStateAction::ARCHIVE, 1, $input['operationKey']),
                default => throw new RuntimeException('Unknown catalogue race action.'),
            }];
        };
        if (($input['rollback'] ?? false) === true) {
            $rollback = new RuntimeException('Controlled race rollback after decision.');
            try {
                app(SqlUnit::class)->forActor($actor, static function () use ($run, $rollback): void {
                    $run();
                    throw $rollback;
                });
                throw new LogicException('The controlled race rollback was not performed.');
            } catch (RuntimeException $error) {
                if ($error !== $rollback) {
                    throw $error;
                }
            }
            $result = ['rolledBack' => true];
        } else {
            $result = $run();
        }
        echo json_encode([...$result, 'notifications' => $notificationCount()], JSON_THROW_ON_ERROR).PHP_EOL;
    }
} catch (Throwable $error) {
    echo json_encode(['errorCode' => $error instanceof DomainError ? $error->errorCode() : null, 'errorClass' => $error::class, 'notifications' => $notificationCount()], JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 2;
} finally {
    if ($notificationRedis !== null) {
        $notificationRedis->del($notificationQueue, $notificationQueue.':notify');
    }
}
exit($exitCode);
