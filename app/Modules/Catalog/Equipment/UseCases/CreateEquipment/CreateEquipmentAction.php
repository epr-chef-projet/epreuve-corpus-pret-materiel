<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\CreateEquipment;

use App\Infrastructure\Operations\CommandReceipt;
use App\Infrastructure\Operations\OperationRepository;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\RetrySqlUnit;
use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentTextService;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use InvalidArgumentException;

final class CreateEquipmentAction
{
    public const OPERATION = 'catalog.equipment.create';

    public function __construct(private SqlUnit $units, private RetrySqlUnit $retry, private OperationRepository $operations, private EquipmentRepository $equipment, private EquipmentTextService $text, private EquipmentPolicy $policy) {}

    public function handle(ActorContext $actor, EquipmentInput $input, int $totalQuantity, string $operationKey): CommandReceipt
    {
        $this->text->validate($input);
        if ($totalQuantity < 0 || $totalQuantity > 999999) {
            throw new InvalidArgumentException('Initial equipment total is out of bounds.');
        }
        $scope = new OperationScope($actor->workshopId, $actor->actorId, self::OPERATION, $operationKey);
        $hash = hash('sha256', json_encode(['name' => $input->name, 'category' => $input->category, 'description' => $input->description, 'totalQuantity' => $totalQuantity], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $receipt = null;
        $this->retry->run(function (int $budget) use ($actor, $input, $totalQuantity, $scope, $hash, &$receipt): void {
            $receipt = null;
            $this->units->forActor($actor, function () use ($actor, $input, $totalQuantity, $scope, $hash, $budget, &$receipt): void {
                $this->retry->limit($budget);
                $this->policy->create($actor);
                $receipt = $this->operations->acquire($scope, $hash);
                if ($receipt !== null) {
                    return;
                }
                $equipment = $this->equipment->insert($actor, $input, $totalQuantity);
                $receipt = $this->operations->record($scope, $hash, $equipment->id, $equipment->version, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
                PublishCatalogInvalidation::dispatch($actor->workshopId)->afterCommit();
            });
        });

        return $receipt ?? throw new \LogicException('Equipment command produced no receipt.');
    }
}
