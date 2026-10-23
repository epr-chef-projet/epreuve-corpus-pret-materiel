<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ImageVariantData
{
    public function __construct(public string $url, public int $width, public int $height, #[LiteralTypeScriptType("'image/webp'")] public string $mimeType) {}
}
