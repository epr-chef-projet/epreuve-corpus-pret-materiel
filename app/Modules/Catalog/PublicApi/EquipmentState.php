<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

/** Materialized state read under the caller's transaction lock. */
final readonly class EquipmentState
{
    public function __construct(
        public string $equipmentId, public string $name, public ?string $description, public string $category,
        public int $totalQuantity, public string $status, public int $equipmentVersion, public ?ImagePublishedData $image,
    ) {}
}
