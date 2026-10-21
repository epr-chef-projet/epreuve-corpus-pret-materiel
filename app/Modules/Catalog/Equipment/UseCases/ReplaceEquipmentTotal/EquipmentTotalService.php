<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReplaceEquipmentTotal;

use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\PublicApi\Errors\ContentConflict;
use App\Modules\Catalog\PublicApi\Errors\TotalBelowCommitments;
use App\Modules\Catalog\PublicApi\Errors\VersionExhausted;

final class EquipmentTotalService
{
    public function replace(Equipment $equipment, int $totalQuantity, int $expectedVersion, int $minimumCommittedQuantity): void
    {
        if ($equipment->version !== $expectedVersion) {
            throw new ContentConflict('Equipment was changed since the total was read.');
        }
        if ($totalQuantity < $minimumCommittedQuantity) {
            throw new TotalBelowCommitments($minimumCommittedQuantity);
        }
        if ($equipment->version === 2147483647) {
            throw new VersionExhausted('Equipment version is exhausted.');
        }
        $equipment->total_quantity = $totalQuantity;
        $equipment->version++;
    }
}
