<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class EquipmentData
{
    public function __construct(
        public string $equipmentId, public string $name, public ?string $description, public string $category,
        public int $totalQuantity, #[LiteralTypeScriptType("'active'|'archived'")] public string $status,
        public int $equipmentVersion, public ?ImagePublishedData $image,
    ) {}
}
