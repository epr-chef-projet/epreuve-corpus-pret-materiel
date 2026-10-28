<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog\Equipment;

use App\Infrastructure\Operations\ClosedOperationResultData;
use App\Infrastructure\Operations\CommandReceipt;
use App\Infrastructure\Operations\OperationClosureData;
use App\Infrastructure\Operations\OperationResultData;
use App\Infrastructure\Operations\RetrySqlUnit;
use App\Modules\Catalog\Equipment\UseCases\CloseEquipmentOperation\CloseEquipmentOperationAction;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInputFactory;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentTextService;
use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\Catalog\PublicApi\ReadEquipmentBatch;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class EquipmentContractTest extends TestCase
{
    private function actor(string $login = 'atelier-1-manager'): ActorContext
    {
        $user = Accounts::user($login);

        return new ActorContext($user['id'], $user['workshopId'], $user['role']);
    }

    public function test_standalone_read_owns_a_short_read_only_snapshot_without_exposing_sql_objects(): void
    {
        $units = app(SqlUnit::class);
        $units->forActorRead($this->actor(), function (): void {
            $this->assertSame(0, func_num_args());
            $mode = DB::selectOne("SELECT current_setting('transaction_isolation') AS isolation,current_setting('transaction_read_only') AS readonly,current_setting('transaction_timeout') AS budget");
            $this->assertSame('repeatable read', $mode->isolation);
            $this->assertSame('on', $mode->readonly);
            $this->assertSame('5s', $mode->budget);
        });
        $this->assertSame('void', (string) (new ReflectionMethod(SqlUnit::class, 'forActorRead'))->getReturnType());
        $this->assertSame(0, DB::connection('pgsql')->transactionLevel());
    }

    public function test_participant_read_retains_the_outer_unit_but_refuses_another_context(): void
    {
        $units = app(SqlUnit::class);
        $actor = $this->actor();
        $units->forActor($actor, function () use ($units, $actor): void {
            $before = DB::connection('pgsql')->transactionLevel();
            $units->forActorRead($actor, function () use ($before): void {
                $this->assertSame($before, DB::connection('pgsql')->transactionLevel());
                $this->assertSame('read committed', DB::selectOne("SELECT current_setting('transaction_isolation') AS value")->value);
            });
            expect(fn () => $units->forActorRead($this->actor('atelier-2-manager'), static function (): void {}))->toThrow(LogicException::class);
        });
    }

    public function test_batch_boundary_rejects_duplicate_oversized_and_noncanonical_identifiers(): void
    {
        $read = app(ReadEquipmentBatch::class);
        $actor = $this->actor();
        $id = '019a0000-0000-7000-8000-000000000001';
        foreach ([[$id, $id], array_fill(0, 101, $id), ['invalid'], [strtoupper('019a000a-0000-7000-8000-000000000001')]] as $ids) {
            expect(fn () => $read->handle($actor, $ids))->toThrow(\InvalidArgumentException::class);
        }
        $this->assertSame([], $read->handle($actor, []));
    }

    public function test_public_equipment_value_is_immutable_and_serializes_only_selected_fields_without_sql(): void
    {
        $value = new EquipmentData('019a0000-0000-7000-8000-000000000001', '<script>text</script>', null, 'Tools', 0, 'active', 1, null);
        DB::connection('pgsql')->enableQueryLog();
        DB::connection('pgsql')->flushQueryLog();
        $json = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true);
        $this->assertSame(['equipmentId', 'name', 'description', 'category', 'totalQuantity', 'status', 'equipmentVersion', 'image'], array_keys($json));
        $this->assertNull($json['image']);
        $this->assertSame([], DB::connection('pgsql')->getQueryLog());
        DB::connection('pgsql')->disableQueryLog();
        expect(function () use ($value): void {
            (new \ReflectionProperty($value, 'name'))->setValue($value, 'changed');
        })->toThrow(\Error::class);
        $this->assertSame('<script>text</script>', $value->name);
    }

    public function test_invalid_utf8_cannot_be_silently_normalized_to_an_absent_optional_description(): void
    {
        $invalid = "invalid\xC3text";
        $this->assertSame($invalid, EquipmentInputFactory::normalize($invalid));
        try {
            app(EquipmentInputFactory::class)->create(['name' => 'Valid', 'category' => 'Tools', 'description' => $invalid, 'totalQuantity' => 0, 'operationKey' => '019a0000-0000-7000-8000-000000000001']);
            $this->fail('Malformed optional text was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('description', $error->errors());
        }
        expect(fn () => app(EquipmentTextService::class)->validate(new EquipmentInput('Valid', 'Tools', $invalid)))->toThrow(\InvalidArgumentException::class);
    }

    public function test_only_recognized_native_sql_failures_with_a_completed_rollback_are_retried(): void
    {
        foreach (['40001', '40P01'] as $state) {
            $attempts = 0;
            app(RetrySqlUnit::class)->run(function (int $remaining) use ($state, &$attempts): void {
                $this->assertGreaterThan(0, $remaining);
                $this->assertLessThanOrEqual(15000, $remaining);
                $this->assertSame(0, DB::connection('pgsql')->transactionLevel());
                app(SqlUnit::class)->forActor($this->actor(), function () use ($state, &$attempts): void {
                    if (++$attempts < 3) {
                        DB::unprepared("DO \$proof\$ BEGIN RAISE EXCEPTION 'Controlled SQL failure' USING ERRCODE = '{$state}'; END \$proof\$");
                    }
                });
            });
            $this->assertSame(3, $attempts);
            $this->assertFalse(DB::connection('pgsql')->getPdo()->inTransaction());
        }
        $attempts = 0;
        try {
            app(RetrySqlUnit::class)->run(function (int $remaining) use (&$attempts): void {
                app(SqlUnit::class)->forActor($this->actor(), function () use (&$attempts): void {
                    $attempts++;
                    DB::unprepared("DO \$proof\$ BEGIN RAISE EXCEPTION 'Controlled timeout' USING ERRCODE = '55P03'; END \$proof\$");
                });
            });
            $this->fail('A timeout should leave the retry policy.');
        } catch (QueryException $error) {
            $this->assertSame('55P03', $error->errorInfo[0] ?? null);
        }
        $this->assertSame(1, $attempts);
    }

    public function test_a_closure_is_a_distinct_immutable_value_and_preserves_the_two_previous_wire_shapes(): void
    {
        $key = '019a0000-0000-7000-8000-000000000001';
        $closure = new OperationClosureData($key, '2026-10-26T12:30:00.123Z');
        $closed = new ClosedOperationResultData($closure);
        $receipt = new CommandReceipt($key, 'catalog.equipment.create', 'equipment', $key, 1, '2026-10-26T12:30:00.123Z', false);
        $serialized = json_decode(json_encode($closed, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(3, $serialized);
        $this->assertSame('closed', $serialized['status']);
        $this->assertNull($serialized['receipt']);
        $this->assertCount(3, $serialized['closure']);
        $this->assertSame($key, $serialized['closure']['operationKey']);
        $this->assertSame('catalog.equipment.create', $serialized['closure']['operationType']);
        $this->assertSame('2026-10-26T12:30:00.123Z', $serialized['closure']['closedAt']);
        $this->assertSame(['status' => 'notEstablished', 'receipt' => null], json_decode(json_encode(new OperationResultData('notEstablished', null), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['status' => 'applied', 'receipt' => json_decode(json_encode($receipt, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR)], json_decode(json_encode(new OperationResultData('applied', $receipt), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        expect(function () use ($closure): void {
            (new \ReflectionProperty($closure, 'operationKey'))->setValue($closure, '019a0000-0000-7000-8000-000000000002');
        })->toThrow(\Error::class);
        expect(function () use ($closed): void {
            (new \ReflectionProperty($closed, 'status'))->setValue($closed, 'applied');
        })->toThrow(\Error::class);
    }

    public function test_closure_refuses_an_outer_read_only_snapshot_without_recording_an_outcome(): void
    {
        $actor = $this->actor();
        $key = '019a0000-0000-7000-8000-000000000004';

        app(SqlUnit::class)->forActorRead($actor, function () use ($actor, $key): void {
            expect(fn () => app(CloseEquipmentOperationAction::class)->handle($actor, $key))->toThrow(LogicException::class);
            $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
        });

        $this->assertSame(0, DB::connection('pgsql')->transactionLevel());
    }

    /** @return iterable<string, array{string}> */
    public static function incompatibleClosureUnits(): iterable
    {
        yield 'writable repeatable read' => ['REPEATABLE READ, READ WRITE'];
        yield 'read only read committed' => ['READ COMMITTED, READ ONLY'];
    }

    #[DataProvider('incompatibleClosureUnits')]
    public function test_closure_also_refuses_each_incompatible_sql_mode_independently(string $mode): void
    {
        $actor = $this->actor();
        $key = '019a0000-0000-7000-8000-000000000005';

        DB::transaction(function () use ($actor, $key, $mode): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL '.$mode);
            app(SqlUnit::class)->forActor($actor, function () use ($actor, $key): void {
                expect(fn () => app(CloseEquipmentOperationAction::class)->handle($actor, $key))->toThrow(LogicException::class);
                $this->assertDatabaseMissing('operation_receipts', ['operation_key' => $key]);
            });
        });

        $this->assertSame(0, DB::connection('pgsql')->transactionLevel());
    }
}
