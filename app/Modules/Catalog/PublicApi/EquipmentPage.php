<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class EquipmentPage
{
    /** @param list<EquipmentData> $items */
    public function __construct(public array $items, public int $page, public int $perPage, public bool $hasMore, public string $observedAt) {}
}
