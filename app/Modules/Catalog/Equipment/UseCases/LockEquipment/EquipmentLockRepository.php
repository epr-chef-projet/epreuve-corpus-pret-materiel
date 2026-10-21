<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\LockEquipment;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\EquipmentState;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Transaction-local bookkeeping follows PostgreSQL savepoint and outer rollback semantics. */
final class EquipmentLockRepository
{
    private const SETTING = 'epr.catalog_equipment_locks';

    public function __construct(private SqlUnit $units) {}

    /** @return list<string> */
    private function protectedIds(ActorContext $actor): array
    {
        $connection = DB::connection('pgsql');
        $this->units->assertActor($connection, $actor);
        $state = $connection->selectOne("SELECT current_setting('transaction_read_only') AS readonly, current_setting('transaction_isolation') AS isolation, pg_current_xact_id_if_assigned()::text AS unit, current_setting(?, true) AS locks", [self::SETTING]);
        if ($state === null || $state->readonly !== 'off' || $state->isolation !== 'read committed') {
            throw new LogicException('Equipment protection requires a writable READ COMMITTED SQL unit.');
        }
        $trace = json_decode($state->locks ?: 'null', true, flags: JSON_THROW_ON_ERROR);
        if ($trace === null) {
            return [];
        }
        if (! is_array($trace) || ($trace['unit'] ?? null) !== $state->unit || ($trace['workshop'] ?? null) !== $actor->workshopId || ($trace['actor'] ?? null) !== $actor->actorId || ($trace['role'] ?? null) !== $actor->role || ! is_array($trace['ids'] ?? null) || ! array_is_list($trace['ids']) || count($trace['ids']) > 200) {
            throw new LogicException('Equipment protection belongs to a different SQL unit or context.');
        }
        foreach ($trace['ids'] as $id) {
            if (! is_string($id) || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $id) !== 1) {
                throw new LogicException('Invalid equipment protection trace.');
            }
        }

        return $trace['ids'];
    }

    /** @param list<string> $ids
     * @return list<EquipmentState>
     */
    public function acquire(ActorContext $actor, array $ids): array
    {
        $protected = $this->protectedIds($actor);
        $additional = array_values(array_diff($ids, $protected));
        sort($additional, SORT_STRING);
        if (count($protected) + count($additional) > 200 || ($protected !== [] && $additional !== [] && $additional[0] < max($protected))) {
            throw new LogicException('Equipment acquisitions must remain bounded and follow global UUID order.');
        }
        $rows = Equipment::query()->where('workshop_id', $actor->workshopId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()
            ->get(['id', 'workshop_id', 'name', 'description', 'category', 'total_quantity', 'is_active', 'version']);
        $locked = array_values(array_unique([...$protected, ...$rows->pluck('id')->all()]));
        sort($locked, SORT_STRING);
        $connection = DB::connection('pgsql');
        $unit = $connection->selectOne('SELECT pg_current_xact_id()::text AS id');
        $connection->select('SELECT set_config(?, ?, true)', [self::SETTING, json_encode(['unit' => $unit?->id, 'workshop' => $actor->workshopId, 'actor' => $actor->actorId, 'role' => $actor->role, 'ids' => $locked], JSON_THROW_ON_ERROR)]);

        return array_values($rows->map(static fn (Equipment $row): EquipmentState => new EquipmentState($row->id, $row->name, $row->description, $row->category, $row->total_quantity, $row->is_active ? 'active' : 'archived', $row->version, null))->all());
    }

    public function assertProtected(ActorContext $actor, string $id): void
    {
        if (! in_array($id, $this->protectedIds($actor), true)) {
            throw new LogicException('Equipment must first be protected in this SQL unit.');
        }
    }
}
