<?php

declare(strict_types=1);

namespace App\Modules\Catalog\PublicApi;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ImagePublishedData
{
    public function __construct(public string $imageId, public int $generation, public ImageVariantData $thumbnail, public ImageVariantData $detail) {}
}
