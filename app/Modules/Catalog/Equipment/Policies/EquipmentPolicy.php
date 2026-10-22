<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Policies;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EquipmentPolicy
{
    public function viewAny(?Authenticatable $user, ActorContext $actor, CatalogAudience $audience): bool
    {
        return $audience !== CatalogAudience::Management || Role::from($actor->role)->allows('manage-catalog');
    }

    public function create(?Authenticatable $user, ActorContext $actor): bool
    {
        return Role::from($actor->role)->allows('manage-catalog');
    }

    public function update(?Authenticatable $user, Equipment $equipment, ActorContext $actor): bool
    {
        if ($equipment->workshop_id !== $actor->workshopId) {
            throw (new ModelNotFoundException)->setModel(Equipment::class);
        }

        return Role::from($actor->role)->allows('manage-catalog');
    }
}
