<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\Catalog\PublicApi\ImagePublishedData;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class EquipmentSummaryData
{
    public function __construct(public string $equipmentId, public string $name, public string $category, public int $totalQuantity, #[LiteralTypeScriptType("'active'|'archived'")] public string $status, public int $equipmentVersion, public ?ImagePublishedData $image) {}

    public static function fromEquipment(EquipmentData $equipment): self
    {
        return new self($equipment->equipmentId, $equipment->name, $equipment->category, $equipment->totalQuantity, $equipment->status, $equipment->equipmentVersion, $equipment->image);
    }
}
