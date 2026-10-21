<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState;

use App\Infrastructure\Operations\CommandReceipt;
use App\Infrastructure\Operations\OperationRepository;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\RetrySqlUnit;
use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class ChangeEquipmentStateAction
{
    public const ARCHIVE = 'catalog.equipment.archive';

    public const REACTIVATE = 'catalog.equipment.reactivate';

    public function __construct(private SqlUnit $units, private RetrySqlUnit $retry, private OperationRepository $operations, private EquipmentRepository $equipment, private EquipmentStateService $states, private EquipmentPolicy $policy) {}

    public function handle(ActorContext $actor, string $id, string $operationType, int $expectedVersion, string $operationKey): CommandReceipt
    {
        if (! Str::isUuid($id) || strtolower($id) !== $id || ! in_array($operationType, [self::ARCHIVE, self::REACTIVATE], true) || $expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid equipment state command.');
        }
        $scope = new OperationScope($actor->workshopId, $actor->actorId, $operationType, $operationKey);
        $hash = hash('sha256', json_encode(['equipmentId' => $id, 'expectedVersion' => $expectedVersion], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $receipt = null;
        $this->retry->run(function (int $budget) use ($actor, $id, $operationType, $expectedVersion, $scope, $hash, &$receipt): void {
            $receipt = null;
            $this->units->forActor($actor, function () use ($actor, $id, $operationType, $expectedVersion, $scope, $hash, $budget, &$receipt): void {
                $this->retry->limit($budget);
                $this->policy->create($actor);
                $receipt = $this->operations->acquire($scope, $hash);
                if ($receipt !== null) {
                    return;
                }
                $equipment = $this->equipment->lock($actor, $id);
                $this->policy->update($actor, $equipment);
                $changed = $this->states->change($equipment, $operationType === self::ARCHIVE ? 'archived' : 'active', $expectedVersion);
                if ($changed) {
                    $this->equipment->save($actor, $equipment);
                }
                $receipt = $this->operations->record($scope, $hash, $equipment->id, $equipment->version, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
                if ($changed) {
                    PublishCatalogInvalidation::dispatch($actor->workshopId)->afterCommit();
                }
            });
        });

        return $receipt ?? throw new LogicException('Equipment state command produced no receipt.');
    }
}
