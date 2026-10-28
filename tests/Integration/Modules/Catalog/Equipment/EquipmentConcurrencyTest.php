<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Catalog\Equipment;

use App\Infrastructure\Operations\IntentClosed;
use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\PublicApi\LockEquipmentBatch;
use App\Modules\Catalog\PublicApi\ReplaceTotalWithinUnit;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Database\QueryException;
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
    private function race(array $first, array $second, string $pause, bool $waitsForLock = true): array
    {
        $directory = storage_path('catalog-races/'.Str::uuid7());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not prepare race barriers.');
        }
        $nativeNotifications = in_array('close', [$first['action'], $second['action']], true);
        $first = [...$first, 'nativeNotifications' => $nativeNotifications];
        $second = [...$second, 'nativeNotifications' => $nativeNotifications];
        $a = $this->child([...$first, 'directory' => $directory, 'worker' => 'a', 'pause' => $pause]);
        $this->waitFor(static fn (): bool => is_file($directory.'/held') || ! $a->isRunning());
        $this->assertTrue($a->isRunning(), $a->getErrorOutput().$a->getOutput());
        $b = $this->child([...$second, 'directory' => $directory, 'worker' => 'b', 'pause' => 'none']);
        $this->waitFor(static fn (): bool => is_file($directory.'/b.pid') || ! $b->isRunning());
        $this->assertTrue($b->isRunning(), $b->getErrorOutput().$b->getOutput());
        $pid = (int) file_get_contents($directory.'/b.pid');
        if ($waitsForLock) {
            $this->waitFor(static fn (): bool => DB::selectOne('SELECT count(*) AS count FROM pg_locks WHERE pid=? AND NOT granted', [$pid])->count > 0);
        } else {
            $this->waitFor(static fn (): bool => $b->getStatus() === Process::STATUS_TERMINATED);
            $this->assertSame(0, $b->getExitCode(), $b->getErrorOutput().$b->getOutput());
        }
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

    public function test_a_creation_committed_before_resolution_returns_its_original_receipt_without_another_effect(): void
    {
        $key = $this->key();
        $name = '__af02_create_first_'.Str::uuid7();

        [$created, $resolved] = $this->race(['action' => 'create', 'operationKey' => $key, 'name' => $name], ['action' => 'close', 'operationKey' => $key], 'intention');

        $this->assertSame('applied', $resolved['result']['status']);
        $this->assertSame($created['receipt']['resourceId'], $resolved['result']['receipt']['resourceId']);
        $this->assertSame($created['receipt']['recordedAt'], $resolved['result']['receipt']['recordedAt']);
        $this->assertTrue($resolved['result']['receipt']['replayed']);
        $this->assertSame(1, $created['notifications']);
        $this->assertSame(0, $resolved['notifications']);
        $this->assertAppliedOutcome($key, $created['receipt']['resourceId'], $name);
    }

    public function test_a_committed_closure_refuses_a_waiting_creation_and_a_later_changed_body(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $key = $this->key();
        $name = '__af02_close_first_'.Str::uuid7();

        [$closed, $created] = $this->race(['action' => 'close', 'operationKey' => $key], ['action' => 'create', 'operationKey' => $key, 'name' => $name], 'intention');

        $this->assertSame('closed', $closed['result']['status']);
        $this->assertNull($closed['result']['receipt']);
        $this->assertSame($key, $closed['result']['closure']['operationKey']);
        $this->assertSame(CreateEquipmentAction::OPERATION, $closed['result']['closure']['operationType']);
        $this->assertSame('operations.intent_closed', $created['errorCode']);
        $this->assertSame(IntentClosed::class, $created['errorClass']);
        $this->assertSame(0, $closed['notifications']);
        $this->assertSame(0, $created['notifications']);
        expect(fn () => app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput($name.'_changed', 'Changed late body', null), 2, $key))->toThrow(IntentClosed::class);
        $this->assertClosedOutcome($key);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($name): void {
            $this->assertDatabaseMissing('catalog_equipment', ['name' => $name]);
            $this->assertDatabaseMissing('catalog_equipment', ['name' => $name.'_changed']);
        });
        Queue::assertNotPushed(PublishCatalogInvalidation::class);
    }

    public function test_two_simultaneous_closures_keep_the_same_terminal_proof_without_publishing(): void
    {
        $key = $this->key();
        $input = ['action' => 'close', 'operationKey' => $key];

        [$first, $second] = $this->race($input, $input, 'intention');

        $this->assertSame('closed', $first['result']['status']);
        $this->assertSame($first['result'], $second['result']);
        $this->assertSame(0, $first['notifications']);
        $this->assertSame(0, $second['notifications']);
        $this->assertClosedOutcome($key);
    }

    public function test_a_creation_rolled_back_after_its_receipt_allows_the_waiting_closure_to_commit(): void
    {
        $key = $this->key();
        $name = '__af02_create_rollback_'.Str::uuid7();

        [$rolledBack, $closed] = $this->race(['action' => 'create', 'operationKey' => $key, 'name' => $name, 'rollback' => true], ['action' => 'close', 'operationKey' => $key], 'decision');

        $this->assertTrue($rolledBack['rolledBack']);
        $this->assertSame('closed', $closed['result']['status']);
        $this->assertSame(0, $rolledBack['notifications']);
        $this->assertSame(0, $closed['notifications']);
        $this->assertClosedOutcome($key);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($name): void {
            $this->assertDatabaseMissing('catalog_equipment', ['name' => $name]);
        });
    }

    public function test_a_closure_rolled_back_after_insertion_allows_the_waiting_creation_to_commit(): void
    {
        $key = $this->key();
        $name = '__af02_close_rollback_'.Str::uuid7();

        [$rolledBack, $created] = $this->race(['action' => 'close', 'operationKey' => $key, 'rollback' => true], ['action' => 'create', 'operationKey' => $key, 'name' => $name], 'decision');

        $this->assertTrue($rolledBack['rolledBack']);
        $this->assertSame(0, $rolledBack['notifications']);
        $this->assertSame(1, $created['notifications']);
        $this->assertAppliedOutcome($key, $created['receipt']['resourceId'], $name);
    }

    public function test_operation_storage_rejects_partial_successes_and_closures_with_fictitious_results(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $actor = $this->actor();
        $created = app(CreateEquipmentAction::class)->handle($actor, new EquipmentInput('__af02_constraints_'.Str::uuid7(), 'AF02 constraint fixture', null), 1, $this->key());
        $applied = ['status' => 'applied', 'operation_type' => CreateEquipmentAction::OPERATION, 'input_sha256' => str_repeat('a', 64), 'resource_type' => 'equipment', 'resource_id' => $created->resourceId, 'equipment_id' => $created->resourceId, 'result_version' => 1];
        $closed = ['status' => 'closed', 'operation_type' => CreateEquipmentAction::OPERATION, 'input_sha256' => null, 'resource_type' => null, 'resource_id' => null, 'equipment_id' => null, 'result_version' => null];
        $cases = [
            'unknown status' => [[...$applied, 'status' => 'pending'], '23514', 'operation_receipts_outcome_check'],
            'null status' => [[...$applied, 'status' => null], '23502', '"status"'],
            'invalid hash' => [[...$applied, 'input_sha256' => 'not-a-canonical-hash'], '23514', 'operation_receipts_hash_check'],
            'invalid version' => [[...$applied, 'result_version' => 0], '23514', 'operation_receipts_version_check'],
            'invalid resource type' => [[...$applied, 'resource_type' => 'loanRequest'], '23514', 'operation_receipts_resource_check'],
            'divergent resource id' => [[...$applied, 'resource_id' => (string) Str::uuid7()], '23514', 'operation_receipts_resource_check'],
        ];
        foreach (['input_sha256', 'resource_type', 'resource_id', 'equipment_id', 'result_version'] as $column) {
            $cases['partial success '.$column] = [[...$applied, $column => null], '23514', 'operation_receipts_outcome_check'];
            $cases['fictitious closed result '.$column] = [[...$closed, $column => $applied[$column]], '23514', 'operation_receipts_outcome_check'];
        }
        foreach (['catalog.equipment.update_text', 'catalog.equipment.archive', 'catalog.equipment.reactivate'] as $operationType) {
            $cases['closed other family '.$operationType] = [[...$closed, 'operation_type' => $operationType], '23514', 'operation_receipts_outcome_check'];
        }

        foreach ($cases as $label => [$outcome, $sqlState, $constraint]) {
            $key = $this->key();
            try {
                app(SqlUnit::class)->forActor($actor, static function () use ($actor, $key, $outcome): void {
                    DB::table('operation_receipts')->insert([
                        ...$outcome, 'id' => (string) Str::uuid7(), 'workshop_id' => $actor->workshopId, 'actor_id' => $actor->actorId,
                        'operation_key' => $key, 'recorded_at' => now(),
                    ]);
                });
                $this->fail('Invalid operation outcome was accepted: '.$label);
            } catch (QueryException $error) {
                $this->assertSame($sqlState, $error->errorInfo[0] ?? null, $label);
                $this->assertStringContainsString($constraint, $error->errorInfo[2] ?? '', $label);
            }
            app(SqlUnit::class)->forActorRead($actor, function () use ($key): void {
                $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
            });
        }
        Queue::assertPushed(PublishCatalogInvalidation::class, 1);
    }

    private function assertClosedOutcome(string $key): void
    {
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($key): void {
            $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $key)->count());
            $this->assertDatabaseHas('operation_receipts', [
                'operation_key' => $key, 'status' => 'closed', 'operation_type' => CreateEquipmentAction::OPERATION,
                'input_sha256' => null, 'resource_type' => null, 'resource_id' => null, 'equipment_id' => null, 'result_version' => null,
            ]);
        });
    }

    private function assertAppliedOutcome(string $key, string $resourceId, string $name): void
    {
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($key, $resourceId, $name): void {
            $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $key)->count());
            $this->assertDatabaseHas('operation_receipts', ['operation_key' => $key, 'status' => 'applied', 'resource_id' => $resourceId, 'equipment_id' => $resourceId, 'result_version' => 1]);
            $this->assertDatabaseHas('catalog_equipment', ['id' => $resourceId, 'name' => $name, 'version' => 1, 'total_quantity' => 1]);
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

    public function test_archive_and_edit_serialize_in_both_orders_without_silently_overwriting_the_other(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        foreach ([['archive', 'edit'], ['edit', 'archive']] as [$first, $second]) {
            $created = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a04_race_initial', 'A04 race fixture', null), 5, $this->key());
            [$a, $b] = $this->race(['action' => $first, 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId, 'name' => '__a04_edited'], ['action' => $second, 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId, 'name' => '__a04_later'], 'equipment');
            $this->assertSame(2, $a['receipt']['resultVersion']);
            $this->assertSame('catalog.content_conflict', $b['errorCode']);
            app(SqlUnit::class)->forActorRead($this->actor(), function () use ($created, $first): void {
                $this->assertDatabaseHas('catalog_equipment', ['id' => $created->resourceId, 'version' => 2, 'is_active' => $first !== 'archive', 'total_quantity' => 5, 'name' => $first === 'archive' ? '__a04_race_initial' : '__a04_edited']);
            });
        }
    }

    public function test_archive_and_participant_protection_share_the_same_lock_and_reread_after_waiting_in_both_orders(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        foreach ([['archive', 'lock'], ['lock', 'archive']] as [$first, $second]) {
            $created = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a04_lock_initial', 'A04 race fixture', null), 1, $this->key());
            [$a, $b] = $this->race(['action' => $first, 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId], ['action' => $second, 'operationKey' => $this->key(), 'equipmentId' => $created->resourceId], 'equipment');
            $state = $first === 'lock' ? $a['states'][0] : $b['states'][0];
            $this->assertSame($first === 'lock' ? 'active' : 'archived', $state['status']);
            $this->assertSame($first === 'lock' ? 1 : 2, $state['equipmentVersion']);
            app(SqlUnit::class)->forActorRead($this->actor(), function () use ($created): void {
                $this->assertDatabaseHas('catalog_equipment', ['id' => $created->resourceId, 'version' => 2, 'is_active' => false]);
            });
        }
    }

    public function test_simultaneous_same_archive_intention_has_only_one_effect_and_replays_the_receipt(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $created = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a04_same_archive', 'A04 race fixture', null), 1, $this->key());
        $key = $this->key();
        $input = ['action' => 'archive', 'operationKey' => $key, 'equipmentId' => $created->resourceId];
        [$a, $b] = $this->race($input, $input, 'intention');
        $this->assertSame(2, $a['receipt']['resultVersion']);
        $this->assertFalse($a['receipt']['replayed']);
        $this->assertTrue($b['receipt']['replayed']);
        $this->assertSame($a['receipt']['recordedAt'], $b['receipt']['recordedAt']);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($key): void {
            $this->assertSame(1, DB::table('operation_receipts')->where('operation_key', $key)->count());
        });
    }

    public function test_archive_and_total_notifications_reach_native_redis_only_after_outer_commit_and_never_after_rollback(): void
    {
        $prefix = 'epr_testing_a04_'.Str::uuid7().'_';
        config(['database.redis.catalog_proof' => [...config('database.redis.default'), 'options' => ['prefix' => $prefix]], 'queue.connections.redis.connection' => 'catalog_proof']);
        $redis = Redis::connection('catalog_proof');
        $queue = 'queues:testing-notifications';
        $actor = $this->actor();
        $created = app(CreateEquipmentAction::class)->handle($actor, new EquipmentInput('__a04_publication', 'A04 race fixture', null), 5, $this->key());
        $redis->del($queue, $queue.':notify');
        $archiveKey = $this->key();
        $rollback = new \RuntimeException('A04 controlled rollback');
        try {
            try {
                app(SqlUnit::class)->forActor($actor, function () use ($actor, $created, $archiveKey, $rollback, $redis, $queue): void {
                    app(LockEquipmentBatch::class)->handle($actor, [$created->resourceId]);
                    $this->assertSame(2, app(ReplaceTotalWithinUnit::class)->handle($actor, $created->resourceId, 3, 1, 3));
                    app(ChangeEquipmentStateAction::class)->handle($actor, $created->resourceId, ChangeEquipmentStateAction::ARCHIVE, 2, $archiveKey);
                    $this->assertSame(0, $redis->llen($queue));
                    throw $rollback;
                });
            } catch (\RuntimeException $error) {
                if ($error !== $rollback) {
                    throw $error;
                }
            }
            $this->assertSame(0, $redis->llen($queue));
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $created, $archiveKey, $redis, $queue): void {
                $this->assertDatabaseHas('catalog_equipment', ['id' => $created->resourceId, 'version' => 1, 'total_quantity' => 5, 'is_active' => true]);
                $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $archiveKey]);
                expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($actor, $created->resourceId, 3, 1, 0))->toThrow(\LogicException::class);
                app(LockEquipmentBatch::class)->handle($actor, [$created->resourceId]);
                app(ReplaceTotalWithinUnit::class)->handle($actor, $created->resourceId, 3, 1, 3);
                app(ChangeEquipmentStateAction::class)->handle($actor, $created->resourceId, ChangeEquipmentStateAction::ARCHIVE, 2, $archiveKey);
                $this->assertSame(0, $redis->llen($queue));
            });
            $this->assertSame(2, $redis->llen($queue));
            app(ChangeEquipmentStateAction::class)->handle($actor, $created->resourceId, ChangeEquipmentStateAction::ARCHIVE, 3, $this->key());
            $payloads = $redis->lrange($queue, 0, -1);
            $this->assertIsArray($payloads);
            $this->assertCount(2, $payloads);
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $created): void {
                expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($actor, $created->resourceId, 3, 3, 0))->toThrow(\LogicException::class);
            });
        } finally {
            $redis->del($queue, $queue.':notify');
        }
    }

    public function test_releasing_a_savepoint_retains_the_row_lock_and_rolling_it_back_releases_the_row_lock_and_trace(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        foreach (['lockRelease', 'lockRollback'] as $first) {
            $created = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a04_savepoint', 'A04 race fixture', null), 1, $this->key());
            [$a, $b] = $this->race(['action' => $first, 'equipmentId' => $created->resourceId], ['action' => 'archive', 'equipmentId' => $created->resourceId, 'operationKey' => $this->key()], 'savepoint', $first === 'lockRelease');
            $this->assertSame('active', $a['states'][0]['status']);
            $this->assertSame(2, $b['receipt']['resultVersion']);
            app(SqlUnit::class)->forActorRead($this->actor(), function () use ($created): void {
                $this->assertDatabaseHas('catalog_equipment', ['id' => $created->resourceId, 'version' => 2, 'is_active' => false]);
            });
        }
    }

    public function test_opposite_batch_input_orders_share_the_same_global_uuid_lock_order(): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $ids = [];
        foreach (['first', 'second'] as $suffix) {
            $ids[] = app(CreateEquipmentAction::class)->handle($this->actor(), new EquipmentInput('__a04_batch_'.$suffix, 'A04 race fixture', null), 1, $this->key())->resourceId;
        }
        sort($ids, SORT_STRING);
        [$a, $b] = $this->race(['action' => 'lock', 'equipmentIds' => array_reverse($ids)], ['action' => 'lock', 'equipmentIds' => $ids], 'equipment');
        $this->assertSame($ids, array_column($a['states'], 'equipmentId'));
        $this->assertSame($ids, array_column($b['states'], 'equipmentId'));
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
