<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog\Equipment;

use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class EquipmentConcurrencyTest extends TestCase
{
    /** @var list<string> */
    private static array $keys = [];

    private static string $runId = '';

    /** @var list<Process> */
    private array $children = [];

    protected function tearDown(): void
    {
        try {
            foreach ($this->children as $child) {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
            }
            $this->cleanupRecipe();
        } finally {
            parent::tearDown();
        }
    }

    private function actor(): ActorContext
    {
        $user = Accounts::user('atelier-1-manager');

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    private function key(): string
    {
        $key = (string) Str::uuid7();
        self::$runId = self::$runId === '' ? (string) Str::uuid7() : self::$runId;
        self::$keys[] = $key;

        return $key;
    }

    /** @param array<string, mixed> $input */
    private function child(array $input): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Catalog/race-worker.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'testing', 'DB_URL' => '', 'EPR_CATALOG_RACE' => '1', 'EPR_BROWSER_TESTS' => 'false', 'LARAVEL_STORAGE_PATH' => storage_path(), 'APP_CONFIG_CACHE' => '/tmp/epr-race-config.php']);
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(12);
        $process->start();
        $this->children[] = $process;

        return $process;
    }

    private function waitFor(\Closure $condition): void
    {
        $deadline = microtime(true) + 6;
        while (! $condition()) {
            if (microtime(true) > $deadline) {
                $this->fail('Independent SQL race did not reach the required barrier.');
            }
            usleep(10_000);
        }
    }

    /** @param array<string, mixed> $first
     * @param  array<string, mixed>  $second
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function race(array $first, array $second, string $pause): array
    {
        $directory = storage_path('catalog-races/'.Str::uuid7());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not prepare race barriers.');
        }
        $a = $this->child([...$first, 'directory' => $directory, 'worker' => 'a', 'pause' => $pause]);
        $this->waitFor(static fn (): bool => is_file($directory.'/held') || ! $a->isRunning());
        $this->assertTrue($a->isRunning(), $a->getErrorOutput().$a->getOutput());
        $b = $this->child([...$second, 'directory' => $directory, 'worker' => 'b', 'pause' => 'none']);
        $this->waitFor(static fn (): bool => is_file($directory.'/b.pid') || ! $b->isRunning());
        $this->assertTrue($b->isRunning(), $b->getErrorOutput().$b->getOutput());
        $pid = (int) file_get_contents($directory.'/b.pid');
        $this->waitFor(static fn (): bool => DB::selectOne('SELECT count(*) AS count FROM pg_locks WHERE pid=? AND NOT granted', [$pid])->count > 0);
        file_put_contents($directory.'/release', 'release');
        $a->wait();
        $b->wait();
        $firstResult = json_decode(trim($a->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $secondResult = json_decode(trim($b->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $a->getExitCode(), $a->getErrorOutput());

        return [$firstResult, $secondResult];
    }

    public function test_two_simultaneous_creations_with_the_same_intention_produce_one_reference_and_one_receipt(): void
    {
        $key = $this->key();
        $name = '__a03_race_'.Str::uuid7();
        [$a,$b] = $this->race(['action' => 'create', 'operationKey' => $key, 'name' => $name], ['action' => 'create', 'operationKey' => $key, 'name' => $name], 'intention');
        $this->assertSame($a['receipt']['resourceId'], $b['receipt']['resourceId']);
        $this->assertFalse($a['receipt']['replayed']);
        $this->assertTrue($b['receipt']['replayed']);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($key, $a): void {
            $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $key)->count());
            $this->assertSame(1, DB::table('catalog_equipment')->where('id', $a['receipt']['resourceId'])->count());
        });
    }

    public function test_a_different_content_waiting_for_the_same_key_is_refused_after_the_first_commit(): void
    {
        $key = $this->key();
        [$a,$b] = $this->race(['action' => 'create', 'operationKey' => $key, 'name' => '__a03_race_first'], ['action' => 'create', 'operationKey' => $key, 'name' => '__a03_race_different'], 'intention');
        $this->assertSame('operations.intent_mismatch', $b['errorCode']);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($key, $a): void {
            $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $key)->count());
            $this->assertDatabaseHas('catalog_equipment', ['id' => $a['receipt']['resourceId'], 'name' => '__a03_race_first']);
        });
    }

    public function test_two_edits_of_the_same_version_cannot_silently_overwrite_one_another(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $created = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a03_race_initial', 'A03 race fixture', null), 1, $this->key());
        [$a,$b] = $this->race(['action' => 'edit', 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId, 'name' => '__a03_race_winner'], ['action' => 'edit', 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId, 'name' => '__a03_race_later'], 'equipment');
        $this->assertSame(2, $a['receipt']['resultVersion']);
        $this->assertSame('catalog.content_conflict', $b['errorCode']);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($created): void {
            $this->assertDatabaseHas('catalog_equipment', ['id' => $created->resourceId, 'name' => '__a03_race_winner', 'version' => 2]);
        });
    }

    public function test_native_redis_publication_waits_for_outer_commit_and_is_discarded_after_outer_rollback(): void
    {
        $prefix = 'epr_testing_catalog_'.Str::uuid7().'_';
        config(['database.redis.catalog_proof' => [...config('database.redis.default'), 'options' => ['prefix' => $prefix]], 'queue.connections.redis.connection' => 'catalog_proof']);
        $redis = Redis::connection('catalog_proof');
        $queue = 'queues:testing-notifications';
        $actor = $this->actor();
        $action = app(CreateEquipmentAction::class);
        $rolledBackKey = $this->key();
        $this->assertSame(0, $redis->llen($queue));
        try {
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $action, $rolledBackKey, $redis, $queue): void {
                $action->handle($actor, new EquipmentInput('__a03_rollback', 'A03 transaction fixture', null), 1, $rolledBackKey);
                $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $rolledBackKey)->count());
                $this->assertSame(0, $redis->llen($queue));
                throw new \RuntimeException('Deliberate outer rollback');
            });
            $this->fail('The transaction should have rolled back.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Deliberate outer rollback', $error->getMessage());
        }
        app(SqlUnit::class)->forActorRead($actor, function () use ($rolledBackKey): void {
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $rolledBackKey]);
            $this->assertDatabaseMissing('catalog_equipment', ['name' => '__a03_rollback', 'category' => 'A03 transaction fixture']);
        });
        $this->assertSame(0, $redis->llen($queue));
        try {
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $action, $redis, $queue): void {
                $action->handle($actor, new EquipmentInput('__a03_commit', 'A03 transaction fixture', null), 1, $this->key());
                $this->assertSame(0, $redis->llen($queue));
            });
            $this->assertSame(1, $redis->llen($queue));
            $payload = json_decode($redis->lindex($queue, 0), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(PublishCatalogInvalidation::class, $payload['displayName']);
        } finally {
            $redis->del($queue, $queue.':notify');
        }
    }

    private function cleanupRecipe(): void
    {
        if (self::$keys === []) {
            return;
        }
        $actor = $this->actor();
        $pairs = implode(',', array_map(static fn (string $key): string => "('{$actor->actorId}'::uuid,'{$key}'::uuid)", self::$keys));
        $sql = "BEGIN;\nDO \$guard\$ BEGIN IF current_database()<>'testing' OR NOT EXISTS(SELECT FROM pg_roles WHERE rolname=session_user AND rolsuper) THEN RAISE EXCEPTION 'Testing cleanup target refused'; END IF; END \$guard\$;\nCREATE TEMP TABLE catalog_fixture_cleanup ON COMMIT DROP AS SELECT equipment_id FROM epr.operation_receipts WHERE (actor_id,operation_key) IN ($pairs) AND operation_type='catalog.equipment.create';\nDELETE FROM epr.operation_receipts WHERE (actor_id,operation_key) IN ($pairs);\nDELETE FROM epr.catalog_equipment WHERE id IN(SELECT equipment_id FROM catalog_fixture_cleanup);\nCOMMIT;\n";
        file_put_contents(base_path('.epr-local/catalog-race-cleanup-'.self::$runId.'.sql'), $sql);
        chmod(base_path('.epr-local/catalog-race-cleanup-'.self::$runId.'.sql'), 0600);
    }
}
