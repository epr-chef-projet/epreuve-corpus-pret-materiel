<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\LockEquipment\EquipmentLockRepository;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\Catalog\Equipment\UseCases\ReplaceEquipmentTotal\EquipmentTotalService;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** No independent transaction or receipt: WorkshopOperations owns the command and protected minimum. */
final class ReplaceTotalWithinUnit
{
    public function __construct(private EquipmentLockRepository $locks, private EquipmentRepository $equipment, private AuthorizeActor $authorize, private EquipmentTotalService $totals) {}

    public function handle(ActorContext $actor, string $equipmentId, int $totalQuantity, int $expectedVersion, int $minimumCommittedQuantity): int
    {
        if (! Str::isUuid($equipmentId) || strtolower($equipmentId) !== $equipmentId || $totalQuantity < 0 || $totalQuantity > 999999 || $expectedVersion < 1 || $expectedVersion > 2147483647 || $minimumCommittedQuantity < 0 || $minimumCommittedQuantity > 999999) {
            throw new InvalidArgumentException('Invalid equipment, total, version or protected minimum.');
        }
        $this->locks->assertProtected($actor, $equipmentId);
        $this->authorize->handle($actor, 'manage-catalog');
        Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Management]);
        $equipment = $this->equipment->lock($actor, $equipmentId);
        Gate::forUser(null)->authorize('update', [$equipment, $actor]);
        $this->totals->replace($equipment, $totalQuantity, $expectedVersion, $minimumCommittedQuantity);
        $this->equipment->save($actor, $equipment);
        PublishCatalogInvalidation::dispatch($actor->workshopId)->afterCommit();

        return $equipment->version;
    }
}
