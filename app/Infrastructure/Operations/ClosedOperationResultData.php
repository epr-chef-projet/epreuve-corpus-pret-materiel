<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ClosedOperationResultData
{
    #[LiteralTypeScriptType("'closed'")]
    public string $status;

    public null $receipt;

    public function __construct(public OperationClosureData $closure)
    {
        $this->status = 'closed';
        $this->receipt = null;
    }
}
