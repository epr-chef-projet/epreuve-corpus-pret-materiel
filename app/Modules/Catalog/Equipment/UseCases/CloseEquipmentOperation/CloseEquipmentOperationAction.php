<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\CloseEquipmentOperation;

use App\Infrastructure\Operations\ClosedOperationResultData;
use App\Infrastructure\Operations\OperationClosureData;
use App\Infrastructure\Operations\OperationRepository;
use App\Infrastructure\Operations\OperationResultData;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\RetrySqlUnit;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class CloseEquipmentOperationAction
{
    public function __construct(private SqlUnit $units, private RetrySqlUnit $retry, private OperationRepository $operations, private AuthorizeActor $authorize) {}

    public function handle(ActorContext $actor, string $operationKey): OperationResultData|ClosedOperationResultData
    {
        $scope = new OperationScope($actor->workshopId, $actor->actorId, CreateEquipmentAction::OPERATION, $operationKey);
        $result = null;
        $this->retry->run(function (int $budget) use ($actor, $scope, &$result): void {
            $result = null;
            $this->units->forActor($actor, function () use ($actor, $scope, $budget, &$result): void {
                $this->retry->limit($budget);
                $this->authorize->handle($actor, 'manage-catalog');
                Gate::forUser(null)->authorize('create', [Equipment::class, $actor]);
                $result = $this->operations->close($scope, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));
            });
        });

        if ($result instanceof OperationClosureData) {
            return new ClosedOperationResultData($result);
        }

        return $result === null ? throw new LogicException('Equipment resolution produced no terminal outcome.') : new OperationResultData('applied', $result);
    }
}
