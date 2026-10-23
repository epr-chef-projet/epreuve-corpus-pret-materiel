<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OperationResultData
{
    public function __construct(#[LiteralTypeScriptType("'applied'|'notEstablished'")] public string $status, public ?CommandReceipt $receipt) {}
}
