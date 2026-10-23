<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\ReadEquipment;

use App\Modules\Catalog\PublicApi\EquipmentPage;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ManagementPageData
{
    /** @param list<EquipmentSummaryData> $items */
    public function __construct(public array $items, public int $page, public int $perPage, public bool $hasMore, public string $observedAt) {}

    public static function fromPage(EquipmentPage $page): self
    {
        return new self(array_map(EquipmentSummaryData::fromEquipment(...), $page->items), $page->page, $page->perPage, $page->hasMore, $page->observedAt);
    }
}
