<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Policies;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EquipmentPolicy
{
    public function __construct(private AuthorizeActor $authorize) {}

    public function create(ActorContext $actor): void
    {
        $this->authorize->handle($actor, 'manage-catalog');
    }

    public function update(ActorContext $actor, Equipment $equipment): void
    {
        if ($equipment->workshop_id !== $actor->workshopId) {
            throw (new ModelNotFoundException)->setModel(Equipment::class);
        }
        $this->authorize->handle($actor, 'manage-catalog');
    }
}
