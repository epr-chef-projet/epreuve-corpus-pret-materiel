<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\LockEquipment\EquipmentLockRepository;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Participant only: the caller retains ownership of the writable pgsql unit. */
final class LockEquipmentBatch
{
    public function __construct(private SqlUnit $units, private ResolveActor $actors, private EquipmentLockRepository $locks) {}

    /** @param array<array-key, mixed> $ids Dense list of at most 200 distinct canonical UUIDs.
     * @return list<EquipmentState> Same-workshop states ordered by UUID; missing and foreign IDs are omitted alike.
     */
    public function handle(ActorContext $actor, array $ids): array
    {
        if (! array_is_list($ids) || count($ids) > 200) {
            throw new InvalidArgumentException('Equipment protection accepts at most 200 distinct UUIDs.');
        }
        foreach ($ids as $id) {
            if (! is_string($id) || ! Str::isUuid($id) || strtolower($id) !== $id) {
                throw new InvalidArgumentException('Invalid equipment UUID.');
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Equipment UUIDs must be distinct.');
        }
        $this->units->assertActor(DB::connection('pgsql'), $actor);
        $this->actors->assertCurrent($actor);
        Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Catalog]);

        return $this->locks->acquire($actor, $ids);
    }
}
