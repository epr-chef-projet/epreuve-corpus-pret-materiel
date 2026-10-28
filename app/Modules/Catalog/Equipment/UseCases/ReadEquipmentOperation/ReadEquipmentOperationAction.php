<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipmentOperation;

use App\Infrastructure\Operations\ClosedOperationResultData;
use App\Infrastructure\Operations\OperationResultData;
use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\ReadOperationResult;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Support\Facades\Gate;
use LogicException;

/** Reads a validated operation reference for the current manager, never for a client-supplied actor. */
final class ReadEquipmentOperationAction
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize, private ReadOperationResult $operations) {}

    public function handle(ActorContext $actor, string $operationType, string $operationKey): OperationResultData|ClosedOperationResultData
    {
        $this->authorize->handle($actor, 'manage-catalog');
        Gate::forUser(null)->authorize('viewAny', [Equipment::class, $actor, CatalogAudience::Management]);
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $operationType, $operationKey, &$result): void {
            $result = $this->operations->handle(new OperationScope($actor->workshopId, $actor->actorId, $operationType, $operationKey));
        });

        return $result ?? throw new LogicException('Equipment operation was not materialized.');
    }
}
