<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OperationClosureData
{
    #[LiteralTypeScriptType("'catalog.equipment.create'")]
    public string $operationType;

    public function __construct(public string $operationKey, public string $closedAt)
    {
        $this->operationType = 'catalog.equipment.create';
    }
}
