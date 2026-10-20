<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CatalogAudience: string
{
    case Catalog = 'catalog';
    case Management = 'catalogManagement';
}
