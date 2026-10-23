<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\EditEquipmentText;

use App\Infrastructure\Operations\CommandReceipt;
use App\Infrastructure\Operations\OperationRepository;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\RetrySqlUnit;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\InvalidateCatalog\PublishCatalogInvalidation;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentRepository;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInput;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentTextService;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EditEquipmentTextAction
{
    public const OPERATION = 'catalog.equipment.update_text';

    public function __construct(private SqlUnit $units, private RetrySqlUnit $retry, private OperationRepository $operations, private EquipmentRepository $equipment, private EquipmentTextService $text, private AuthorizeActor $authorize) {}

    public function handle(ActorContext $actor, string $id, EquipmentInput $input, int $expectedVersion, string $operationKey): CommandReceipt
    {
        $this->text->validate($input);
        if (! Str::isUuid($id) || strtolower($id) !== $id || $expectedVersion < 1 || $expectedVersion > 2147483647) {
            throw new InvalidArgumentException('Invalid equipment or expected version.');
        }
        $scope = new OperationScope($actor->workshopId, $actor->actorId, self::OPERATION, $operationKey);
        $hash = hash('sha256', json_encode(['equipmentId' => $id, 'name' => $input->name, 'category' => $input->category, 'description' => $input->description, 'expectedVersion' => $expectedVersion], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $receipt = null;
        $this->retry->run(function (int $budget) use ($actor, $id, $input, $expectedVersion, $scope, $hash, &$receipt): void {
            $receipt = null;
            $this->units->forActor($actor, function () use ($actor, $id, $input, $expectedVersion, $scope, $hash, $budget, &$receipt): void {
                $this->retry->limit($budget);
                $this->authorize->handle($actor, 'manage-catalog');
                Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Management]);
                $receipt = $this->operations->acquire($scope, $hash);
                if ($receipt !== null) {
                    return;
                }
                $equipment = $this->equipment->lock($actor, $id);
                Gate::forUser(null)->authorize('update', [$equipment, $actor]);
                $this->text->revise($equipment, $input, $expectedVersion);
                $this->equipment->save($actor, $equipment);
                $receipt = $this->operations->record($scope, $hash, $equipment->id, $equipment->version, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
                PublishCatalogInvalidation::dispatch($actor->workshopId)->afterCommit();
            });
        });

        return $receipt ?? throw new \LogicException('Equipment command produced no receipt.');
    }
}
