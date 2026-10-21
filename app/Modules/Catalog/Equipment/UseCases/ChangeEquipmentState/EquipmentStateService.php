<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\Errors\ContentConflict;
use App\Modules\Catalog\PublicApi\Errors\VersionExhausted;

final class EquipmentStateService
{
    /** Returns whether the protected state actually changed. */
    public function change(Equipment $equipment, string $state, int $expectedVersion): bool
    {
        if ($equipment->version !== $expectedVersion) {
            throw new ContentConflict('Equipment was changed since its state was read.');
        }
        $active = $state === 'active';
        if ($equipment->is_active === $active) {
            return false;
        }
        if ($equipment->version === 2147483647) {
            throw new VersionExhausted('Equipment version is exhausted.');
        }
        $equipment->is_active = $active;
        $equipment->version++;

        return true;
    }
}
