<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipmentOperation;

use App\Infrastructure\Operations\OperationResultData;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\ReadOperationResult;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use LogicException;

/** Reads a validated operation reference for the current manager, never for a client-supplied actor. */
final class ReadEquipmentOperationAction
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize, private ReadOperationResult $operations) {}

    public function handle(ActorContext $actor, string $operationType, string $operationKey): OperationResultData
    {
        $this->authorize->handle($actor, 'manage-catalog');
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $operationType, $operationKey, &$result): void {
            $result = $this->operations->handle(new OperationScope($actor->workshopId, $actor->actorId, $operationType, $operationKey));
        });

        return $result ?? throw new LogicException('Equipment operation was not materialized.');
    }
}
