<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use InvalidArgumentException;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class EquipmentQuery
{
    public const MAX_PAGE = 9007199254740991;

    public function __construct(public string $search = '', public ?string $category = null, public int $page = 1, public int $perPage = 25, public CatalogAudience $audience = CatalogAudience::Catalog)
    {
        if (! mb_check_encoding($search, 'UTF-8') || str_contains($search, "\0") || mb_strlen($search) > 120 || ($category !== null && (! mb_check_encoding($category, 'UTF-8') || str_contains($category, "\0") || mb_strlen($category) < 1 || mb_strlen($category) > 80)) || $page < 1 || $page > self::MAX_PAGE || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid bounded equipment query.');
        }
    }
}
