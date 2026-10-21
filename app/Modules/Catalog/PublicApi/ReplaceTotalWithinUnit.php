<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\LockEquipment\EquipmentLockRepository;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\Catalog\Equipment\UseCases\ReplaceEquipmentTotal\EquipmentTotalService;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** No independent transaction or receipt: WorkshopOperations owns the command and protected minimum. */
final class ReplaceTotalWithinUnit
{
    public function __construct(private EquipmentLockRepository $locks, private EquipmentRepository $equipment, private EquipmentPolicy $policy, private EquipmentTotalService $totals) {}

    public function handle(ActorContext $actor, string $equipmentId, int $totalQuantity, int $expectedVersion, int $minimumCommittedQuantity): int
    {
        if (! Str::isUuid($equipmentId) || strtolower($equipmentId) !== $equipmentId || $totalQuantity < 0 || $totalQuantity > 999999 || $expectedVersion < 1 || $expectedVersion > 2147483647 || $minimumCommittedQuantity < 0 || $minimumCommittedQuantity > 999999) {
            throw new InvalidArgumentException('Invalid equipment, total, version or protected minimum.');
        }
        $this->locks->assertProtected($actor, $equipmentId);
        $this->policy->create($actor);
        $equipment = $this->equipment->lock($actor, $equipmentId);
        $this->policy->update($actor, $equipment);
        $this->totals->replace($equipment, $totalQuantity, $expectedVersion, $minimumCommittedQuantity);
        $this->equipment->save($actor, $equipment);
        PublishCatalogInvalidation::dispatch($actor->workshopId)->afterCommit();

        return $equipment->version;
    }
}
