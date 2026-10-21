<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Catalog\Equipment;

use App\Modules\Catalog\Database\Factories\EquipmentFactory;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\PublicApi\Errors\ContentConflict;
use App\Modules\Catalog\PublicApi\Errors\TotalBelowCommitments;
use App\Modules\Catalog\PublicApi\Errors\VersionExhausted;
use App\Modules\Catalog\PublicApi\LockEquipmentBatch;
use App\Modules\Catalog\PublicApi\ReplaceTotalWithinUnit;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

/** No framework test transaction: the guards and savepoint lifetimes are exercised explicitly. */
final class EquipmentParticipantTest extends TestCase
{
    private function actor(string $login = 'atelier-1-manager'): ActorContext
    {
        $user = Accounts::user($login);

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    /** @param Closure(ActorContext, Equipment): void $test */
    private function fixture(Closure $test): void
    {
        Queue::fake([PublishCatalogInvalidation::class]);
        $actor = $this->actor();
        $rollback = new RuntimeException('Owned participant fixture rollback');
        try {
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $test, $rollback): void {
                $row = EquipmentFactory::new()->createOne(['workshop_id' => $actor->workshopId, 'total_quantity' => 5]);
                $test($actor, $row);
                throw $rollback;
            });
        } catch (RuntimeException $error) {
            if ($error !== $rollback) {
                throw $error;
            }
        }
        $this->assertSame(0, DB::connection('pgsql')->transactionLevel());
    }

    public function test_lock_rejects_missing_wrong_connection_read_only_and_foreign_context_units(): void
    {
        $lock = app(LockEquipmentBatch::class);
        $actor = $this->actor();
        expect(fn () => $lock->handle($actor, []))->toThrow(LogicException::class);
        DB::connection('auth_lookup')->transaction(function () use ($lock, $actor): void {
            expect(fn () => $lock->handle($actor, []))->toThrow(LogicException::class);
        });
        app(SqlUnit::class)->forActorRead($actor, function () use ($lock, $actor): void {
            expect(fn () => $lock->handle($actor, []))->toThrow(LogicException::class);
            $this->assertSame('on', DB::selectOne("SELECT current_setting('transaction_read_only') AS value")->value);
        });
        app(SqlUnit::class)->forActor($actor, function () use ($lock): void {
            expect(fn () => $lock->handle($this->actor('atelier-2-manager'), []))->toThrow(LogicException::class);
            $this->assertSame([], $lock->handle($this->actor(), []));
        });
    }

    public function test_lock_refuses_a_writable_repeatable_read_unit_before_acquiring_rows(): void
    {
        DB::connection('pgsql')->transaction(function (): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            app(SqlUnit::class)->forActor($this->actor(), function (): void {
                expect(fn () => app(LockEquipmentBatch::class)->handle($this->actor(), []))->toThrow(LogicException::class);
            });
        });
    }

    public function test_batches_validate_before_sql_and_cannot_reverse_order_or_exceed_the_cumulative_bound(): void
    {
        $lock = app(LockEquipmentBatch::class);
        $actor = $this->actor();
        $id = '019a000a-0000-7000-8000-000000000001';
        foreach ([[$id, $id], array_fill(0, 201, $id), ['bad'], [strtoupper($id)], [1 => $id]] as $ids) {
            expect(fn () => $lock->handle($actor, $ids))->toThrow(InvalidArgumentException::class);
        }
        $this->fixture(function (ActorContext $actor, Equipment $row) use ($lock): void {
            $low = EquipmentFactory::new()->createOne(['id' => '019a000a-0000-7000-8000-000000000001', 'workshop_id' => $actor->workshopId]);
            $lock->handle($actor, [$row->id]);
            expect(fn () => $lock->handle($actor, [$low->id]))->toThrow(LogicException::class);
        });
        $this->fixture(function (ActorContext $actor) use ($lock): void {
            $rows = EquipmentFactory::new()->count(200)->create(['workshop_id' => $actor->workshopId]);
            $ids = array_values($rows->pluck('id')->all());
            $result = $lock->handle($actor, array_reverse($ids));
            $this->assertCount(200, $result);
            sort($ids, SORT_STRING);
            $this->assertSame($ids, array_column($result, 'equipmentId'));
            expect(fn () => $lock->handle($actor, [(string) Str::uuid7()]))->toThrow(LogicException::class);
            $this->assertCount(1, $lock->handle($actor, [$ids[0]]));
        });
    }

    public function test_lock_materializes_only_selected_immutable_states_without_follow_up_sql(): void
    {
        $this->fixture(function (ActorContext $actor, Equipment $row): void {
            $values = app(LockEquipmentBatch::class)->handle($actor, [(string) Str::uuid7(), $row->id]);
            $this->assertCount(1, $values);
            DB::connection('pgsql')->enableQueryLog();
            DB::connection('pgsql')->flushQueryLog();
            $json = json_decode(json_encode($values, JSON_THROW_ON_ERROR), true);
            $this->assertSame(['equipmentId', 'name', 'description', 'category', 'totalQuantity', 'status', 'equipmentVersion', 'image'], array_keys($json[0]));
            $this->assertSame([], DB::connection('pgsql')->getQueryLog());
            DB::connection('pgsql')->disableQueryLog();
            $this->assertSame($row->id, $json[0]['equipmentId']);
            $this->assertNull($json[0]['image']);
            expect(function () use ($values): void {
                (new \ReflectionProperty($values[0], 'equipmentVersion'))->setValue($values[0], 4);
            })->toThrow(\Error::class);
        });
    }

    public function test_total_requires_previously_acquired_protection_and_current_manager_rights(): void
    {
        $this->fixture(function (ActorContext $actor, Equipment $row): void {
            $total = app(ReplaceTotalWithinUnit::class);
            expect(fn () => $total->handle($actor, $row->id, 4, 1, 0))->toThrow(LogicException::class);
            app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
            $this->assertSame(2, $total->handle($actor, $row->id, 4, 1, 4));
            $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'total_quantity' => 4, 'version' => 2]);
            $this->assertSame(0, DB::table('operation_receipts')->where('equipment_id', $row->id)->count());
            Queue::assertPushed(PublishCatalogInvalidation::class, static fn (PublishCatalogInvalidation $job): bool => $job->afterCommit === true);
        });
        foreach (['atelier-1-borrower-1', 'atelier-1-staff'] as $login) {
            $this->fixture(function (ActorContext $manager, Equipment $row) use ($login): void {
                $actor = $this->actor($login);
                DB::select("SELECT set_config('epr.actor_id', ?, true), set_config('epr.actor_role', ?, true)", [$actor->actorId, $actor->role]);
                app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
                expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($actor, $row->id, 1, 1, 0))->toThrow(AuthorizationException::class);
                $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'total_quantity' => 5, 'version' => 1]);
            });
        }
    }

    public function test_rejected_total_minimum_version_and_bounds_preserve_the_row(): void
    {
        $this->fixture(function (ActorContext $actor, Equipment $row): void {
            app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
            $total = app(ReplaceTotalWithinUnit::class);
            try {
                $total->handle($actor, $row->id, 2, 1, 3);
                $this->fail('The protected minimum must be honored.');
            } catch (TotalBelowCommitments $error) {
                $this->assertSame(3, $error->minimumQuantity);
                $this->assertSame('catalog.total_below_commitments', $error->errorCode());
            }
            expect(fn () => $total->handle($actor, $row->id, 0, 2, 3))->toThrow(ContentConflict::class);
            foreach ([[-1, 1, 0], [1000000, 1, 0], [0, 0, 0], [0, 2147483648, 0], [0, 1, -1], [0, 1, 1000000]] as [$quantity,$version,$minimum]) {
                expect(fn () => $total->handle($actor, $row->id, $quantity, $version, $minimum))->toThrow(InvalidArgumentException::class);
            }
            $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'total_quantity' => 5, 'version' => 1]);
            $this->assertSame(2, $total->handle($actor, $row->id, 0, 1, 0));
            $row->refresh();
            $row->version = 2147483647;
            $row->save();
            expect(fn () => $total->handle($actor, $row->id, 1, 2147483647, 0))->toThrow(VersionExhausted::class);
            $this->assertDatabaseHas('catalog_equipment', ['id' => $row->id, 'total_quantity' => 0, 'version' => 2147483647]);
        });
    }

    public function test_savepoint_rollback_discards_new_protection_but_release_retains_it_and_outer_rollback_discards_the_write(): void
    {
        $id = null;
        $this->fixture(function (ActorContext $actor, Equipment $row) use (&$id): void {
            $id = $row->id;
            $db = DB::connection('pgsql');
            $db->beginTransaction();
            app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
            $db->rollBack();
            expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($actor, $row->id, 3, 1, 0))->toThrow(LogicException::class);
            $db->beginTransaction();
            app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
            $db->commit();
            $this->assertSame(2, app(ReplaceTotalWithinUnit::class)->handle($actor, $row->id, 3, 1, 0));
            Queue::assertPushed(PublishCatalogInvalidation::class, static fn (PublishCatalogInvalidation $job): bool => $job->afterCommit === true);
        });
        $this->assertIsString($id);
        app(SqlUnit::class)->forActorRead($this->actor(), function () use ($id): void {
            $this->assertDatabaseMissing('catalog_equipment', ['id' => $id]);
        });
        app(SqlUnit::class)->forActor($this->actor(), function () use ($id): void {
            expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($this->actor(), $id, 1, 1, 0))->toThrow(LogicException::class);
        });
    }

    public function test_wrong_context_and_inherited_session_trace_never_authorize_a_total_write(): void
    {
        $this->fixture(function (ActorContext $actor, Equipment $row): void {
            app(LockEquipmentBatch::class)->handle($actor, [$row->id]);
            expect(fn () => app(ReplaceTotalWithinUnit::class)->handle($this->actor('atelier-2-manager'), $row->id, 1, 1, 0))->toThrow(LogicException::class);
        });
        DB::select("SELECT set_config('epr.catalog_equipment_locks', ?, false)", [json_encode(['unit' => '0', 'workshop' => $this->actor()->workshopId, 'actor' => $this->actor()->actorId, 'role' => 'manager', 'ids' => [(string) Str::uuid7()]], JSON_THROW_ON_ERROR)]);
        try {
            app(SqlUnit::class)->forActor($this->actor(), function (): void {
                expect(fn () => app(LockEquipmentBatch::class)->handle($this->actor(), []))->toThrow(LogicException::class);
            });
        } finally {
            DB::statement('RESET epr.catalog_equipment_locks');
        }
    }
}
