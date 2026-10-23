<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class CommandReceipt
{
    public function __construct(
        public string $operationKey,
        public string $operationType,
        #[LiteralTypeScriptType("'equipment'|'loanRequest'|'equipmentImage'|'loanDocument'")] public string $resourceType,
        public string $resourceId,
        public int $resultVersion,
        public string $recordedAt,
        public bool $replayed,
    ) {}
}
