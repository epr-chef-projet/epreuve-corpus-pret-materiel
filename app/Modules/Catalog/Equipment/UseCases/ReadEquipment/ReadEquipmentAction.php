<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;

final class ReadEquipmentAction
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize, private EquipmentRepository $equipment) {}

    public function handle(ActorContext $actor, string $id): EquipmentData
    {
        $this->authorize->handle($actor, 'manage-catalog');
        $result = null;
        $this->units->forActorRead($actor, function () use ($actor, $id, &$result): void {
            $result = $this->equipment->find($actor, $id);
        });

        return $result ?? throw new \LogicException('Equipment detail was not materialized.');
    }
}
